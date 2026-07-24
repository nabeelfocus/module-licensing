<?php
declare(strict_types=1);

/**
 * Copyright © Focus. All rights reserved.
 * @author Focus Team
 * @package Focus_Licensing
 */

namespace Focus\Licensing\Api;

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
