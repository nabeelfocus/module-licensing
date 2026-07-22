<?php
declare(strict_types=1);

/**
 * Copyright © Focus. All rights reserved.
 * @author Focus Team
 * @package Focus_Licensing
 */

namespace Focus\Licensing\ViewModel\Dashboard;

use Focus\Licensing\Model\Config;
use Focus\Licensing\Model\LicenseCacheManager;
use Magento\Cron\Model\ResourceModel\Schedule\CollectionFactory as ScheduleCollectionFactory;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Cron\Model\Schedule;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * Presentation-side interpretation of the cached signed license state.
 *
 * Read-only: everything here derives from LicenseCacheManager::read() (which
 * verifies the Ed25519 signature) plus configuration. No licensing decision
 * is made here — LicenseGuard remains the only authority. This class only
 * translates state into what the dashboard displays.
 */
class LicenseState implements ArgumentInterface
{
    public const STATUS_NOT_CONFIGURED = 'not_configured';
    public const STATUS_NOT_ACTIVATED  = 'not_activated';
    public const STATUS_ACTIVE         = 'active';
    public const STATUS_EXPIRING       = 'expiring';
    public const STATUS_EXPIRED        = 'expired';
    public const STATUS_SUSPENDED      = 'suspended';
    public const STATUS_INVALID        = 'invalid';
    public const STATUS_VALIDATION_REQUIRED = 'validation_required';

    private ?array $state = null;
    private bool $stateLoaded = false;

    /**
     * @param LicenseCacheManager $cacheManager
     * @param Config $config
     * @param ScheduleCollectionFactory $scheduleCollectionFactory
     * @param TimezoneInterface $timezone
     * @param ProductMetadataInterface $productMetadata
     */
    public function __construct(
        private readonly LicenseCacheManager $cacheManager,
        private readonly Config $config,
        private readonly ScheduleCollectionFactory $scheduleCollectionFactory,
        private readonly TimezoneInterface $timezone,
        private readonly ProductMetadataInterface $productMetadata
    ) {}

    /**
     * Verified cached state, or null when absent/invalid.
     */
    public function getState(): ?array
    {
        if (!$this->stateLoaded) {
            $this->state = $this->cacheManager->read();
            $this->stateLoaded = true;
        }

        return $this->state;
    }

    /**
     * @return bool
     */
    public function isKeyConfigured(): bool
    {
        return $this->config->getGlobalLicenseKey() !== '';
    }

    /**
     * License key with the middle masked — enough to recognise, not to copy.
     */
    public function getMaskedKey(): string
    {
        $key = $this->config->getGlobalLicenseKey();
        if ($key === '') {
            return '';
        }
        if (strlen($key) <= 14) {
            return substr($key, 0, 4) . '••••';
        }

        return substr($key, 0, 8) . '-••••-••••-' . substr($key, -4);
    }

    /**
     * One of the STATUS_* constants — drives badge, hero copy and card states.
     */
    public function getStatus(): string
    {
        if (!$this->isKeyConfigured()) {
            return self::STATUS_NOT_CONFIGURED;
        }

        $state = $this->getState();
        if ($state === null) {
            return self::STATUS_NOT_ACTIVATED;
        }

        $success = ($state['success'] ?? false) === true || ($state['status'] ?? '') === 'active';
        if (!$success) {
            return match ($state['code'] ?? '') {
                'BL-4031' => self::STATUS_EXPIRED,
                'BL-4030' => self::STATUS_SUSPENDED,
                default   => self::STATUS_INVALID,
            };
        }

        // Signed state says active — apply presentation-level freshness checks
        $days = $this->getDaysRemaining();
        if ($days !== null && $days <= 0) {
            return self::STATUS_EXPIRED;
        }

        if ($this->getCacheAgeSeconds() > Config::OFFLINE_GRACE_SECONDS) {
            return self::STATUS_VALIDATION_REQUIRED;
        }

        if ($days !== null && $days <= Config::EXPIRY_WARNING_DAYS) {
            return self::STATUS_EXPIRING;
        }

        return self::STATUS_ACTIVE;
    }

    /**
     * @return string
     */
    public function getStatusLabel(): string
    {
        return match ($this->getStatus()) {
            self::STATUS_NOT_CONFIGURED => (string) __('No License Key'),
            self::STATUS_NOT_ACTIVATED  => (string) __('Activation Required'),
            self::STATUS_ACTIVE         => (string) __('Active'),
            self::STATUS_EXPIRING       => (string) __('Active — Renewal Due'),
            self::STATUS_EXPIRED        => (string) __('Expired'),
            self::STATUS_SUSPENDED      => (string) __('Suspended'),
            self::STATUS_VALIDATION_REQUIRED => (string) __('Validation Required'),
            default                     => (string) __('Invalid'),
        };
    }

