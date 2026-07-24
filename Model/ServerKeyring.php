<?php
declare(strict_types=1);

/**
 * Copyright © Focus. All rights reserved.
 * @author Focus Team
 * @package Focus_Licensing
 */

namespace Focus\Licensing\Model;

class ServerKeyring
{
    /**
     * key_id => base64-encoded Ed25519 public key
     */
    private const PUBLIC_KEYS = [
        'focus-2026-8228d3' => 'MFNX5yh6wrNCdHW7RX6SE6Ea6SB2QZ6c6jirXf7b5J0=',
    ];

    /**
     * Raw (binary) public key for a key id, or null when unknown.
     */
    public function getPublicKey(string $keyId): ?string
    {
        if (!isset(self::PUBLIC_KEYS[$keyId])) {
            return null;
        }
        $binary = base64_decode(self::PUBLIC_KEYS[$keyId], true);

        return $binary === false ? null : $binary;
    }
}
