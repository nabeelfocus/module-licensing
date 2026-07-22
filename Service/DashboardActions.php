<?php
declare(strict_types=1);

/**
 * Copyright © Focus. All rights reserved.
 * @author Focus Team
 * @package Focus_Licensing
 */

namespace Focus\Licensing\Service;

use Focus\Licensing\Api\LicenseClientInterface;
use Focus\Licensing\Api\LicenseGuardInterface;
use Focus\Licensing\Api\ModuleDiscoveryInterface;
use Focus\Licensing\Model\ActivityLog;
use Focus\Licensing\Model\Config;
use Focus\Licensing\Model\LicenseCacheManager;

/**
 * Orchestrates the dashboard action buttons. Thin wrapper over existing
 * services — no licensing decisions live here.
 *
 *   validateNow() — force a server round-trip, refresh the cache
 *   refresh()     — drop the cache and re-bootstrap via activate (full
 *                   signed payload + fresh HMAC secret)
 *   deactivate()  — release this store's domain slot and clear local state
 *   releaseDomain() — release a DIFFERENT domain's slot, leaving this store
 *                   running (server migrations, rebuilt staging)
 */
class DashboardActions
{
    /**
     * @param LicenseGuardInterface $guard
     * @param LicenseClientInterface $licenseClient
     * @param LicenseCacheManager $cacheManager
     * @param ModuleDiscoveryInterface $moduleDiscovery
     * @param Config $config
     * @param ActivityLog $activityLog
     */
    public function __construct(
        private readonly LicenseGuardInterface $guard,
        private readonly LicenseClientInterface $licenseClient,
        private readonly LicenseCacheManager $cacheManager,
        private readonly ModuleDiscoveryInterface $moduleDiscovery,
        private readonly Config $config,
        private readonly ActivityLog $activityLog
    ) {}

    /**
     * @return array{success: bool, message: string}
     */
    public function validateNow(): array
    {
        if ($this->config->getGlobalLicenseKey() === '') {
            return ['success' => false, 'message' => (string) __('No license key is configured. Enter it below and click Save Config first.')];
        }

        $isValid = $this->guard->forceRevalidate(null, ActivityLog::SOURCE_MANUAL);
        $state = $this->cacheManager->read();

        if ($isValid) {
            return [
                'success' => true,
                'message' => (string) __(
                    'License synchronized — revision %1, %2 module(s) licensed.',
                    (string) ($state['license_revision'] ?? '?'),
                    (string) count($state['allowed_modules'] ?? [])
                ),
            ];
        }

        if ($state === null) {
            return ['success' => false, 'message' => (string) __('The license server could not be reached. Check the Server URL below and your outbound connectivity, then try again.')];
        }

        return ['success' => false, 'message' => (string) __('The license server rejected this license (%1): %2', (string) ($state['code'] ?? ''), (string) ($state['message'] ?? ''))];
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function refresh(): array
    {
        if ($this->config->getGlobalLicenseKey() === '') {
            return ['success' => false, 'message' => (string) __('No license key is configured. Enter it below and click Save Config first.')];
        }

        // Dropping the cache forces the guard down the activate() bootstrap
        // path, which returns the complete signed payload + a fresh secret.
        $this->cacheManager->clear();
        $isValid = $this->guard->forceRevalidate(null, ActivityLog::SOURCE_MANUAL);

        if ($isValid) {
            $state = $this->cacheManager->read();

            return [
                'success' => true,
                'message' => (string) __(
                    'Fresh license payload downloaded — revision %1.',
                    (string) ($state['license_revision'] ?? '?')
                ),
            ];
        }

        return ['success' => false, 'message' => (string) __('Could not download a fresh license payload. The previous cached state has been cleared — click Activate License once the server is reachable.')];
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function deactivate(): array
    {
        $licenseKey = $this->config->getGlobalLicenseKey();
        if ($licenseKey === '') {
            return ['success' => false, 'message' => (string) __('No license key is configured — nothing to deactivate.')];
        }

        $response = $this->licenseClient->deactivate(
            $licenseKey,
            $this->moduleDiscovery->getInstalledFocusModules()
        );
        $this->cacheManager->clear();
        $this->activityLog->clear();

        if ($response !== null && ($response['success'] ?? false)) {
            return ['success' => true, 'message' => (string) __('Domain released. Commercial modules are now in restricted mode on this store — click Activate License to register again.')];
        }

        return ['success' => true, 'message' => (string) __('Local license state cleared. The server could not confirm the domain release — it will free the slot automatically, or contact Focus support.')];
    }

    /**
     * Release the slot held by another domain on this same licence.
     *
     * The licence server's deactivate endpoint already accepts an arbitrary
     * domain and is authenticated with this licence's own HMAC secret, so the
     * caller can only ever release a domain belonging to the licence it holds.
     * No new endpoint and no new trust are involved.
     *
     * The local cache is deliberately NOT cleared: this store keeps running.
     * That is the whole difference from deactivate().
     *
     * @param string $domain Domain to release, as shown in the domains card
     * @return array{success: bool, message: string}
     */
    public function releaseDomain(string $domain): array
    {
        $domain = trim($domain);
        if ($domain === '') {
            return ['success' => false, 'message' => (string) __('No domain was specified.')];
        }

        $licenseKey = $this->config->getGlobalLicenseKey();
        if ($licenseKey === '') {
            return ['success' => false, 'message' => (string) __('No license key is configured.')];
        }

        $state = $this->cacheManager->read();
        if ($state !== null && strcasecmp($domain, (string) ($state['domain'] ?? '')) === 0) {
            return [
                'success' => false,
                'message' => (string) __('That is this store\'s own domain — use Deactivate above to release it.'),
            ];
        }

        $response = $this->licenseClient->deactivate(
            $licenseKey,
            $this->moduleDiscovery->getInstalledFocusModules(),
            $domain
        );

        if ($response === null || ($response['success'] ?? false) !== true) {
            return [
                'success' => false,
                'message' => (string) __('Could not release %1. The license server did not confirm the change — try again, or contact Focus support.', $domain),
            ];
        }

        $this->activityLog->record(
            ActivityLog::SOURCE_RELEASE,
            (string) ($response['code'] ?? 'OK'),
            true,
            (int) ($state['license_revision'] ?? 0),
            count($state['allowed_modules'] ?? []),
            (string) __('Released domain %1', $domain)
        );

        // Refresh so the domains card reflects the freed slot immediately.
        $this->guard->forceRevalidate(null, ActivityLog::SOURCE_RELEASE);

        return ['success' => true, 'message' => (string) __('%1 has been released. Its production slot is now free.', $domain)];
    }
}
