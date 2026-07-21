<?php
declare(strict_types=1);

/**
 * Copyright © Focus. All rights reserved.
 * @author Focus Team
 * @package Focus_Licensing
 */

namespace Focus\Licensing\ViewModel\Dashboard;

use Focus\Licensing\Model\Config;
use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * Diagnostics card data, grouped Connection / License / Cache.
 * Static values only — live probe results come from the Test Connection
 * button (ConnectionTester) and render client-side.
 */
class Diagnostics implements ArgumentInterface
{
    /**
     * @param LicenseState $licenseState
     * @param Config $config
     */
    public function __construct(
        private readonly LicenseState $licenseState,
        private readonly Config $config
    ) {}

    /**
     * @return array<string, array<int, array{label: string, value: string, code?: bool}>>
     */
    public function getGroups(): array
    {
        $state = $this->licenseState->getState();

        return [
            (string) __('Connection') => [
                ['label' => (string) __('Server URL'), 'value' => $this->config->getServerUrl() ?: '—', 'code' => true],
                ['label' => (string) __('API status'), 'value' => (string) __('Run Test Connection for a live check')],
            ],
            (string) __('License') => [
                ['label' => (string) __('Revision'), 'value' => $state !== null ? (string) $this->licenseState->getRevision() : '—'],
                ['label' => (string) __('Signature'), 'value' => $state !== null
                    ? (string) __('Valid (verified on every read)')
                    : (string) __('No verified state cached')],
                ['label' => (string) __('Signing Key ID'), 'value' => (string) ($state['key_id'] ?? '—'), 'code' => true],
                ['label' => (string) __('Expires'), 'value' => $this->licenseState->getExpiresAt() !== null
                    ? $this->licenseState->formatDate($this->licenseState->getExpiresAt())
                    : ($state !== null ? (string) __('Lifetime') : '—')],
            ],
            (string) __('Cache') => [
                ['label' => (string) __('Last Sync'), 'value' => $this->licenseState->formatDate($this->licenseState->getLastSyncedAt(), true)],
                ['label' => (string) __('Cache Age'), 'value' => $this->getCacheAgeLabel()],
                ['label' => (string) __('Offline Grace'), 'value' => $state !== null
                    ? (string) __('%1 of %2 days remaining', (string) $this->licenseState->getGraceRemainingDays(), (string) $this->licenseState->getGraceTotalDays())
                    : '—'],
            ],
        ];
    }

    /**
     * @return string
     */
    private function getCacheAgeLabel(): string
    {
        if ($this->licenseState->getState() === null) {
            return '—';
        }
        $seconds = $this->licenseState->getCacheAgeSeconds();

        if ($seconds < 3600) {
            return (string) __('%1 minute(s)', (string) max(1, (int) floor($seconds / 60)));
        }
        if ($seconds < 86400) {
            return (string) __('%1 hour(s)', (string) floor($seconds / 3600));
        }

        return (string) __('%1 day(s)', (string) floor($seconds / 86400));
    }
}
