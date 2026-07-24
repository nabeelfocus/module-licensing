<?php
declare(strict_types=1);

/**
 * Copyright © Focus. All rights reserved.
 * @author Focus Team
 * @package Focus_Licensing
 */

namespace Focus\Licensing\Model\System\Message;

use Focus\Licensing\ViewModel\Dashboard\LicenseState;

class ValidationStale extends AbstractLicenseMessage
{
    public const IDENTITY = 'focus_licensing_validation_stale';

    private const STALE_AFTER_SECONDS = 3 * 86400;

    /**
     * @return bool
     */
    public function isDisplayed(): bool
    {
        if (!in_array($this->licenseState->getStatus(), [
            LicenseState::STATUS_ACTIVE,
            LicenseState::STATUS_EXPIRING,
        ], true)) {
            return false;
        }

        return $this->licenseState->getCacheAgeSeconds() > self::STALE_AFTER_SECONDS
            && $this->licenseState->getGraceRemainingDays() > 5;
    }

    /**
     * @return string
     */
    public function getText(): string
    {
        return (string) __(
            'Focus License: the last successful validation was %1. The daily validation cron may not be running — check your cron configuration, or click Validate Now on the license dashboard.',
            $this->licenseState->formatDate($this->licenseState->getLastSyncedAt(), true)
        );
    }
}
