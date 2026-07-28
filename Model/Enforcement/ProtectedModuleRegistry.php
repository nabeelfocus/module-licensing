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

    /** @var array<string, true>|null */
    private ?array $signedProtected = null;

    /**
     * @param LicenseCacheManager $cacheManager
     */
    public function __construct(
        private readonly LicenseCacheManager $cacheManager
    ) {}

    /**
     * @return string[]
     */
    public function getGuardedModules(): array
    {
        $modules = array_values(array_filter(
            array_keys($this->loadSignedProtected()),
            static fn (string $name): bool => !in_array($name, self::NEVER_GUARDED, true)
        ));

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

        return $this->isCommercialNamespace($moduleName);
    }

    /**
     * Human-readable label for a module (falls back to the module name).
     *
     * @param string $moduleName
     * @return string
     */
    public function getLabel(string $moduleName): string
    {
        return $moduleName;
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
}
