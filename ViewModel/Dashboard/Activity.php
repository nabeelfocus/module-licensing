<?php
declare(strict_types=1);

/**
 * Copyright © Focus. All rights reserved.
 * @author Focus Team
 * @package Focus_Licensing
 */

namespace Focus\Licensing\ViewModel\Dashboard;

use Focus\Licensing\Model\ActivityLog;
use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * Presents the local licence-check history for the dashboard.
 *
 * Read-only, and deliberately so: the history exists to explain what happened,
 * never to influence what happens next.
 */
class Activity implements ArgumentInterface
{
    /**
     * @param ActivityLog $activityLog
     * @param LicenseState $licenseState
     */
    public function __construct(
        private readonly ActivityLog $activityLog,
        private readonly LicenseState $licenseState
    ) {}

    /**
     * Display-ready rows, newest first.
     *
     * @return array<int, array{when: string, source: string, code: string, severity: string, note: string}>
     */
    public function getRows(): array
    {
        $rows = [];

        foreach ($this->activityLog->getEntries() as $entry) {
            $code = (string) ($entry['code'] ?? '');
            $rows[] = [
                'when'     => $this->licenseState->formatDate((string) ($entry['at'] ?? ''), true),
                'source'   => $this->sourceLabel((string) ($entry['source'] ?? '')),
                'code'     => $code,
                'severity' => $this->severity($code, (bool) ($entry['success'] ?? false)),
                'note'     => (string) ($entry['note'] ?? ''),
            ];
        }

        return $rows;
    }

    /**
     * @return bool
     */
    public function hasRows(): bool
    {
        return $this->activityLog->getEntries() !== [];
    }

    /**
     * @param string $source
     * @return string
     */
    private function sourceLabel(string $source): string
    {
        return match ($source) {
            ActivityLog::SOURCE_MANUAL      => (string) __('Manual check'),
            ActivityLog::SOURCE_CONFIG_SAVE => (string) __('Configuration saved'),
            ActivityLog::SOURCE_RELEASE     => (string) __('Domain released'),
            default                         => (string) __('Scheduled'),
        };
    }

    /**
     * Colour bucket for the result badge.
     *
     * A failed check is not automatically critical: the transient codes are the
     * system working as designed — the licence was kept and the check retried —
     * so they read as a warning, not an alarm.
     *
     * @param string $code
     * @param bool $success
     * @return string
     */
    private function severity(string $code, bool $success): string
    {
        if ($success) {
            return 'ok';
        }

        return in_array($code, ['BL-4290', 'BL-5000', 'BL-4011', 'UNREACHABLE'], true) ? 'warn' : 'crit';
    }
}
