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
