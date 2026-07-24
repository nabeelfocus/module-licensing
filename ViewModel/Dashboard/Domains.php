<?php
declare(strict_types=1);

/**
 * Copyright © Focus. All rights reserved.
 * @author Focus Team
 * @package Focus_Licensing
 */

namespace Focus\Licensing\ViewModel\Dashboard;

use Magento\Framework\View\Element\Block\ArgumentInterface;

class Domains implements ArgumentInterface
{
    /**
     * @param LicenseState $licenseState
     */
    public function __construct(
        private readonly LicenseState $licenseState
    ) {}

    /**
     * @return array<int, array{domain: string, type: string, status: string,
     *                          activated_at: ?string, last_validated_at: ?string,
     *                          is_current: bool, uses_slot: bool, releasable: bool}>
     */
    public function getRows(): array
    {
        $state = $this->licenseState->getState();
        if ($state === null) {
            return [];
        }

        $current = (string) ($state['domain'] ?? '');
        $rows = [];

        foreach ((array) ($state['domains'] ?? []) as $row) {
            if (!is_array($row) || empty($row['domain'])) {
                continue;
            }
            $rows[] = [
                'domain'            => (string) $row['domain'],
                'type'              => (string) ($row['domain_type'] ?? ''),
                'status'            => (string) ($row['status'] ?? ''),
                'activated_at'      => $row['activated_at'] ?? null,
                'last_validated_at' => $row['last_validated_at'] ?? null,
                'is_current'        => (string) $row['domain'] === $current,
                'uses_slot'         => $this->usesSlot((string) ($row['domain_type'] ?? '')),
                'releasable'        => (string) $row['domain'] !== $current
                    && (string) ($row['status'] ?? '') !== 'revoked',
            ];
        }

        if (empty($rows) && $current !== '') {
            $rows[] = [
                'domain'            => $current,
                'type'              => (string) ($state['domain_type'] ?? ''),
                'status'            => 'active',
                'activated_at'      => null,
                'last_validated_at' => $state['issued_at'] ?? null,
                'is_current'        => true,
                'uses_slot'         => $this->usesSlot((string) ($state['domain_type'] ?? '')),
                'releasable'        => false,
            ];
        }

        return $rows;
    }

    /**
     * @return LicenseState
     */
    public function getLicenseState(): LicenseState
    {
        return $this->licenseState;
    }

    /**
     * Whether a domain of this type consumes a paid slot — production and
     * public IP only, mirroring the server's rule. Local/staging are free.
     *
     * @param string $type
     * @return bool
     */
    private function usesSlot(string $type): bool
    {
        return in_array($type, ['production', 'ip'], true);
    }

    /**
     * Paid slots currently consumed by active domains.
     *
     * @return int
     */
    public function getSlotsUsed(): int
    {
        return count(array_filter(
            $this->getRows(),
            static fn (array $r): bool => $r['uses_slot'] && $r['status'] !== 'revoked'
        ));
    }

    /**
     * Paid slots the licence permits. 0 when the server did not report it.
     *
     * @return int
     */
    public function getSlotsTotal(): int
    {
        return $this->licenseState->getMaxDomains();
    }
}
