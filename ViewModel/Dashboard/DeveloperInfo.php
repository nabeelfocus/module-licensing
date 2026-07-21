<?php
declare(strict_types=1);

/**
 * Copyright © Focus. All rights reserved.
 * @author Focus Team
 * @package Focus_Licensing
 */

namespace Focus\Licensing\ViewModel\Dashboard;

use Magento\Framework\App\State as AppState;
use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * Developer-mode-only diagnostics: the raw signed payload and verification
 * metadata. Hidden entirely in production/default mode.
 *
 * Secrets are ALWAYS redacted — the cached payload carries the per-license
 * HMAC secret, which must never reach a screen.
 */
class DeveloperInfo implements ArgumentInterface
{
    private const REDACTED_KEYS = ['secret'];

    /**
     * @param LicenseState $licenseState
     * @param AppState $appState
     */
    public function __construct(
        private readonly LicenseState $licenseState,
        private readonly AppState $appState
    ) {}

    /**
     * @return bool
     */
    public function isVisible(): bool
    {
        try {
            return $this->appState->getMode() === AppState::MODE_DEVELOPER
                && $this->licenseState->getState() !== null;
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * Pretty-printed raw payload with secrets removed.
     */
    public function getRedactedPayloadJson(): string
    {
        $state = $this->licenseState->getState() ?? [];
        foreach (self::REDACTED_KEYS as $key) {
            if (array_key_exists($key, $state)) {
                $state[$key] = '*** redacted ***';
            }
        }

        return (string) json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @return array<string, string>
     */
    public function getFacts(): array
    {
        $state = $this->licenseState->getState() ?? [];

        return [
            (string) __('Signature')       => substr((string) ($state['signature'] ?? ''), 0, 24) . '…',
            (string) __('Key ID')          => (string) ($state['key_id'] ?? '—'),
            (string) __('Request ID')      => (string) ($state['request_id'] ?? '—'),
            (string) __('Revision')        => (string) ($state['license_revision'] ?? '—'),
            (string) __('Server issued at') => (string) ($state['issued_at'] ?? '—'),
            (string) __('Cache age')       => $this->licenseState->getCacheAgeSeconds() . 's',
            (string) __('Check again in')  => (string) ($state['check_again_in'] ?? '—') . 's',
        ];
    }
}
