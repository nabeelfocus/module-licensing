<?php
declare(strict_types=1);

/**
 * Copyright © Focus. All rights reserved.
 * @author Focus Team
 * @package Focus_Licensing
 */

namespace Focus\Licensing\ViewModel\Dashboard;

use Focus\Licensing\Api\ModuleDiscoveryInterface;
use Focus\Licensing\Model\ActivityLog;
use Magento\Framework\View\Element\Block\ArgumentInterface;

class Activity implements ArgumentInterface
{
    /**
     * @param ActivityLog $activityLog
     * @param LicenseState $licenseState
     * @param ModuleDiscoveryInterface $moduleDiscovery
     */
    public function __construct(
        private readonly ActivityLog $activityLog,
        private readonly LicenseState $licenseState,
        private readonly ModuleDiscoveryInterface $moduleDiscovery
    ) {}

    /**
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
                'note'     => $this->sanitizeNote((string) ($entry['note'] ?? '')),
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
     * Transient codes read as a warning rather than an alarm — the licence
     * was kept and the check retried, which is the system working as designed.
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

    /**
     * Entries recorded before a module-name filter existed can still name a
     * module this store never installed. Rather than leave that stale text
     * on screen indefinitely, any note naming a module absent from today's
     * install becomes a generic placeholder — the row (time, source, result)
     * stays, only the description is replaced.
     *
     * @param string $note
     * @return string
     */
    private function sanitizeNote(string $note): string
    {
        if (!preg_match_all('/Focus_[A-Za-z0-9]+/', $note, $matches)) {
            return $note;
        }

        $installed = array_flip($this->moduleDiscovery->getInstalledFocusModules());
        foreach ($matches[0] as $module) {
            if (!isset($installed[$module])) {
                return (string) __('Licence updated.');
            }
        }

        return $note;
    }
}
