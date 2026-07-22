<?php
declare(strict_types=1);

/**
 * Copyright © Focus. All rights reserved.
 * @author Focus Team
 * @package Focus_Licensing
 */

namespace Focus\Licensing\Model;

use Focus\Licensing\Logger\Logger;
use Magento\Framework\FlagManager;

/**
 * A short, customer-facing history of licence checks.
 *
 * The licence server keeps the authoritative audit trail for 180 days, but the
 * customer never sees it — from their side a licence problem is invisible until
 * a module stops working. This records the last few checks locally so the
 * dashboard can show what happened and when.
 *
 * Stored in the `flag` table rather than the cache, for the same reason the
 * signed licence state is: a cache flush must not erase the evidence an admin
 * is about to read. It is a rolling list, so it cannot grow without bound and
 * needs no schema, no table and no cleanup cron.
 *
 * Purely informational. Nothing here influences a licensing decision.
 */
class ActivityLog
{
    public const SOURCE_SCHEDULED   = 'scheduled';
    public const SOURCE_MANUAL      = 'manual';
    public const SOURCE_CONFIG_SAVE = 'config_save';
    public const SOURCE_RELEASE     = 'release';

    /** How many entries to keep. Enough to show a pattern, small enough to stay cheap. */
    private const MAX_ENTRIES = 10;

    private const FLAG_CODE = 'focus_licensing_activity_global';

    /**
     * Deliberately depends on nothing beyond the flag store and the module's
     * own logger. Magento's DateTime service pulls in Timezone -> StoreManager
     * -> EventManager, and EventManager is itself intercepted by the licensing
     * observer guard — injecting it here closes a circular dependency back
     * onto LicenseGuard. gmdate() gives the same UTC string with no graph.
     *
     * @param FlagManager $flagManager
     * @param Logger $logger
     */
    public function __construct(
        private readonly FlagManager $flagManager,
        private readonly Logger $logger
    ) {}

    /**
     * Append one check to the history, dropping the oldest beyond the limit.
     *
     * Never throws: this is a display aid, and failing to record it must not
     * disturb the validation that just happened.
     *
     * @param string $source One of the SOURCE_* constants
     * @param string $code Result code from the server, e.g. OK or BL-4290
     * @param bool $success
     * @param int $revision Licence revision reported by the server
     * @param int $allowedCount Number of modules the licence covers
     * @param string $note Short human explanation of what changed
     * @return void
     */
    public function record(
        string $source,
        string $code,
        bool $success,
        int $revision = 0,
        int $allowedCount = 0,
        string $note = ''
    ): void {
        try {
            $entries = $this->getEntries();

            array_unshift($entries, [
                'at'            => gmdate('Y-m-d H:i:s'),
                'source'        => $source,
                'code'          => $code !== '' ? $code : 'OK',
                'success'       => $success,
                'revision'      => $revision,
                'allowed_count' => $allowedCount,
                'note'          => $note,
            ]);

            $this->flagManager->saveFlag(
                self::FLAG_CODE,
                json_encode(array_slice($entries, 0, self::MAX_ENTRIES))
            );
        } catch (\Exception $e) {
            $this->logger->warning('Focus_Licensing: could not record licence activity', [
                'exception' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Recorded checks, newest first.
     *
     * @return array<int, array{at: string, source: string, code: string, success: bool, revision: int, allowed_count: int, note: string}>
     */
    public function getEntries(): array
    {
        try {
            $raw = $this->flagManager->getFlagData(self::FLAG_CODE);
            if (!is_string($raw) || $raw === '') {
                return [];
            }

            $decoded = json_decode($raw, true);

            return is_array($decoded) ? $decoded : [];
        } catch (\Exception) {
            return [];
        }
    }

    /**
     * Forget the history — used when the store is deactivated, so a re-activated
     * store does not show the previous installation's checks.
     *
     * @return void
     */
    public function clear(): void
    {
        try {
            $this->flagManager->deleteFlag(self::FLAG_CODE);
        } catch (\Exception) {
            // nothing to do — an unreadable history is not worth an error
        }
    }
}
