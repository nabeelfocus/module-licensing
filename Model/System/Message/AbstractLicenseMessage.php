<?php
declare(strict_types=1);

/**
 * Copyright © Focus. All rights reserved.
 * @author Focus Team
 * @package Focus_Licensing
 */

namespace Focus\Licensing\Model\System\Message;

use Focus\Licensing\ViewModel\Dashboard\LicenseState;
use Magento\Framework\Notification\MessageInterface;

abstract class AbstractLicenseMessage implements MessageInterface
{
    /**
     * @param LicenseState $licenseState
     */
    public function __construct(
        protected readonly LicenseState $licenseState
    ) {}

    /**
     * @return string
     */
    public function getIdentity(): string
    {
        return static::IDENTITY;
    }

    /**
     * @return int
     */
    public function getSeverity(): int
    {
        return self::SEVERITY_MAJOR;
    }
}
