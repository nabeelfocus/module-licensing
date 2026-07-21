<?php
declare(strict_types=1);

namespace Focus\Licensing\Service;

use Focus\Licensing\Api\LicenseClientInterface;
use Focus\Licensing\Api\ModuleDiscoveryInterface;
use Focus\Licensing\Model\Config;

/**
 * "Test Connection" diagnostics: connection → REST API → authentication →
 * signature verification, each reported as its own pass/fail check.
 *
 * Strictly read-only: the local cache is never touched and the server logs
 * the probe under action=test_connection without stamping validation state.
 */
class ConnectionTester
{
    public function __construct(
        private readonly LicenseClientInterface $licenseClient,
        private readonly SignatureVerifier $signatureVerifier,
        private readonly ModuleDiscoveryInterface $moduleDiscovery,
        private readonly Config $config
    ) {}

    /**
     * @return array{success: bool, message: string, checks: array<int, array{label: string, ok: bool, detail: string}>}
     */
    public function run(): array
    {
        $checks = [];

        $licenseKey = $this->config->getGlobalLicenseKey();
        if ($licenseKey === '' || $this->config->getServerUrl() === '') {
            return [
                'success' => false,
                'message' => (string) __('Configure the License Server URL and License Key below, then Save Config first.'),
                'checks'  => [],
            ];
        }

        $report = $this->licenseClient->testConnection(
            $licenseKey,
            $this->moduleDiscovery->getInstalledFocusModules()
        );

        $checks[] = [
            'label'  => (string) __('License server reachable'),
            'ok'     => $report['server_reachable'],
            'detail' => $report['server_reachable']
                ? (string) __('%1 answered in %2 ms', $report['server_url'], (string) $report['server_ms'])
                : (string) __('No HTTP response from %1 — check the URL, DNS and outbound firewall.', $report['server_url']),
        ];

        $apiOk = $report['api_status'] !== null && $report['api_status'] >= 200 && $report['api_status'] < 300;
        $checks[] = [
            'label'  => (string) __('License API'),
            'ok'     => $apiOk,
            'detail' => $report['api_status'] === null
                ? (string) __('The REST endpoint did not respond.')
                : (string) __('HTTP %1 in %2 ms', (string) $report['api_status'], (string) $report['api_ms']),
        ];

        $payload = $report['payload'];
        $authOk = $payload !== null
            && !in_array($payload['code'] ?? '', ['BL-4010', 'BL-4011'], true);
        $checks[] = [
            'label'  => (string) __('Authentication (HMAC)'),
            'ok'     => $authOk,
            'detail' => match (true) {
                $payload === null => (string) __('Skipped — no API response to authenticate.'),
                $authOk           => (string) __('Request signature accepted.'),
                default           => (string) __('Rejected (%1). Click Refresh License to obtain a fresh secret.', (string) ($payload['code'] ?? '')),
            },
        ];

        $signatureOk = $payload !== null && $this->signatureVerifier->isValid($payload);
        $checks[] = [
            'label'  => (string) __('Response signature (Ed25519)'),
            'ok'     => $signatureOk,
            'detail' => match (true) {
                $payload === null => (string) __('Skipped — no API response to verify.'),
                $signatureOk      => (string) __('Valid, signed with key %1.', (string) ($payload['key_id'] ?? '')),
                default           => (string) __('INVALID — the response was not signed by a trusted Focus key.'),
            },
        ];

        if ($payload !== null) {
            $licenseOk = ($payload['success'] ?? false) === true;
            $checks[] = [
                'label'  => (string) __('License check'),
                'ok'     => $licenseOk,
                'detail' => $licenseOk
                    ? (string) __('%1 — revision %2, %3 module(s) allowed.', (string) ($payload['status'] ?? ''), (string) ($payload['license_revision'] ?? '?'), (string) count($payload['allowed_modules'] ?? []))
                    : (string) __('%1: %2', (string) ($payload['code'] ?? ''), (string) ($payload['message'] ?? '')),
            ];
        }

        $success = !in_array(false, array_column($checks, 'ok'), true);

        return [
            'success' => $success,
            'message' => $success
                ? (string) __('All connection checks passed.')
                : (string) __('Some checks failed — see details below.'),
            'checks'  => $checks,
        ];
    }
}
