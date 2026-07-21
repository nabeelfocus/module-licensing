<?php
declare(strict_types=1);

/**
 * Copyright © Focus. All rights reserved.
 * @author Focus Team
 * @package Focus_Licensing
 */

namespace Focus\Licensing\Model;

use Focus\Licensing\Api\ModuleDiscoveryInterface;
use Magento\Framework\Module\ModuleListInterface;

class ModuleDiscovery implements ModuleDiscoveryInterface
{
    private const EXCLUDED_MODULES = [
        'Focus_Licensing',
        'Focus_LicenseServer'
    ];

    /**
     * @param ModuleListInterface $moduleList
     */
    public function __construct(
        private readonly ModuleListInterface $moduleList
    ) {}

    /**
     * @return array
     */
    public function getInstalledFocusModules(): array
    {
        $focusModules = [];
        
        foreach ($this->moduleList->getNames() as $moduleName) {
            if (str_starts_with($moduleName, 'Focus_') && !in_array($moduleName, self::EXCLUDED_MODULES, true)) {
                $focusModules[] = $moduleName;
            }
        }
        
        return $focusModules;
    }
}
