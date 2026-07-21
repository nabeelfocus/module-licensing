<?php
declare(strict_types=1);

namespace Focus\Licensing\Service;

use Focus\Licensing\Api\LicenseClientInterface;
use Focus\Licensing\Api\LicenseGuardInterface;
use Focus\Licensing\Api\ModuleDiscoveryInterface;
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
 */
class DashboardActions
{
    public function __construct(
        private readonly LicenseGuardInterface $guard,
        private readonly LicenseClientInterface $licenseClient,
        private readonly LicenseCacheManager $cacheManager,
        private readonly ModuleDiscoveryInterface $moduleDiscovery,
        private readonly Config $config
    ) {}

    /**
     * @return array{success: bool, message: string}
     */
    public function validateNow(): array
    {
        if ($this->config->getGlobalLicenseKey() === '') {
            return ['success' => false, 'message' => (string) __('No license key is configured. Enter it below and click Save Config first.')];
        }

        $isValid = $this->guard->forceRevalidate();
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
        $isValid = $this->guard->forceRevalidate();

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

        if ($response !== null && ($response['success'] ?? false)) {
            return ['success' => true, 'message' => (string) __('Domain released. Commercial modules are now in restricted mode on this store — click Activate License to register again.')];
        }

        return ['success' => true, 'message' => (string) __('Local license state cleared. The server could not confirm the domain release — it will free the slot automatically, or contact Focus support.')];
    }
}
