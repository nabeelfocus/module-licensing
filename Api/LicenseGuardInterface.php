<?php
declare(strict_types=1);

/**
 * Copyright © Focus. All rights reserved.
 * @author Focus Team
 * @package Focus_Licensing
 */

namespace Focus\Licensing\Api;

use Focus\Licensing\Model\ActivityLog;

interface LicenseGuardInterface
{
    /**
     * Whether the given module is currently licensed on this store.
     *
     * Returns true  → module may operate normally
     * Returns false → module must enter restricted mode (no-op / notice)
     *
     * @param string $moduleName  e.g. "Focus_KSystem"
     * @return bool
     */
    public function isLicensed(string $moduleName): bool;

    /**
     * Force an immediate server round-trip to validate the global license key.
     * Re-populates the cache and answers from the fresh result.
     * Optionally accepts a license key to use instead of the configured one (used during "Save and Verify" actions).
     *
     * @param string|null $licenseKey
     * @param string|null $source One of ActivityLog::SOURCE_*, or null for a
     *                       silent refresh that records nothing
     * @return bool
     */
    public function forceRevalidate(
        ?string $licenseKey = null,
        ?string $source = ActivityLog::SOURCE_SCHEDULED
    ): bool;
}
