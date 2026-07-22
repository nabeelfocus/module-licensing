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
use Focus\Licensing\Logger\Logger;

/**
 * The guard: answers isLicensed() for every commercial module.
 *
 * Decision flow (per-request memory cache → global StateStore → offline grace rules):
 *
 *   1. Per-request in-memory cache
 *   2. LicenseCacheManager::read() (global state)
 *      - signature valid & module in allowed_modules & state age < TTL   → ALLOW
 *      - signature valid & module not in allowed_modules                  → DENY
 *      - state age >= TTL but < offline grace                             → ALLOW (stale-graced)
 *      - beyond offline grace / corrupt / absent                          → DENY
 *   3. forceRevalidate() → calls server now, refreshes cache for ALL modules
 */
class LicenseGuard implements LicenseGuardInterface
{
    /** Per-request memory cache: module_name → bool */
    private array $memoryCache = [];

    /**
     * @param LicenseCacheManager $cacheManager
     * @param LicenseClientInterface $licenseClient
     * @param ModuleDiscoveryInterface $moduleDiscovery
     * @param Config $config
     * @param Logger $logger
     * @param ActivityLog $activityLog
     */
    public function __construct(
        private readonly LicenseCacheManager $cacheManager,
        private readonly LicenseClientInterface $licenseClient,
        private readonly ModuleDiscoveryInterface $moduleDiscovery,
        private readonly Config $config,
        private readonly Logger $logger,
        private readonly ActivityLog $activityLog
    ) {}

    /**
     * @param string $moduleName
     * @return bool
     */
    public function isLicensed(string $moduleName): bool
    {
        if (array_key_exists($moduleName, $this->memoryCache)) {
            return $this->memoryCache[$moduleName];
        }

        $result = $this->evaluateFromCache($moduleName);
        $this->memoryCache[$moduleName] = $result;

        return $result;
    }

    /**
     * @param ?string $licenseKey
     * @return bool
     */
    public function forceRevalidate(
        ?string $licenseKey = null,
        string $source = ActivityLog::SOURCE_SCHEDULED
    ): bool {
        $licenseKey = $licenseKey ?: $this->config->getGlobalLicenseKey();
        if (empty($licenseKey)) {
            $this->cacheManager->clear();
            $this->memoryCache = [];
            return false;
        }

        $installedModules = $this->moduleDiscovery->getInstalledFocusModules();
        $previousState = $this->cacheManager->read();
        $hasState = $previousState !== null;

        $response = $hasState
            ? $this->licenseClient->validate($licenseKey, $installedModules)
            : $this->licenseClient->activate($licenseKey, $installedModules);

        if ($response === null) {
            $this->logger->warning('Focus_Licensing: server unreachable during forceRevalidate');
            $this->activityLog->record(
                $source,
                'UNREACHABLE',
                false,
                (int) ($previousState['license_revision'] ?? 0),
                count($previousState['allowed_modules'] ?? []),
                $hasState
                    ? (string) __('Server unreachable — existing licence kept.')
                    : (string) __('Server unreachable.')
            );

            return false;
        }

        if (in_array($response['code'] ?? '', ['BL-4032', 'BL-4010', 'FL-4032', 'FL-4010'], true)) {
            $activateResponse = $this->licenseClient->activate($licenseKey, $installedModules);
            if ($activateResponse !== null) {
                $response = $activateResponse;
            }
        }

        if (in_array($response['code'] ?? '', ['BL-4290', 'BL-5000', 'FL-4290', 'FL-5000'], true)) {
            $this->logger->warning('Focus_Licensing: transient server response during forceRevalidate', [
                'code' => $response['code'] ?? '',
            ]);
            $this->activityLog->record(
                $source,
                (string) ($response['code'] ?? ''),
                false,
                (int) ($previousState['license_revision'] ?? 0),
                count($previousState['allowed_modules'] ?? []),
                (string) __('Transient server response — existing licence kept, will retry.')
            );

            return false;
        }

        $isValid = ($response['success'] ?? false) === true || ($response['status'] ?? '') === 'active';

        $previousRevision = (int) ($previousState['license_revision'] ?? 0);
        $newRevision = (int) ($response['license_revision'] ?? 0);
        if ($newRevision > 0 && $newRevision !== $previousRevision) {
            $this->logger->info('Focus_Licensing: license state updated from server', [
                'previous_revision' => $previousRevision,
                'new_revision'      => $newRevision,
                'allowed_modules'   => $response['allowed_modules'] ?? [],
            ]);
        }

        $this->activityLog->record(
            $source,
            (string) ($response['code'] ?? ''),
            $isValid,
            $newRevision,
            count($response['allowed_modules'] ?? []),
            $this->describeChange($previousState, $response)
        );

        $this->cacheManager->write($response);

        $this->memoryCache = [];
        return $isValid;
    }

    /**
     * One line describing what this check changed, for the activity history.
     *
     * Module names are reported rather than counts: "Divan Storage Add-ons
     * added" tells an admin something a revision number never could.
     *
     * @param array|null $previous
     * @param array $response
     * @return string
     */
    private function describeChange(?array $previous, array $response): string
    {
        if (($response['success'] ?? false) !== true && ($response['status'] ?? '') !== 'active') {
            return (string) ($response['message'] ?? __('Licence rejected by the server.'));
        }

        if ($previous === null) {
            return (string) __('Licence activated on this store.');
        }

        $before = $previous['allowed_modules'] ?? [];
        $after  = $response['allowed_modules'] ?? [];
        $added   = array_values(array_diff($after, $before));
        $removed = array_values(array_diff($before, $after));

        $parts = [];
        if ($added !== []) {
            $parts[] = (string) __('%1 added', implode(', ', $added));
        }
        if ($removed !== []) {
            $parts[] = (string) __('%1 removed', implode(', ', $removed));
        }

        if ($parts === []) {
            return (string) __('No change.');
        }

        $previousRevision = (int) ($previous['license_revision'] ?? 0);
        $newRevision = (int) ($response['license_revision'] ?? 0);

        return $newRevision > 0 && $newRevision !== $previousRevision
            ? (string) __('Revision %1 → %2 · %3', (string) $previousRevision, (string) $newRevision, implode(' · ', $parts))
            : implode(' · ', $parts);
    }

    /**
     * @param string $moduleName
     * @return bool
     */
    private function evaluateFromCache(string $moduleName): bool
    {
        $licenseKey = $this->config->getGlobalLicenseKey();
        if (empty($licenseKey)) {
            return false;
        }

        $state = $this->cacheManager->read();
        if ($state === null) {
            return false;
        }

        $allowedModules = $state['allowed_modules'] ?? [];
        if (!in_array($moduleName, $allowedModules, true)) {
            return false;
        }

        $issuedAt = strtotime($state['issued_at'] ?? '1970-01-01');
        $ageSeconds = time() - $issuedAt;
        $success = ($state['success'] ?? false) === true || ($state['status'] ?? '') === 'active';

        if (!$success) {
            return false;
        }

        $cacheTtl = $this->config->getCacheTtl();
        $offlineGrace = Config::OFFLINE_GRACE_SECONDS;

        if ($ageSeconds <= $cacheTtl) {
            return true;
        }

        if ($ageSeconds <= $offlineGrace) {
            $this->logger->info('Focus_Licensing: stale global cache, allowing module with grace', ['module' => $moduleName]);
            return true;
        }

        $this->logger->warning('Focus_Licensing: offline grace exceeded, restricting module', ['module' => $moduleName]);
        return false;
    }
}
