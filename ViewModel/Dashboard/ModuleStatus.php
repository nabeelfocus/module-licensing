<?php
declare(strict_types=1);

namespace Focus\Licensing\ViewModel\Dashboard;

use Focus\Licensing\Api\LicenseGuardInterface;
use Magento\Framework\Module\FullModuleList;
use Magento\Framework\Module\Manager as ModuleManager;
use Magento\Framework\Module\ModuleListInterface;
use Magento\Framework\Module\PackageInfo;
use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * Discovers every installed commercial Focus module and combines four
 * independent facts per module for display:
 *
 *   installed — present in config.php (FullModuleList)
 *   enabled   — enabled flag in config.php (Module Manager)
 *   licensed  — LicenseGuard verdict (the only licensing authority)
 *   status    — derived label: Active / Restricted / Disabled
 *
 * Purely presentational; consumed by the dashboard card and the
 * Commercial Modules grid.
 */
class ModuleStatus implements ArgumentInterface
{
    public const STATUS_ACTIVE     = 'active';
    public const STATUS_RESTRICTED = 'restricted';
    public const STATUS_DISABLED   = 'disabled';

    private const EXCLUDED_MODULES = [
        'Focus_Licensing',
        'Focus_LicenseServer',
    ];

    private ?array $rows = null;

    public function __construct(
        private readonly FullModuleList $fullModuleList,
        private readonly ModuleManager $moduleManager,
        private readonly ModuleListInterface $moduleList,
        private readonly PackageInfo $packageInfo,
        private readonly LicenseGuardInterface $licenseGuard,
        private readonly LicenseState $licenseState
    ) {}

    /**
     * @return array<int, array{
     *     module: string, version: string, installed: bool, enabled: bool,
     *     licensed: bool, status: string, status_label: string,
     *     severity: string, reason: string, last_validation: ?string
     * }>
     */
    public function getRows(): array
    {
        if ($this->rows !== null) {
            return $this->rows;
        }

        $lastValidation = $this->licenseState->getLastSyncedAt();
        $rows = [];

        foreach ($this->fullModuleList->getNames() as $moduleName) {
            if (!str_starts_with($moduleName, 'Focus_')
                || in_array($moduleName, self::EXCLUDED_MODULES, true)
            ) {
                continue;
            }

            $enabled  = $this->moduleManager->isEnabled($moduleName);
            $licensed = $enabled && $this->licenseGuard->isLicensed($moduleName);

            if (!$enabled) {
                $status = self::STATUS_DISABLED;
            } elseif ($licensed) {
                $status = self::STATUS_ACTIVE;
            } else {
                $status = self::STATUS_RESTRICTED;
            }

            $rows[] = [
                'module'          => $moduleName,
                'version'         => $this->resolveVersion($moduleName),
                'installed'       => true,
                'enabled'         => $enabled,
                'licensed'        => $licensed,
                'status'          => $status,
                'status_label'    => $this->statusLabel($status),
                'severity'        => match ($status) {
                    self::STATUS_ACTIVE => 'ok',
                    self::STATUS_RESTRICTED => 'crit',
                    default => 'neutral',
                },
                'reason'          => $this->restrictionReason($moduleName, $enabled, $licensed),
                'last_validation' => $enabled ? $lastValidation : null,
            ];
        }

        usort($rows, static fn (array $a, array $b): int => strcmp($a['module'], $b['module']));

        return $this->rows = $rows;
    }

    /**
     * Rows for enabled modules only — what the dashboard card shows.
     * Disabled modules stay visible on the Commercial Modules grid.
     *
     * @return array[]
     */
    public function getEnabledRows(): array
    {
        return array_values(array_filter(
            $this->getRows(),
            static fn (array $r): bool => $r['enabled']
        ));
    }

    public function getInstalledCount(): int
    {
        return count($this->getRows());
    }

    public function getLicensedCount(): int
    {
        return count(array_filter($this->getRows(), static fn (array $r): bool => $r['licensed']));
    }

    public function getLicenseState(): LicenseState
    {
        return $this->licenseState;
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            self::STATUS_ACTIVE     => (string) __('Active'),
            self::STATUS_RESTRICTED => (string) __('Restricted'),
            default                 => (string) __('Disabled'),
        };
    }

    /**
     * Why a module is not operating — empty for active modules.
     */
    private function restrictionReason(string $moduleName, bool $enabled, bool $licensed): string
    {
        if ($licensed) {
            return '';
        }
        if (!$enabled) {
            return (string) __('Module is disabled in this installation.');
        }
        if (!$this->licenseState->isKeyConfigured()) {
            return (string) __('No license key configured.');
        }

        return match ($this->licenseState->getStatus()) {
            LicenseState::STATUS_NOT_ACTIVATED => (string) __('License not activated on this store.'),
            LicenseState::STATUS_EXPIRED       => (string) __('License expired.'),
            LicenseState::STATUS_SUSPENDED     => (string) __('License suspended.'),
            LicenseState::STATUS_INVALID       => (string) __('License invalid.'),
            LicenseState::STATUS_VALIDATION_REQUIRED => (string) __('License validation overdue (offline grace ended).'),
            default => (string) __('Not included in your license — contact Focus to add %1.', $moduleName),
        };
    }

    private function resolveVersion(string $moduleName): string
    {
        try {
            $version = (string) $this->packageInfo->getVersion($moduleName);
            if ($version !== '') {
                return $version;
            }
        } catch (\Exception) {
            // fall through to module.xml
        }

        $config = $this->moduleList->getOne($moduleName);

        return (string) ($config['setup_version'] ?? '') !== ''
            ? (string) $config['setup_version']
            : '—';
    }
}
