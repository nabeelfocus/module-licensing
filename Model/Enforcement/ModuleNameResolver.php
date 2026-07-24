<?php
declare(strict_types=1);

/**
 * Copyright © Focus. All rights reserved.
 * @author Focus Team
 * @package Focus_Licensing
 */

namespace Focus\Licensing\Model\Enforcement;

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

    /**
     * @param string $className
     * @return ?string
     */
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
