<?php
declare(strict_types=1);

namespace Focus\Licensing\Controller\Adminhtml\License;

/**
 * "Deactivate": release this store's registered domain slot on the license
 * server and clear the local state. Confirmation happens client-side.
 */
class Deactivate extends AbstractDashboardAction
{
    protected function runAction(): array
    {
        return $this->dashboardActions->deactivate();
    }
}
