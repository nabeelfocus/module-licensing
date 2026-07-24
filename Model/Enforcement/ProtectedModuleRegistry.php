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

        if (isset($this->loadSignedProtected()[$moduleName])) {
            return true;
        }

        if ($this->isCommercialNamespace($moduleName)) {
            return true;
        }

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
     * The signed `protected_modules` list — a tampered payload fails
     * signature verification and yields null here, so editing it frees nothing.
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
