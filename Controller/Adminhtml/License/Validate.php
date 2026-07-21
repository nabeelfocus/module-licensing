<?php
declare(strict_types=1);

namespace Focus\Licensing\Controller\Adminhtml\License;

/**
 * "Validate Now" / "Activate License": force an immediate server round-trip.
 * The guard bootstraps via activate() automatically when no cached state exists.
 */
class Validate extends AbstractDashboardAction
{
    protected function runAction(): array
    {
        return $this->dashboardActions->validateNow();
    }
}
