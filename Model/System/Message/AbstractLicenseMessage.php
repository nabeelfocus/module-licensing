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

/**
 * Base for the licensing system messages (admin bell / message list).
 *
 * System messages replace the old per-login flash notices: Magento shows each
 * identity exactly once until the condition clears, so the customer never
 * gets duplicate warnings for the same issue.
 */
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
