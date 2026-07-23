<?php
declare(strict_types=1);

/**
 * Copyright © Focus. All rights reserved.
 * @author Focus Team
 * @package Focus_Licensing
 */

namespace Focus\Licensing\Ui\Component\Listing\Column;

use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

/**
 * Renders the Commercial Modules grid's License Status as a colour badge:
 * green = active, red = restricted (running but not covered by the licence —
 * the actual problem this dashboard exists to surface), grey = disabled (not
 * running, so not a licensing issue). Reuses the same severity ModuleStatus
 * already computes for the dashboard card, so the colour is consistent
 * everywhere this status appears.
 *
 * Requires <bodyTmpl>ui/grid/cells/html</bodyTmpl> on the column in the
 * listing XML, matching the Focus_LicenseServer result-code column pattern.
 */
class LicenseStatusColumn extends Column
{
    /**
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param array $components
     * @param array $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    /**
     * @param array $dataSource
     * @return array
     */
    public function prepareDataSource(array $dataSource): array
    {
        if (!isset($dataSource['data']['items'])) {
            return $dataSource;
        }

        $name = $this->getData('name');

        foreach ($dataSource['data']['items'] as &$item) {
            $label = (string) ($item[$name] ?? '');
            if ($label === '') {
                continue;
            }

            $severity = (string) ($item['severity'] ?? 'neutral');

            $item[$name] = sprintf(
                '<span class="focus-lic__badge focus-lic__badge--%s">%s</span>',
                htmlspecialchars($severity, ENT_QUOTES),
                htmlspecialchars(strtoupper($label), ENT_QUOTES)
            );
        }

        return $dataSource;
    }
}
