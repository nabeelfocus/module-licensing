<?php
declare(strict_types=1);

/**
 * Copyright © Focus. All rights reserved.
 * @author Focus Team
 * @package Focus_Licensing
 */

namespace Focus\Licensing\Api;

interface ModuleDiscoveryInterface
{
    /**
     * Get a list of all active Focus_* modules (excluding infrastructure like Licensing).
     *
     * @return string[] Array of module names, e.g. ['Focus_BLK', 'Focus_ERP']
     */
    public function getInstalledFocusModules(): array;
}
