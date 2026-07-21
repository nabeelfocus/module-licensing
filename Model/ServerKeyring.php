<?php
declare(strict_types=1);

namespace Focus\Licensing\Model;

/**
 * Ed25519 public keys of the Focus license server, embedded in code on purpose:
 * a key living in config or the database could be swapped by anyone with store
 * access, which would defeat response verification entirely. Changing the key
 * must require changing the module code.
 *
 * Rotation: generate a new pair on the server (focus:license:generate-keys),
 * add the new key_id here while KEEPING the old one, ship the client, then
 * switch the server to sign with the new key. Remove the old entry a release later.
 */
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
