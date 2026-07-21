<?php
declare(strict_types=1);

namespace Focus\Licensing\Ui\DataProvider;

use Focus\Licensing\ViewModel\Dashboard\ModuleStatus;
use Magento\Framework\Api\Filter;
use Magento\Ui\DataProvider\AbstractDataProvider;

/**
 * Array-backed provider for the Commercial Modules listing.
 *
 * Rows come from live module discovery + the license guard — there is no
 * database table behind this grid, so collection-based operations are no-ops.
 */
class CommercialModulesProvider extends AbstractDataProvider
{
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

    public function getData(): array
    {
        $state = $this->moduleStatus->getLicenseState();
        $items = [];

        foreach ($this->moduleStatus->getRows() as $i => $row) {
            $items[] = [
                'id_field_name'   => 'module_id',
                'module_id'       => $i + 1,
                'module'          => $row['module'],
                'version'         => $row['version'],
                'enabled'         => $row['enabled'] ? __('Yes') : __('No'),
                'license_status'  => $row['status_label'],
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

    public function count(): int
    {
        return count($this->moduleStatus->getRows());
    }

    public function addFilter(Filter $filter)
    {
        // no-op: in-memory data set, no server-side filtering
    }

    public function addOrder($field, $direction)
    {
        // no-op: rows are pre-sorted by module name
    }

    public function setLimit($offset, $size)
    {
        // no-op: the full set is always small enough to return
    }
}
