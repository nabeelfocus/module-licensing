<?php
declare(strict_types=1);

namespace Focus\Licensing\Api;

/**
 * The set of commercial Focus modules that have opted into license enforcement
 * via etc/focus_licensing.xml.
 *
 * This is only the "which modules are guarded" list. Whether a guarded module
 * is *licensed* is decided elsewhere, by LicenseGuardInterface against the
 * signed allowed_modules payload.
 */
interface ProtectedModuleRegistryInterface
{
    /**
     * All guarded module names, e.g. ['Focus_StorageAddons', 'Focus_KSystemSyncBulkOrderApi'].
     *
     * @return string[]
     */
    public function getGuardedModules(): array;

    /**
     * Whether the given module has declared itself guarded.
     *
     * @param string $moduleName
     * @return bool
     */
    public function isGuarded(string $moduleName): bool;

    /**
     * Human-readable label for a guarded module (falls back to the module name).
     *
     * @param string $moduleName
     * @return string
     */
    public function getLabel(string $moduleName): string;
}
