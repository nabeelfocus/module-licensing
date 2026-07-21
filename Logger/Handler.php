<?php
declare(strict_types=1);

namespace Focus\Licensing\Logger;

use Magento\Framework\Logger\Handler\Base;
use Monolog\Logger;

/**
 * Writes the Focus_Licensing channel to var/log/focus_licensing.log.
 */
class Handler extends Base
{
    /**
     * @var int
     */
    protected $loggerType = Logger::DEBUG;

    /**
     * @var string
     */
    protected $fileName = '/var/log/focus_licensing.log';
}
