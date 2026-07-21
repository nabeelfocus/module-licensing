<?php
declare(strict_types=1);

namespace Focus\Licensing\Model\System\Message;

use Focus\Licensing\ViewModel\Dashboard\LicenseState;

/**
 * "License expires in N days" — shown inside the 30-day renewal window.
 */
class LicenseExpiring extends AbstractLicenseMessage
{
    public const IDENTITY = 'focus_licensing_expiring';

    public function isDisplayed(): bool
    {
        return $this->licenseState->getStatus() === LicenseState::STATUS_EXPIRING;
    }

    public function getText(): string
    {
        return (string) __(
            'Focus License: your license expires on %1 (%2 days). Contact Focus to renew — commercial modules switch to restricted mode after expiry.',
            $this->licenseState->formatDate($this->licenseState->getExpiresAt()),
            (string) max(0, (int) $this->licenseState->getDaysRemaining())
        );
    }
}
