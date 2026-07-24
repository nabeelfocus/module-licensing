<?php
declare(strict_types=1);

/**
 * Copyright © Focus. All rights reserved.
 * @author Focus Team
 * @package Focus_Licensing
 */

namespace Focus\Licensing\Controller\Adminhtml\License;

class Validate extends AbstractDashboardAction
{
    /**
     * @return array
     */
    protected function runAction(): array
    {
        return $this->dashboardActions->validateNow();
    }
}
