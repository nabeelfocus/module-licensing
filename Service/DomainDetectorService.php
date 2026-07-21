<?php
declare(strict_types=1);

namespace Focus\Licensing\Service;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Detects and normalises the current store's domain from its secure base URL.
 *
 * The normalisation algorithm is identical to DomainNormalizerService in
 * Focus_LicenseServer — both sides must produce the same output for the same input.
 */
class DomainDetectorService
{
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {}

    /**
     * Detect the canonical domain for the current store.
     *
     * @return string  Normalised domain, e.g. "beds.co.uk"
     */
    public function detect(): string
    {
        $baseUrl = (string) $this->scopeConfig->getValue(
            'web/secure/base_url',
            ScopeInterface::SCOPE_STORE
        );

        if (empty($baseUrl)) {
            $baseUrl = (string) $this->scopeConfig->getValue(
                'web/unsecure/base_url',
                ScopeInterface::SCOPE_STORE
            );
        }

        return $this->normalize($baseUrl);
    }

    /**
     * Normalise a URL/domain to canonical form.
     * MUST be kept in sync with DomainNormalizerService in Focus_LicenseServer.
     */
    public function normalize(string $raw): string
    {
        $domain = strtolower(trim($raw));
        $domain = preg_replace('#^https?://#', '', $domain);
        $domain = preg_replace('#^[^@]+@#', '', $domain);
        $domain = explode('/', $domain)[0];
        $domain = explode('?', $domain)[0];
        $domain = explode('#', $domain)[0];
        $domain = preg_replace('/:\d+$/', '', $domain);

        if (function_exists('idn_to_ascii') && preg_match('/[^\x20-\x7E]/', $domain)) {
            $ascii = idn_to_ascii($domain, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
            if ($ascii !== false) {
                $domain = $ascii;
            }
        }

        $domain = rtrim($domain, '.');
        if (str_starts_with($domain, 'www.')) {
            $domain = substr($domain, 4);
        }

        return $domain;
    }
}
