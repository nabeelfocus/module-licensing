<?php
declare(strict_types=1);

/**
 * Copyright © Focus. All rights reserved.
 * @author Focus Team
 * @package Focus_Licensing
 */

namespace Focus\Licensing\Model\Enforcement;

use Focus\Licensing\Api\ProtectedModuleRegistryInterface;
use Focus\Licensing\Model\LicenseCacheManager;
use Magento\Framework\Config\DataInterface;

/**
 * Decides which modules the enforcement layer guards.
 *
 * Three sources, in descending order of trust:
 *
 *   1. The SIGNED server payload (`protected_modules`). Authoritative and
 *      tamper-proof: it sits inside the Ed25519 signature, so it can be
 *      neither shortened nor forged on the customer's disk.
 *   2. The commercial vendor namespaces below. A module named `Focus_*` is
 *      commercial by default — and that rule lives in this class, part of the
 *      licensing module, not in a file shipped alongside the module it
 *      protects.
 *   3. `etc/focus_licensing.xml` declarations. Convenience only: they carry
 *      the merchant-facing label, and let a module outside the commercial
 *      namespaces opt in.
 *
 * WHY IT IS ORDERED THIS WAY: the declaration file used to be the only source.
 * Deleting it from a module dropped that module out of the guarded set, and it
 * then ran unlicensed — a silent bypass that left the module's own code
 * untouched and survived updates. Sources 1 and 2 close it: whether a module is
 * commercial no longer travels in a file the customer can delete.
 *
 * For the same reason `enabled="false"` in a declaration can no longer un-guard
 * a module covered by source 1 or 2. Opting out is a decision for the licence
 * server, never for a file on the customer's disk.
 */
class ProtectedModuleRegistry implements ProtectedModuleRegistryInterface
{
    /**
     * Module prefixes that are commercial unless the signed payload says
     * otherwise. Add a prefix here when Focus sells under a new vendor name.
     */
    private const COMMERCIAL_VENDOR_PREFIXES = ['Focus_'];

    /**
     * The licensing modules themselves are never guarded — guarding the guard
     * would deadlock, and the server is not a customer-facing module.
     */
    private const NEVER_GUARDED = [
        'Focus_Licensing',
        'Focus_LicenseServer',
    ];

    /** @var array<string, array{name: string, label: string, enabled: bool}>|null */
    private ?array $declarations = null;

    /** @var array<string, true>|null */
    private ?array $signedProtected = null;

    /**
     * @param DataInterface $config
     * @param LicenseCacheManager $cacheManager
     */
    public function __construct(
        private readonly DataInterface $config,
        private readonly LicenseCacheManager $cacheManager
    ) {}

    /**
     * Every module currently treated as commercial: the signed list, plus local
     * declarations, minus the licensing modules themselves.
     *
     * Namespace membership alone cannot be enumerated here — this class does not
     * know what is installed — so callers that need the full set combine this
     * with module discovery. isGuarded() is the authority for a single module.
     *
     * @return string[]
     */
    public function getGuardedModules(): array
    {
        $modules = array_keys($this->loadSignedProtected());

        foreach ($this->loadDeclarations() as $name => $entry) {
            if (($entry['enabled'] ?? true) === true || $this->isCommercialNamespace($name)) {
                $modules[] = $name;
            }
        }

        $modules = array_values(array_unique(array_filter(
            $modules,
            static fn (string $name): bool => !in_array($name, self::NEVER_GUARDED, true)
        )));
        sort($modules);

        return $modules;
    }

    /**
     * Whether this module must hold a licence to operate.
     *
     * @param string $moduleName
     * @return bool
     */
    public function isGuarded(string $moduleName): bool
    {
        if (in_array($moduleName, self::NEVER_GUARDED, true)) {
            return false;
        }

        // 1. Signed server list — authoritative, cannot be edited locally.
        if (isset($this->loadSignedProtected()[$moduleName])) {
            return true;
        }

        // 2. Commercial namespace — the rule lives here, not in the module.
        if ($this->isCommercialNamespace($moduleName)) {
            return true;
        }

        // 3. Local opt-in, for modules outside the commercial namespaces.
        $declaration = $this->loadDeclarations()[$moduleName] ?? null;

        return $declaration !== null && ($declaration['enabled'] ?? true) === true;
    }

    /**
     * Human-readable label for a module (falls back to the module name).
     *
     * @param string $moduleName
     * @return string
     */
    public function getLabel(string $moduleName): string
    {
        return $this->loadDeclarations()[$moduleName]['label'] ?? $moduleName;
    }

    /**
     * @param string $moduleName
     * @return bool
     */
    private function isCommercialNamespace(string $moduleName): bool
    {
        foreach (self::COMMERCIAL_VENDOR_PREFIXES as $prefix) {
            if (str_starts_with($moduleName, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The signed `protected_modules` list from the cached licence payload.
     *
     * Read through LicenseCacheManager, which verifies the Ed25519 signature on
     * every read: a tampered payload yields null here AND is rejected as a
     * licence at the same time, so editing it frees nothing.
     *
     * @return array<string, true>
     */
    private function loadSignedProtected(): array
    {
        if ($this->signedProtected !== null) {
            return $this->signedProtected;
        }

        $signed = [];

        try {
            $state = $this->cacheManager->read();
            foreach ((array) ($state['protected_modules'] ?? []) as $name) {
                $name = trim((string) $name);
                if ($name !== '') {
                    $signed[$name] = true;
                }
            }
        } catch (\Exception) {
            // Fail soft: namespace and declarations still apply.
            $signed = [];
        }

        return $this->signedProtected = $signed;
    }

    /**
     * @return array<string, array{name: string, label: string, enabled: bool}>
     */
    private function loadDeclarations(): array
    {
        if ($this->declarations === null) {
            $this->declarations = $this->config->get() ?: [];
        }

        return $this->declarations;
    }
}
