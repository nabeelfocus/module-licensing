<?php
declare(strict_types=1);

namespace Focus\Licensing\Config;

use Magento\Framework\Config\FileResolverInterface;
use Magento\Framework\Config\ReaderInterface;
use Magento\Framework\Config\Reader\Filesystem;
use Magento\Framework\Config\ValidationStateInterface;

/**
 * Reads and merges every module's etc/focus_licensing.xml into one config set.
 * Standard Magento filesystem reader — merging, caching and validation are
 * handled by the framework.
 */
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
