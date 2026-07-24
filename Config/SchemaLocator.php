<?php
declare(strict_types=1);

/**
 * Copyright © Focus. All rights reserved.
 * @author Focus Team
 * @package Focus_Licensing
 */

namespace Focus\Licensing\Config;

use Magento\Framework\Config\Dom\UrnResolver;
use Magento\Framework\Config\SchemaLocatorInterface;

class SchemaLocator implements SchemaLocatorInterface
{
    private string $schema;

    /**
     * @param UrnResolver $urnResolver
     */
    public function __construct(UrnResolver $urnResolver)
    {
        $this->schema = $urnResolver->getRealPath(
            'urn:magento:module:Focus_Licensing:etc/focus_licensing.xsd'
        );
    }

    /**
     * @return ?string
     */
    public function getSchema(): ?string
    {
        return $this->schema;
    }

    /**
     * @return ?string
     */
    public function getPerFileSchema(): ?string
    {
        return $this->schema;
    }
}
