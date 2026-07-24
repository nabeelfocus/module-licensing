<?php
declare(strict_types=1);

/**
 * Copyright © Focus. All rights reserved.
 * @author Focus Team
 * @package Focus_Licensing
 */

namespace Focus\Licensing\Service;

use Focus\Licensing\Api\LicenseClientInterface;
use Focus\Licensing\Model\Config;
use Focus\Licensing\Model\LicenseCacheManager;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\HTTP\Client\CurlFactory;
use Focus\Licensing\Logger\Logger;

class LicenseClient implements LicenseClientInterface
{
    private const CONNECT_TIMEOUT = 2;
    private const TOTAL_TIMEOUT   = 5;

    /**
     * @param Config $config
     * @param CurlFactory $curlFactory
     * @param DomainDetectorService $domainDetector
     * @param LicenseCacheManager $cacheManager
     * @param ProductMetadataInterface $productMetadata
     * @param Logger $logger
     */
    public function __construct(
        private readonly Config $config,
        private readonly CurlFactory $curlFactory,
        private readonly DomainDetectorService $domainDetector,
        private readonly LicenseCacheManager $cacheManager,
        private readonly ProductMetadataInterface $productMetadata,
        private readonly Logger $logger
    ) {}

    /**
     * @param string $licenseKey
     * @param array $installedModules
     * @return ?array
     */
    public function activate(string $licenseKey, array $installedModules): ?array
    {
        return $this->post('/V1/focus-license/activate', [
            'licenseKey'      => $licenseKey,
            'domain'          => $this->domainDetector->detect(),
            'modules'         => $installedModules,
            'magentoVersion'  => $this->getMagentoVersion(),
            'phpVersion'      => PHP_VERSION,
        ]);
    }

    /**
     * @param string $licenseKey
     * @param array $installedModules
     * @return ?array
     */
    public function validate(string $licenseKey, array $installedModules): ?array
    {
        $state = $this->cacheManager->read();

        return $this->post('/V1/focus-license/validate', [
            'licenseKey'      => $licenseKey,
            'domain'          => $this->domainDetector->detect(),
            'modules'         => $installedModules,
            'licenseRevision' => (int) ($state['license_revision'] ?? 0),
        ], 'validate');
    }

    /**
     * @param string $licenseKey
     * @param array $installedModules
     * @return ?array
     */
    public function deactivate(string $licenseKey, array $installedModules, ?string $domain = null): ?array
    {
        return $this->post('/V1/focus-license/deactivate', [
            'licenseKey' => $licenseKey,
            'domain'     => $domain !== null && $domain !== '' ? $domain : $this->domainDetector->detect(),
            'modules'    => $installedModules,
        ], 'deactivate');
    }

    /**
     * @inheritDoc
     */
    public function testConnection(string $licenseKey, array $installedModules): array
    {
        $serverUrl = $this->config->getServerUrl();
        $report = [
            'server_url'       => $serverUrl,
            'server_reachable' => false,
            'server_ms'        => null,
            'api_status'       => null,
            'api_ms'           => null,
            'payload'          => null,
        ];

        if ($serverUrl === '') {
            return $report;
        }

        try {
            $curl = $this->curlFactory->create();
            $curl->setOption(CURLOPT_CONNECTTIMEOUT, self::CONNECT_TIMEOUT);
            $curl->setOption(CURLOPT_TIMEOUT, self::TOTAL_TIMEOUT);
            $start = microtime(true);
            $curl->get($serverUrl . '/rest/V1/focus-license/health-noop');
            $report['server_ms'] = (int) round((microtime(true) - $start) * 1000);
            $report['server_reachable'] = $curl->getStatus() > 0;
        } catch (\Exception $e) {
            $this->logger->info('Focus_Licensing: test connection — server unreachable', ['exception' => $e->getMessage()]);
        }

        $state = $this->cacheManager->read();
        $body = [
            'licenseKey'      => $licenseKey,
            'domain'          => $this->domainDetector->detect(),
            'modules'         => $installedModules,
            'licenseRevision' => (int) ($state['license_revision'] ?? 0),
            'source'          => 'test_connection',
        ];

        try {
            $json = json_encode($body, JSON_THROW_ON_ERROR);
            $curl = $this->curlFactory->create();
            $curl->setOption(CURLOPT_CONNECTTIMEOUT, self::CONNECT_TIMEOUT);
            $curl->setOption(CURLOPT_TIMEOUT, self::TOTAL_TIMEOUT);
            $curl->setOption(CURLOPT_SSL_VERIFYPEER, true);
            $curl->addHeader('Content-Type', 'application/json');
            $curl->addHeader('Accept', 'application/json');
            foreach ($this->buildHmacHeaders('validate', $json) as $name => $value) {
                $curl->addHeader($name, $value);
            }

            $start = microtime(true);
            $curl->post($serverUrl . '/rest/V1/focus-license/validate', $json);
            $report['api_ms'] = (int) round((microtime(true) - $start) * 1000);
            $report['api_status'] = (int) $curl->getStatus();

            if ($report['api_status'] >= 200 && $report['api_status'] < 300) {
                $report['payload'] = json_decode($curl->getBody(), true, 512, JSON_THROW_ON_ERROR);
            }
        } catch (\Exception $e) {
            $this->logger->info('Focus_Licensing: test connection — API call failed', ['exception' => $e->getMessage()]);
        }

        if (!$report['server_reachable'] && ($report['api_status'] ?? 0) > 0) {
            $report['server_reachable'] = true;
            $report['server_ms'] = $report['api_ms'];
        }

        return $report;
    }

