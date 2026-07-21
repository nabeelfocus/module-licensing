<?php
declare(strict_types=1);

/**
 * Copyright © Focus. All rights reserved.
 * @author Focus Team
 * @package Focus_Licensing
 */

namespace Focus\Licensing\Cron;

use Focus\Licensing\Api\LicenseGuardInterface;
use Focus\Licensing\Logger\Logger;

/**
 * Daily revalidation cron: refresh the global license state.
 *
 * Schedule: once per day (configurable). Runs off-peak to avoid contention.
 * Offline grace logic is applied in LicenseGuard, not here. This cron's job
 * is simply to attempt a refresh and store the result.
 */
class RevalidateLicenses
{
    /**
     * @param LicenseGuardInterface $licenseGuard
     * @param Logger $logger
     */
    public function __construct(
        private readonly LicenseGuardInterface $licenseGuard,
        private readonly Logger $logger
    ) {}

    /**
     * @return void
     */
    public function execute(): void
    {
        $this->logger->info('Focus_Licensing: RevalidateLicenses cron started.');

        try {
            $isValid = $this->licenseGuard->forceRevalidate();
            
            if ($isValid) {
                $this->logger->info('Focus_Licensing: successfully revalidated global license state.');
            } else {
                $this->logger->warning('Focus_Licensing: global license revalidation failed or returned invalid status.');
            }
        } catch (\Exception $e) {
            $this->logger->error('Focus_Licensing: exception during global license revalidation', [
                'exception' => $e->getMessage(),
            ]);
        }

        $this->logger->info('Focus_Licensing: RevalidateLicenses cron finished.');
    }
}
