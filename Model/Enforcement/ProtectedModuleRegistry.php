<?php
declare(strict_types=1);

namespace Focus\Licensing\Model\Enforcement;

use Focus\Licensing\Api\ProtectedModuleRegistryInterface;
use Magento\Framework\Config\DataInterface;

/**
 * Reads the merged, cached focus_licensing.xml config and exposes the set of
 * guarded commercial modules. Modules explicitly declared enabled="false" are
 * excluded (opt-out).
 */
class ProtectedModuleRegistry implements ProtectedModuleRegistryInterface
{
    /** @var array<string, array{name: string, label: string, enabled: bool}>|null */
    private ?array $modules = null;

    public function __construct(
        private readonly DataInterface $config
    ) {}

    public function getGuardedModules(): array
    {
        return array_keys($this->load());
    }

    public function isGuarded(string $moduleName): bool
    {
        return isset($this->load()[$moduleName]);
    }

    public function getLabel(string $moduleName): string
    {
        return $this->load()[$moduleName]['label'] ?? $moduleName;
    }

    /**
     * @return array<string, array{name: string, label: string, enabled: bool}>
     */
    private function load(): array
    {
        if ($this->modules === null) {
            $all = $this->config->get() ?: [];
            $this->modules = array_filter(
                $all,
                static fn (array $entry): bool => ($entry['enabled'] ?? true) === true
            );
        }

        return $this->modules;
    }
}
