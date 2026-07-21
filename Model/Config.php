<?php
declare(strict_types=1);

namespace Focus\Licensing\Model;


use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Reads Focus_Licensing configuration from core_config_data.
 */
class Config
{
    public const XML_PATH_SERVER_URL = 'focus_licensing/general/server_url';
    public const XML_PATH_CACHE_TTL  = 'focus_licensing/general/cache_ttl';

    /** Default cache TTL: 25 hours (slightly longer than daily cron interval) */
    public const DEFAULT_CACHE_TTL = 90000;

    /** Offline grace period: 14 days in seconds */
    public const OFFLINE_GRACE_SECONDS = 1209600;

    /** Expiry warning threshold: 30 days in seconds */
    public const EXPIRY_WARNING_DAYS = 30;

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly EncryptorInterface $encryptor
    ) {}

    /**
     * Base URL of the Focus_LicenseServer instance (e.g. https://license.beds.co.uk)
     */
    public function getServerUrl(): string
    {
        return rtrim(
            (string) $this->scopeConfig->getValue(self::XML_PATH_SERVER_URL, ScopeInterface::SCOPE_STORE),
            '/'
        );
    }

    /**
     * How long to trust a cached license state before the cron must refresh it (seconds).
     */
    public function getCacheTtl(): int
    {
        return (int) ($this->scopeConfig->getValue(self::XML_PATH_CACHE_TTL, ScopeInterface::SCOPE_STORE) ?: self::DEFAULT_CACHE_TTL);
    }

    /**
     * Retrieve and decrypt the global license key.
     * Returns empty string if not configured.
     */
    public function getGlobalLicenseKey(): string
    {
        $encrypted = (string) $this->scopeConfig->getValue('focus_licensing/general/license_key', ScopeInterface::SCOPE_STORE);
        if (empty($encrypted)) {
            return '';
        }
        try {
            return $this->encryptor->decrypt($encrypted);
        } catch (\Exception) {
            return $encrypted;
        }
    }
}
