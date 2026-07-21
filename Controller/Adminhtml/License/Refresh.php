<?php
declare(strict_types=1);

/**
 * Copyright © Focus. All rights reserved.
 * @author Focus Team
 * @package Focus_Licensing
 */

namespace Focus\Licensing\Controller\Adminhtml\License;

/**
 * "Refresh License": drop the local cache and download a complete fresh
 * signed payload (activate bootstrap path, includes a fresh HMAC secret).
 */
class Refresh extends AbstractDashboardAction
{
    /**
     * @return array
     */
    protected function runAction(): array
    {
        return $this->dashboardActions->refresh();
    }
}
