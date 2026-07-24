<?php
declare(strict_types=1);

/**
 * Copyright © Focus. All rights reserved.
 * @author Focus Team
 * @package Focus_Licensing
 */

namespace Focus\Licensing\Model\Enforcement;

use Focus\Licensing\Api\LicenseGuardInterface;
use Focus\Licensing\Api\ProtectedModuleRegistryInterface;
use Focus\Licensing\Logger\Logger;

class EnforcementGuard
{
    /** @var array<string, string|null> memoized className → blocked module|null */
    private array $memo = [];

    /**
     * @param ModuleNameResolver $resolver
     * @param ProtectedModuleRegistryInterface $registry
     * @param LicenseGuardInterface $licenseGuard
     * @param Logger $logger
     */
    public function __construct(
        private readonly ModuleNameResolver $resolver,
        private readonly ProtectedModuleRegistryInterface $registry,
        private readonly LicenseGuardInterface $licenseGuard,
        private readonly Logger $logger
    ) {}

    /**
     * @param string $className FQDN of the controller / observer / command / service
     * @return string|null owning a module name when it must be blocked, else null
     */
    public function getBlockedModuleForClass(string $className): ?string
    {
        if (array_key_exists($className, $this->memo)) {
            return $this->memo[$className];
        }

        return $this->memo[$className] = $this->evaluate($className);
    }

    /**
     * Direct check for a known module name (used by the menu guard, which
     * already knows the owning module of each menu item).
     */
    public function isModuleBlocked(string $moduleName): bool
    {
        try {
            return $this->registry->isGuarded($moduleName)
                && !$this->licenseGuard->isLicensed($moduleName);
        } catch (\Throwable $e) {
            $this->logger->error('Focus_Licensing: enforcement check failed (fail-open)', [
                'module'    => $moduleName,
                'exception' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * @param string $className
     * @return ?string
     */
    private function evaluate(string $className): ?string
    {
        try {
            $moduleName = $this->resolver->resolve($className);
            if ($moduleName === null) {
                return null;
            }
            if (!$this->registry->isGuarded($moduleName)) {
                return null;
            }
            if ($this->licenseGuard->isLicensed($moduleName)) {
                return null;
            }

            return $moduleName;
        } catch (\Throwable $e) {
            // Fail OPEN: an internal fault must never take the store down.
            $this->logger->error('Focus_Licensing: enforcement evaluation failed (fail-open)', [
                'class'     => $className,
                'exception' => $e->getMessage(),
            ]);
            return null;
        }
    }
}
