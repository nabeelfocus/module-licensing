<?php
declare(strict_types=1);

/**
 * Copyright © Focus. All rights reserved.
 * @author Focus Team
 * @package Focus_Licensing
 */

namespace Focus\Licensing\Service;

use Focus\Licensing\Model\ServerKeyring;
use Focus\Licensing\Logger\Logger;

/**
 * Verifies the Ed25519 signature on every license server response before it
 * is trusted or persisted. This is the client half of the anti-tamper /
 * anti-spoof design:
 *
 *   - A fake license server (hosts/DNS redirect) cannot produce a valid
 *     signature, so its "success:true" responses are rejected.
 *   - Editing the cached state in the local database breaks the signature,
 *     so tampering downgrades to "no state" (module restricted).
 *
 * CONTRACT: SIGNED_FIELDS and the canonicalization below mirror the server's
 * Focus\LicenseServer\Service\SignaturePayload exactly. The classes are
 * duplicated (not shared) because client and server ship to different stores.
 */
class SignatureVerifier
{
    private const SIGNED_FIELDS = [
        'success',
        'code',
        'status',
        'allowed_modules',
        'license_revision',
        'domain',
        'expires_at',
        'check_again_in',
        'issued_at',
    ];

    /**
     * @param ServerKeyring $keyring
     * @param Logger $logger
     */
    public function __construct(
        private readonly ServerKeyring $keyring,
        private readonly Logger $logger
    ) {}

    /**
     * Whether a decoded server response / cached state carries a valid signature.
     *
     * @param array $payload
     * @return bool
     */
    public function isValid(array $payload): bool
    {
        $signature = (string) ($payload['signature'] ?? '');
        $keyId = (string) ($payload['key_id'] ?? '');

        if ($signature === '' || $keyId === '') {
            return false;
        }

        $publicKey = $this->keyring->getPublicKey($keyId);
        if ($publicKey === null) {
            $this->logger->warning('Focus_Licensing: response signed with unknown key id', ['key_id' => $keyId]);
            return false;
        }

        $binarySignature = base64_decode($signature, true);
        if ($binarySignature === false) {
            return false;
        }

        try {
            return sodium_crypto_sign_verify_detached(
                $binarySignature,
                $this->canonicalize($payload),
                $publicKey
            );
        } catch (\SodiumException $e) {
            $this->logger->warning('Focus_Licensing: signature verification error', ['exception' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Canonical JSON over the signed fields — must byte-match the server.
     */
    private function canonicalize(array $payload): string
    {
        $canonical = [];
        foreach (self::SIGNED_FIELDS as $field) {
            $value = $payload[$field] ?? null;
            // json response decodes integers/bools natively; normalize the
            // fields whose PHP type could drift from the server's setters
            if ($field === 'success') {
                $value = (bool) $value;
            } elseif ($field === 'check_again_in' || $field === 'license_revision') {
                $value = (int) $value;
            } elseif ($field === 'allowed_modules') {
                $value = array_map('strval', array_values((array) $value));
            }
            $canonical[$field] = $value;
        }

        return (string) json_encode($canonical, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
