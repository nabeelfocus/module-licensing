<?php
declare(strict_types=1);

/**
 * Copyright © Focus. All rights reserved.
 * @author Focus Team
 * @package Focus_Licensing
 */

namespace Focus\Licensing\Api;

/**
 * HTTP client for communicating with Focus_LicenseServer REST API.
 *
 * All methods catch all exceptions — network failure is a normal operating
 * state, not an error. Returns null on any communication failure.
 * The caller (LicenseGuard) applies offline grace rules.
 *
 * Timeouts: 2s connect / 5s total (never adds latency to web requests).
 */
interface LicenseClientInterface
{
    /**
     * Call /V1/focus-license/activate on the license server.
     *
     * @param string $licenseKey
     * @param string[] $installedModules
     * @return array|null  Decoded response payload or null on failure
     */
    public function activate(string $licenseKey, array $installedModules): ?array;

    /**
     * Call /V1/focus-license/validate on the license server.
     *
     * @param string $licenseKey
     * @param string[] $installedModules
     * @return array|null
     */
    public function validate(string $licenseKey, array $installedModules): ?array;

    /**
     * Call /V1/focus-license/deactivate on the license server.
     *
     * @param string $licenseKey
     * @param string[] $installedModules
     * @return array|null
     */
    public function deactivate(string $licenseKey, array $installedModules): ?array;

    /**
     * Read-only diagnostics probe for the admin "Test Connection" button.
     * Measures base-URL reachability, then performs a validate round-trip
     * flagged source=test_connection (logged separately server-side, does not
     * stamp last_validated_at). Never writes the local cache.
     *
     * @param string $licenseKey
     * @param string[] $installedModules
     * @return array{
     *     server_url: string, server_reachable: bool, server_ms: ?int,
     *     api_status: ?int, api_ms: ?int, payload: ?array
     * }
     */
    public function testConnection(string $licenseKey, array $installedModules): array;
}
