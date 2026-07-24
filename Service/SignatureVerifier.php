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
            if ($field === 'success') {
                $value = (bool) $value;
            } elseif ($field === 'check_again_in' || $field === 'license_revision') {
                $value = (int) $value;
            } elseif ($field === 'allowed_modules') {
                $value = array_map('strval', array_values((array) $value));
            }
            $canonical[$field] = $value;
        }

        $protectedModules = array_map('strval', array_values((array) ($payload['protected_modules'] ?? [])));
        if ($protectedModules !== []) {
            $canonical['protected_modules'] = $protectedModules;
        }

        return (string) json_encode($canonical, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