    /**
     * Short uppercase token for the status pill next to the headline.
     *
     * getStatusLabel() is a sentence ("Active — Renewal Due") and reads badly
     * shouted inside a pill, so the pill gets its own one-word form.
     */
    public function getStatusBadgeLabel(): string
    {
        return match ($this->getStatus()) {
            self::STATUS_NOT_CONFIGURED => (string) __('NO KEY'),
            self::STATUS_NOT_ACTIVATED  => (string) __('NOT ACTIVATED'),
            self::STATUS_ACTIVE         => (string) __('ACTIVE'),
            self::STATUS_EXPIRING       => (string) __('RENEWAL DUE'),
            self::STATUS_EXPIRED        => (string) __('EXPIRED'),
            self::STATUS_SUSPENDED      => (string) __('SUSPENDED'),
            self::STATUS_VALIDATION_REQUIRED => (string) __('VALIDATION DUE'),
            default                     => (string) __('INVALID'),
        };
    }

    /**
     * Severity bucket for the status badge: ok | warn | crit | neutral.
     */
    public function getStatusSeverity(): string
    {
        return match ($this->getStatus()) {
            self::STATUS_ACTIVE => 'ok',
            self::STATUS_EXPIRING, self::STATUS_NOT_ACTIVATED,
            self::STATUS_VALIDATION_REQUIRED => 'warn',
            self::STATUS_NOT_CONFIGURED => 'neutral',
            default => 'crit',
        };
    }

    /**
     * Friendly one-liner under the status: problem → reason → action.
     */
    public function getStatusDescription(): string
    {
        $state = $this->getState();

        return match ($this->getStatus()) {
            self::STATUS_NOT_CONFIGURED => (string) __('Enter your Focus license key below and click Save Config to get started.'),
            self::STATUS_NOT_ACTIVATED  => (string) __('A license key is configured but this store has not been activated yet. Click Activate License.'),
            self::STATUS_ACTIVE         => (string) __('All licensed modules are operating normally. Last validated %1.', $this->formatDate($state['issued_at'] ?? null, true)),
            self::STATUS_EXPIRING       => (string) __('Your license expires on %1 (%2 days). Contact Focus to renew before modules switch to restricted mode.', $this->formatDate($state['expires_at'] ?? null), (string) $this->getDaysRemaining()),
            self::STATUS_EXPIRED        => (string) __('Your license expired on %1. Contact Focus to renew — commercial modules are in restricted mode.', $this->formatDate($state['expires_at'] ?? null)),
            self::STATUS_SUSPENDED      => (string) __('This license has been suspended. Please contact Focus support — commercial modules are in restricted mode.'),
            self::STATUS_VALIDATION_REQUIRED => (string) __('The license could not be re-validated for more than %1 days and the offline grace period has ended. Check connectivity and click Validate Now.', (string) round(Config::OFFLINE_GRACE_SECONDS / 86400)),
            default => (string) __('The license server rejected this license (%1). Verify the key below or contact Focus support.', (string) ($state['code'] ?? 'unknown')),
        };
    }

    /**
     * @return ?string
     */
    public function getPlan(): ?string
    {
        $plan = $this->getState()['plan'] ?? null;

        return is_string($plan) && $plan !== '' ? $plan : null;
    }

    /**
     * @return int
     */
    public function getRevision(): int
    {
        return (int) ($this->getState()['license_revision'] ?? 0);
    }

    /**
     * @return ?string
     */
    public function getExpiresAt(): ?string
    {
        $v = $this->getState()['expires_at'] ?? null;

        return is_string($v) && $v !== '' ? $v : null;
    }

    /**
     * Whole days until expiry; null for perpetual/unknown.
     */
    public function getDaysRemaining(): ?int
    {
        $expiresAt = $this->getExpiresAt();
        if ($expiresAt === null) {
            return null;
        }

        return (int) ceil((strtotime($expiresAt) - time()) / 86400);
    }

    /**
     * How far through the license term we are, for the expiry meter (0–100,
     * where 100 = a full year still ahead).
     */
    public function getExpiryPercent(): int
    {
        $days = $this->getDaysRemaining();
        if ($days === null) {
            return 100;
        }

        return (int) max(2, min(100, round($days / 365 * 100)));
    }

    /**
     * Production/IP domain slots this licence permits. 0 = not reported by the
     * server (older server, or an error response).
     */
    public function getMaxDomains(): int
    {
        return (int) ($this->getState()['max_domains'] ?? 0);
    }

