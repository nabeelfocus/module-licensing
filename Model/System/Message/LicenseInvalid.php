<?php
declare(strict_types=1);

namespace Focus\Licensing\Model\System\Message;

use Focus\Licensing\ViewModel\Dashboard\LicenseState;

/**
 * License is expired / suspended / rejected — modules are restricted.
 */
class LicenseInvalid extends AbstractLicenseMessage
{
    public const IDENTITY = 'focus_licensing_invalid';

    public function isDisplayed(): bool
    {
        return in_array($this->licenseState->getStatus(), [
            LicenseState::STATUS_EXPIRED,
            LicenseState::STATUS_SUSPENDED,
            LicenseState::STATUS_INVALID,
        ], true);
    }

    public function getText(): string
    {
        return (string) __('Focus License: %1', $this->licenseState->getStatusDescription());
    }

    public function getSeverity(): int
    {
        return self::SEVERITY_CRITICAL;
    }
}
