<?php
declare(strict_types=1);

/**
 * Copyright © Focus. All rights reserved.
 * @author Focus Team
 * @package Focus_Licensing
 */

namespace Focus\Licensing\Controller\Adminhtml\License;

/**
 * "Release slot": free the production domain slot held by another domain on
 * this licence — a decommissioned server, or a staging box that was rebuilt
 * under a new hostname.
 *
 * This store is left running; only deactivate() releases the current domain.
 */
class ReleaseDomain extends AbstractDashboardAction
{
    /**
     * @return array
     */
    protected function runAction(): array
    {
        return $this->dashboardActions->releaseDomain(
            (string) $this->getRequest()->getParam('domain', '')
        );
    }
}
