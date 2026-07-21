<?php
declare(strict_types=1);

namespace Focus\Licensing\Model\Enforcement;

/**
 * Resolves the owning Focus module name from a fully-qualified class name,
 * following Magento's PSR-4 convention (Vendor\Module\... → Vendor_Module).
 *
 * Handles generated interceptors (…\Interceptor) and factories/proxies
 * (…\Proxy) by stripping the generated suffix first. Results are memoized —
 * this runs on every controller / observer dispatch, so it must be cheap.
 *
 * Deliberately only concerns itself with the Focus vendor: any non-Focus class
 * returns null immediately, keeping the enforcement hot path free for the
 * thousands of core/third-party classes on the store.
 */
class ModuleNameResolver
{
    private const VENDOR = 'Focus';
    private const GENERATED_SUFFIXES = ['\\Interceptor', '\\Proxy', '\\Factory'];

    /** @var array<string, string|null> memoized className → moduleName|null */
    private array $cache = [];

    /**
     * @param string $className FQCN, with or without a leading backslash
     * @return string|null e.g. "Focus_StorageAddons", or null if not a Focus class
     */
    public function resolve(string $className): ?string
    {
        if (array_key_exists($className, $this->cache)) {
            return $this->cache[$className];
        }

        $this->cache[$className] = $this->doResolve($className);

        return $this->cache[$className];
    }

    private function doResolve(string $className): ?string
    {
        $fqcn = ltrim($className, '\\');

        // Fast path: not a Focus class → not our concern
        if (!str_starts_with($fqcn, self::VENDOR . '\\')) {
            return null;
        }

        foreach (self::GENERATED_SUFFIXES as $suffix) {
            if (str_ends_with($fqcn, $suffix)) {
                $fqcn = substr($fqcn, 0, -strlen($suffix));
                break;
            }
        }

        $parts = explode('\\', $fqcn);
        if (count($parts) < 2 || $parts[0] !== self::VENDOR || $parts[1] === '') {
            return null;
        }

        return $parts[0] . '_' . $parts[1];
    }
}
