<?php
declare(strict_types=1);

/**
 * Copyright © Focus. All rights reserved.
 * @author Focus Team
 * @package Focus_Licensing
 */

namespace Focus\Licensing\Config;

use Magento\Framework\Config\FileResolverInterface;
use Magento\Framework\Config\ReaderInterface;
use Magento\Framework\Config\Reader\Filesystem;
use Magento\Framework\Config\ValidationStateInterface;

class Reader extends Filesystem implements ReaderInterface
{
    /**
     * The XML element whose "name" attribute keys each merged entry.
     *
     * @var array<string, string>
     */
    protected $_idAttributes = [
        '/config/module' => 'name',
    ];

    /**
     * @param FileResolverInterface $fileResolver
     * @param Converter $converter
     * @param SchemaLocator $schemaLocator
     * @param ValidationStateInterface $validationState
     * @param string $fileName
     * @param array $idAttributes
     * @param string $domDocumentClass
     * @param string $defaultScope
     */
    public function __construct(
        FileResolverInterface $fileResolver,
        Converter $converter,
        SchemaLocator $schemaLocator,
        ValidationStateInterface $validationState,
        string $fileName = 'focus_licensing.xml',
        array $idAttributes = [],
        string $domDocumentClass = \Magento\Framework\Config\Dom::class,
        string $defaultScope = 'global'
    ) {
        parent::__construct(
            $fileResolver,
            $converter,
            $schemaLocator,
            $validationState,
            $fileName,
            $idAttributes,
            $domDocumentClass,
            $defaultScope
        );
    }
}
