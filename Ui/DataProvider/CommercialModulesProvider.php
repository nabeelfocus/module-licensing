<?php
declare(strict_types=1);

/**
 * Copyright © Focus. All rights reserved.
 * @author Focus Team
 * @package Focus_Licensing
 */

namespace Focus\Licensing\Ui\DataProvider;

use Focus\Licensing\ViewModel\Dashboard\ModuleStatus;
use Magento\Framework\Api\Filter;
use Magento\Ui\DataProvider\AbstractDataProvider;

class CommercialModulesProvider extends AbstractDataProvider
{
    /**
     * @param string $name
     * @param string $primaryFieldName
     * @param string $requestFieldName
     * @param ModuleStatus $moduleStatus
     * @param array $meta
     * @param array $data
     */
    public function __construct(
        string $name,
        string $primaryFieldName,
        string $requestFieldName,
        private readonly ModuleStatus $moduleStatus,
        array $meta = [],
        array $data = []
    ) {
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
    }

    /**
     * @return array
     */
    public function getData(): array
    {
        $state = $this->moduleStatus->getLicenseState();
        $items = [];

        foreach ($this->moduleStatus->getRows() as $i => $row) {
            $items[] = [
                'id_field_name'   => 'module_id',
                'module_id'       => $i + 1,
                'label'           => $row['label'],
                'module'          => $row['module'],
                'enabled'         => $row['enabled'] ? __('Yes') : __('No'),
                'license_status'  => $row['status_label'],
                'severity'        => $row['severity'],
                'reason'          => $row['reason'] !== '' ? $row['reason'] : '—',
                'last_validation' => $row['last_validation'] !== null
                    ? $state->formatDate($row['last_validation'], true)
                    : '—',
            ];
        }

        return [
            'totalRecords' => count($items),
            'items'        => $items,
        ];
    }

    /**
     * @return int
     */
    public function count(): int
    {
        return count($this->moduleStatus->getRows());
    }

    /**
     * @param Filter $filter
     */
    public function addFilter(Filter $filter)
    {
        // no-op: in-memory data set, no server-side filtering
    }

    /**
     * @param mixed $field
     * @param mixed $direction
     */
    public function addOrder($field, $direction)
    {
        // no-op: rows are pre-sorted by module name
    }

    /**
     * @param mixed $offset
     * @param mixed $size
     */
    public function setLimit($offset, $size)
    {
        // no-op: the full set is always small enough to return
    }
}
