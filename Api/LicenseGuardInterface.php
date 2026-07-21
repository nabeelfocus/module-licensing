<?php
declare(strict_types=1);

/**
 * Copyright © Focus. All rights reserved.
 * @author Focus Team
 * @package Focus_Licensing
 */

namespace Focus\Licensing\Api;

/**
 * The ONE question every commercial Focus module asks.
 *
 * Usage in a commercial module:
 *   if (!$this->guard->isLicensed('Focus_KSystem')) {
 *       return;
 *   }
 *
 * The guard is:
 *   - In-memory cached per-request (first call hits StateStore, subsequent calls are free)
 *   - Zero network I/O in web requests — all validation happens in the cron
 *   - Non-throwing — if the license state is unknown/corrupt the guard returns false
 */
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
     */
    public function forceRevalidate(?string $licenseKey = null): bool;
}
