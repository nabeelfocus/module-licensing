<?php
declare(strict_types=1);

/**
 * Copyright © Focus. All rights reserved.
 * @author Focus Team
 * @package Focus_Licensing
 */

namespace Focus\Licensing\Model;

use Focus\Licensing\Service\SignatureVerifier;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\FlagManager;
use Focus\Licensing\Logger\Logger;

/**
 * Persists and retrieves the license state response for each module.
 *
 * Storage: the framework `flag` table via FlagManager (one flag per module,
 * payload encrypted at rest with the store's crypt key).
 *
 * Why FlagManager and not core_config_data: values read through ScopeConfig
 * are served from the config cache, and WriterInterface::save() does NOT
 * invalidate that cache — so the daily cron would write fresh state while
 * the guard kept reading a stale copy until someone flushed caches, and a
 * perfectly valid license could go "restricted" once the offline grace ran
 * out. Flags are plain DB reads: no cache layer, no invalidation problem,
 * and they survive cache flushes for free.
 *
 * Security: the entire signed JSON payload from the server is stored verbatim
 * and its Ed25519 signature is verified on EVERY read against the public key
 * bundled in ServerKeyring. Tampering with the flag row (or feeding the client
 * a spoofed server response) downgrades to "no state" — module restricted.
 *
 * The per-license HMAC secret arrives only in activation responses; write()
 * carries it forward into subsequent validate-state writes so the client
 * never loses its request-signing credential on a routine refresh.
 */
class LicenseCacheManager
{
    private const FLAG_PREFIX = 'focus_licensing_state_';

    /**
     * @param FlagManager $flagManager
     * @param EncryptorInterface $encryptor
     * @param SignatureVerifier $signatureVerifier
     * @param Logger $logger
     */
    public function __construct(
        private readonly FlagManager $flagManager,
        private readonly EncryptorInterface $encryptor,
        private readonly SignatureVerifier $signatureVerifier,
        private readonly Logger $logger
    ) {}

    /**
     * Read cached global license state.
     * Returns decoded array on success, null if no valid cached state exists.
     *
     * @return array|null
     */
    public function read(): ?array
    {
        try {
            $encrypted = (string) $this->flagManager->getFlagData($this->flagCode());
            if (empty($encrypted)) {
                return null;
            }

            $json = $this->encryptor->decrypt($encrypted);
            if (empty($json)) {
                return null;
            }

            $payload = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

            if (!$this->signatureVerifier->isValid($payload)) {
                $this->logger->warning(
                    'Focus_Licensing: cached global state failed signature verification — ignoring it.'
                );
                return null;
            }

            return $payload;
        } catch (\Exception $e) {
            $this->logger->error('Focus_Licensing: failed to read global cache', ['exception' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Write global license state response (encrypts before storage).
     *
     * @param array $payload Decoded JSON response from the license server
     */
    public function write(array $payload): void
    {
        try {

            if (!$this->signatureVerifier->isValid($payload)) {
                $this->logger->warning(
                    'Focus_Licensing: refusing to store unsigned/invalid global state.'
                );
                return;
            }

            if (empty($payload['secret'])) {
                $existing = $this->readRaw();
                if (!empty($existing['secret'])) {
                    $payload['secret'] = $existing['secret'];
                }
            }

            $json      = json_encode($payload, JSON_THROW_ON_ERROR);
            $encrypted = $this->encryptor->encrypt($json);

            $this->flagManager->saveFlag($this->flagCode(), $encrypted);
        } catch (\Exception $e) {
            $this->logger->error('Focus_Licensing: failed to write global cache', ['exception' => $e->getMessage()]);
        }
    }

    /**
     * Read the stored payload without signature verification — used ONLY to
     * carry the secret forward inside write(); never expose this to callers.
     */
    private function readRaw(): ?array
    {
        try {
            $encrypted = (string) $this->flagManager->getFlagData($this->flagCode());
            if (empty($encrypted)) {
                return null;
            }
            $json = $this->encryptor->decrypt($encrypted);

            return empty($json) ? null : json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * Clear cached global state (e.g. on deactivation).
     */
    public function clear(): void
    {
        try {
            $this->flagManager->deleteFlag($this->flagCode());
        } catch (\Exception $e) {
            $this->logger->error('Focus_Licensing: failed to clear global cache', ['exception' => $e->getMessage()]);
        }
    }

    /**
     * @return string
     */
    private function flagCode(): string
    {
        return self::FLAG_PREFIX . 'global';
    }
}
