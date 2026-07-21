<?php
declare(strict_types=1);

/**
 * Copyright © Focus. All rights reserved.
 * @author Focus Team
 * @package Focus_Licensing
 */

namespace Focus\Licensing\Config;

use Magento\Framework\Config\ConverterInterface;

/**
 * Converts the merged focus_licensing.xml DOM into a keyed array:
 *
 *   [
 *     'Focus_StorageAddons' => ['name' => 'Focus_StorageAddons', 'label' => 'Storage Addons', 'enabled' => true],
 *     ...
 *   ]
 *
 * A module with enabled="false" is treated as not guarded (an explicit opt-out).
 */
class Converter implements ConverterInterface
{
    /**
     * @param \DOMDocument $source
     * @return array<string, array{name: string, label: string, enabled: bool}>
     */
    public function convert($source): array
    {
        $result = [];

        /** @var \DOMNodeList $modules */
        $modules = $source->getElementsByTagName('module');
        foreach ($modules as $module) {
            if (!$module instanceof \DOMElement) {
                continue;
            }
            $name = trim((string) $module->getAttribute('name'));
            if ($name === '') {
                continue;
            }
            $enabledAttr = $module->getAttribute('enabled');
            $enabled = $enabledAttr === '' ? true : filter_var($enabledAttr, FILTER_VALIDATE_BOOLEAN);

            $result[$name] = [
                'name'    => $name,
                'label'   => trim((string) $module->getAttribute('label')) ?: $name,
                'enabled' => $enabled,
            ];
        }

        return $result;
    }
}
