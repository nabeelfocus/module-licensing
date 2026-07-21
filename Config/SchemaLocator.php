<?php
declare(strict_types=1);

namespace Focus\Licensing\Config;

use Magento\Framework\Config\Dom\UrnResolver;
use Magento\Framework\Config\SchemaLocatorInterface;

/**
 * Points the focus_licensing.xml reader at its XSD for per-file and merged
 * validation.
 */
class SchemaLocator implements SchemaLocatorInterface
{
    private string $schema;

    public function __construct(UrnResolver $urnResolver)
    {
        $this->schema = $urnResolver->getRealPath(
            'urn:magento:module:Focus_Licensing:etc/focus_licensing.xsd'
        );
    }

    public function getSchema(): ?string
    {
        return $this->schema;
    }

    public function getPerFileSchema(): ?string
    {
        return $this->schema;
    }
}
