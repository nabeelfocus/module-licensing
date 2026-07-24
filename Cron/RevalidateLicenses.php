<?php
declare(strict_types=1);

/**
 * Copyright © Focus. All rights reserved.
 * @author Focus Team
 * @package Focus_Licensing
 */

namespace Focus\Licensing\Cron;

use Focus\Licensing\Model\ActivityLog;
use Focus\Licensing\Api\LicenseGuardInterface;
use Focus\Licensing\Logger\Logger;

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
            $isValid = $this->licenseGuard->forceRevalidate(null, ActivityLog::SOURCE_SCHEDULED);
            
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
