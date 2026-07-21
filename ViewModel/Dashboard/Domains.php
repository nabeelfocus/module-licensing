<?php
declare(strict_types=1);

namespace Focus\Licensing\ViewModel\Dashboard;

use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * Registered Domains card. Rows come from the informational (unsigned)
 * `domains` list in the latest server response — display only, never used
 * for licensing decisions. Falls back to the current store's own domain
 * when the server payload predates the domains field.
 */
class Domains implements ArgumentInterface
{
    public function __construct(
        private readonly LicenseState $licenseState
    ) {}

    /**
     * @return array<int, array{domain: string, type: string, status: string,
     *                          activated_at: ?string, last_validated_at: ?string, is_current: bool}>
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
            ];
        }

        // Older cached payloads have no domains list — show at least this store
        if (empty($rows) && $current !== '') {
            $rows[] = [
                'domain'            => $current,
                'type'              => (string) ($state['domain_type'] ?? ''),
                'status'            => 'active',
                'activated_at'      => null,
                'last_validated_at' => $state['issued_at'] ?? null,
                'is_current'        => true,
            ];
        }

        return $rows;
    }

    public function getLicenseState(): LicenseState
    {
        return $this->licenseState;
    }
}
