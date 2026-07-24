<?php
declare(strict_types=1);

/**
 * Copyright © Focus. All rights reserved.
 * @author Focus Team
 * @package Focus_Licensing
 */

namespace Focus\Licensing\Model\System\Message;

use Focus\Licensing\ViewModel\Dashboard\LicenseState;

class LicenseInvalid extends AbstractLicenseMessage
{
    public const IDENTITY = 'focus_licensing_invalid';

    /**
     * @return bool
     */
    public function isDisplayed(): bool
    {
        return in_array($this->licenseState->getStatus(), [
            LicenseState::STATUS_EXPIRED,
            LicenseState::STATUS_SUSPENDED,
            LicenseState::STATUS_INVALID,
        ], true);
    }

    /**
     * @return string
     */
    public function getText(): string
    {
        return (string) __('Focus License: %1', $this->licenseState->getStatusDescription());
    }

    /**
     * @return int
     */
    public function getSeverity(): int
    {
        return self::SEVERITY_CRITICAL;
    }
}