    /**
     * @param string      $path       REST path below /rest
     * @param array       $body       JSON body
     * @param string|null $action     HMAC action name; null = unsigned bootstrap call
     */
    private function post(string $path, array $body, ?string $action = null): ?array
    {
        $serverUrl = $this->config->getServerUrl();
        if (empty($serverUrl)) {
            $this->logger->warning('Focus_Licensing: server URL not configured.');
            return null;
        }

        $url  = $serverUrl . '/rest' . $path;
        $json = json_encode($body, JSON_THROW_ON_ERROR);

        try {
            $curl = $this->curlFactory->create();
            $curl->setOption(CURLOPT_CONNECTTIMEOUT, self::CONNECT_TIMEOUT);
            $curl->setOption(CURLOPT_TIMEOUT, self::TOTAL_TIMEOUT);
            $curl->setOption(CURLOPT_SSL_VERIFYPEER, true);
            $curl->addHeader('Content-Type', 'application/json');
            $curl->addHeader('Accept', 'application/json');

            if ($action !== null) {
                foreach ($this->buildHmacHeaders($action, $json) as $name => $value) {
                    $curl->addHeader($name, $value);
                }
            }

            $curl->post($url, $json);

            $statusCode = $curl->getStatus();
            $responseBody = $curl->getBody();

            if ($statusCode >= 200 && $statusCode < 300) {
                return json_decode($responseBody, true, 512, JSON_THROW_ON_ERROR);
            }

            $this->logger->warning('Focus_Licensing: API returned non-2xx response', [
                'url'    => $url,
                'status' => $statusCode,
                'body'   => substr($responseBody, 0, 500),
            ]);
            return null;

        } catch (\Exception $e) {
            $this->logger->warning('Focus_Licensing: API call failed', [
                'url'       => $url,
                'exception' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * HMAC headers for a signed call. When no secret is cached yet (first run,
     * cache cleared) the headers are omitted — the server answers BL-4010 and
     * the guard falls back to a fresh activate() to re-bootstrap the secret.
     *
     * @return array<string, string>
     */
    private function buildHmacHeaders(string $action, string $jsonBody): array
    {
        $state = $this->cacheManager->read();
        $secret = (string) ($state['secret'] ?? '');
        if ($secret === '') {
            return [];
        }

        $timestamp = (string) time();
        $nonce = bin2hex(random_bytes(16));
        $stringToSign = implode('|', [
            'POST',
            $action,
            $timestamp,
            $nonce,
            hash('sha256', $jsonBody),
        ]);

        return [
            'X-Focus-Timestamp' => $timestamp,
            'X-Focus-Nonce'     => $nonce,
            'X-Focus-Signature' => hash_hmac('sha256', $stringToSign, $secret),
        ];
    }

    /**
     * @return string
     */
    private function getMagentoVersion(): string
    {
        try {
            return $this->productMetadata->getVersion();
        } catch (\Exception) {
            return 'unknown';
        }
    }
}
