<?php
declare(strict_types=1);

namespace Focus\Licensing\Api;

/**
 * Discovers all installed Focus commercial modules dynamically.
 */
interface ModuleDiscoveryInterface
{
    /**
     * Get a list of all active Focus_* modules (excluding infrastructure like Licensing).
     *
     * @return string[] Array of module names, e.g. ['Focus_BLK', 'Focus_ERP']
     */
    public function getInstalledFocusModules(): array;
}