    /**
     * Renewal page URL, or '' when the merchant should not be offered one.
     */
    public function getRenewalUrl(): string
    {
        return $this->config->getRenewalUrl();
    }

    /**
     * mailto: link for the Contact Support button, pre-filled with the
     * diagnostics support always has to ask for. Returns '' when no support
     * address is configured.
     *
     * The licence key is deliberately sent masked — enough to identify the
     * licence, never enough to use it.
     */
    public function getSupportMailto(): string
    {
        $email = $this->config->getSupportEmail();
        if ($email === '') {
            return '';
        }

        $state = $this->getState();
        $body = implode("\n", [
            (string) __('Describe the problem here.'),
            '',
            '--- ' . __('Licence diagnostics') . ' ---',
            __('Status') . ': ' . $this->getStatusLabel(),
            __('Licence key') . ': ' . $this->getMaskedKey(),
            __('Revision') . ': ' . $this->getRevision(),
            __('Domain') . ': ' . $this->getDomain(),
            __('Last result') . ': ' . (string) ($state['code'] ?? '—'),
            __('Last validated') . ': ' . $this->formatDate($this->getLastSyncedAt(), true),
            __('Expires') . ': ' . ($this->getExpiresAt() !== null ? $this->formatDate($this->getExpiresAt()) : (string) __('Lifetime')),
            __('Magento') . ': ' . $this->productMetadata->getVersion(),
            __('PHP') . ': ' . PHP_VERSION,
        ]);

        return 'mailto:' . rawurlencode($email)
            . '?subject=' . rawurlencode((string) __('Focus licence support — %1', $this->getDomain()))
            . '&body=' . rawurlencode($body);
    }

    /**
     * @return string
     */
    public function getDomain(): string
    {
        return (string) ($this->getState()['domain'] ?? '');
    }

    /**
     * @return string
     */
    public function getDomainType(): string
    {
        return (string) ($this->getState()['domain_type'] ?? '');
    }

    /**
     * @return ?string
     */
    public function getLastSyncedAt(): ?string
    {
        $v = $this->getState()['issued_at'] ?? null;

        return is_string($v) && $v !== '' ? $v : null;
    }

    /**
     * @return int
     */
    public function getCacheAgeSeconds(): int
    {
        $issuedAt = $this->getLastSyncedAt();
        if ($issuedAt === null) {
            return PHP_INT_MAX;
        }

        return max(0, time() - (int) strtotime($issuedAt));
    }

    /**
     * @return int
     */
    public function getGraceTotalDays(): int
    {
        return (int) round(Config::OFFLINE_GRACE_SECONDS / 86400);
    }

    /**
     * Days of offline grace left before modules restrict when the server is
     * unreachable. Full when the cache is fresher than the TTL.
     */
    public function getGraceRemainingDays(): int
    {
        if ($this->getState() === null) {
            return 0;
        }
        $remaining = Config::OFFLINE_GRACE_SECONDS - $this->getCacheAgeSeconds();

        return (int) max(0, floor($remaining / 86400));
    }

    /**
     * Next scheduled run of the revalidation cron, or null when not scheduled.
     */
    public function getNextValidationAt(): ?string
    {
        try {
            $collection = $this->scheduleCollectionFactory->create();
            $collection->addFieldToFilter('job_code', 'focus_licensing_revalidate')
                ->addFieldToFilter('status', Schedule::STATUS_PENDING)
                ->setOrder('scheduled_at', 'ASC')
                ->setPageSize(1);

            /** @var Schedule $schedule */
            $schedule = $collection->getFirstItem();
            $value = (string) $schedule->getScheduledAt();

            return $value !== '' ? $value : null;
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * Format a datetime for display in the STORE's configured timezone.
     *
     * Everything the license server stores and signs is UTC (issued_at,
     * expires_at, cron schedule). Admins read the dashboard in local time, so
     * the value is converted through Magento's timezone service and labelled
     * with the real zone abbreviation (e.g. "BST"), never a hard-coded "UTC".
     *
     * @param string|null $utcDateTime UTC datetime string, or null
     * @param bool $withTime Include the time part and timezone label
     * @return string
     */
    public function formatDate(?string $utcDateTime, bool $withTime = false): string
    {
        if (empty($utcDateTime)) {
            return '—';
        }

        try {
            $date = $this->timezone->date(new \DateTime($utcDateTime, new \DateTimeZone('UTC')));
        } catch (\Exception) {
            return '—';
        }

        return $withTime
            ? $date->format('j M Y, H:i') . ' ' . $date->format('T')
            : $date->format('j M Y');
    }
}
