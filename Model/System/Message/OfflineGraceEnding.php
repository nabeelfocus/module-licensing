<?php
declare(strict_types=1);

namespace Focus\Licensing\Model\System\Message;

use Focus\Licensing\ViewModel\Dashboard\LicenseState;

/**
 * The server has been unreachable for a while and the offline grace window is
 * nearly used up — modules restrict when it runs out.
 */
class OfflineGraceEnding extends AbstractLicenseMessage
{
    public const IDENTITY = 'focus_licensing_grace_ending';

    private const WARN_AT_DAYS_LEFT = 5;

    public function isDisplayed(): bool
    {
        if (!in_array($this->licenseState->getStatus(), [
            LicenseState::STATUS_ACTIVE,
            LicenseState::STATUS_EXPIRING,
        ], true)) {
            return false;
        }

        // Only relevant once the cache is well past its normal refresh window
        return $this->licenseState->getCacheAgeSeconds() > 2 * 86400
            && $this->licenseState->getGraceRemainingDays() <= self::WARN_AT_DAYS_LEFT;
    }

    public function getText(): string
    {
        return (string) __(
            'Focus License: the license server has not been reachable since %1. Running on the cached license — offline grace remaining: %2 day(s). Check connectivity and use Validate Now on the license dashboard.',
            $this->licenseState->formatDate($this->licenseState->getLastSyncedAt()),
            (string) $this->licenseState->getGraceRemainingDays()
        );
    }

    public function getSeverity(): int
    {
        return self::SEVERITY_CRITICAL;
    }
}
