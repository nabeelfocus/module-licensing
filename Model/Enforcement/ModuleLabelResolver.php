<?php
declare(strict_types=1);

/**
 * Copyright © Focus. All rights reserved.
 * @author Focus Team
 * @package Focus_Licensing
 */

namespace Focus\Licensing\Model\Enforcement;

use Focus\Licensing\Api\ProtectedModuleRegistryInterface;

/**
 * Turns a Magento module identifier into something a merchant can read.
 *
 * Two sources, in order:
 *
 *   1. The `label` attribute on the module's own etc/focus_licensing.xml
 *      declaration. This is the authoritative name and the one to curate —
 *      "Focus_StorageAddons" is sold as "Divan Storage Add-ons", which no
 *      amount of string splitting could produce.
 *   2. Otherwise a derived name: strip the vendor prefix, split the CamelCase
 *      and restore the acronyms that a naive split mangles ("Pdp" → "PDP").
 *
 * The derived form exists so a module that has not been given a label — a new
 * one, or a customer's own — still reads acceptably instead of showing a raw
 * class-style identifier.
 */
class ModuleLabelResolver
{
    /**
     * Words the CamelCase split produces that should be shown differently.
     * Keyed by the lower-cased split token.
     */
    private const REWRITES = [
        'pdp'  => 'PDP',
        'sku'  => 'SKU',
        'uk'   => 'UK',
        'api'  => 'API',
        'usp'  => 'USP',
        'cms'  => 'CMS',
        'url'  => 'URL',
        'seo'  => 'SEO',
        'csv'  => 'CSV',
        'xml'  => 'XML',
        'pdf'  => 'PDF',
        'and'  => '&',
    ];

    /** @var array<string, string> */
    private array $memo = [];

    /**
     * @param ProtectedModuleRegistryInterface $registry
     */
    public function __construct(
        private readonly ProtectedModuleRegistryInterface $registry
    ) {}

    /**
     * Merchant-facing name for a module, e.g. "Coupon Message on Product Page".
     *
     * @param string $moduleName Magento module identifier, e.g. Focus_PdpCouponMessage
     * @return string
     */
    public function getLabel(string $moduleName): string
    {
        if (isset($this->memo[$moduleName])) {
            return $this->memo[$moduleName];
        }

        $declared = $this->registry->getLabel($moduleName);
        $label = ($declared !== '' && $declared !== $moduleName)
            ? $declared
            : $this->derive($moduleName);

        return $this->memo[$moduleName] = $label;
    }

    /**
     * Derive a readable name from the identifier alone.
     *
     * @param string $moduleName
     * @return string
     */
    private function derive(string $moduleName): string
    {
        $bare = str_contains($moduleName, '_')
            ? substr($moduleName, (int) strpos($moduleName, '_') + 1)
            : $moduleName;

        if ($bare === '') {
            return $moduleName;
        }

        // Split on lower→upper boundaries and before an acronym run that is
        // followed by a normal word ("KSystemSync" → K | System | Sync).
        $spaced = (string) preg_replace(
            ['/([a-z0-9])([A-Z])/', '/([A-Z]+)([A-Z][a-z])/'],
            ['$1 $2', '$1 $2'],
            $bare
        );

        $words = array_map(
            static fn (string $word): string => self::REWRITES[strtolower($word)] ?? $word,
            preg_split('/\s+/', trim($spaced)) ?: []
        );

        return $words === [] ? $moduleName : implode(' ', $words);
    }
}
