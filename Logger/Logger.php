<?php
declare(strict_types=1);

namespace Focus\Licensing\Logger;

/**
 * Dedicated channel for the licensing client. Everything the module logs
 * (validation, cache, signature, guard decisions) lands in
 * var/log/focus_licensing.log instead of the shared system.log, so license
 * issues on a customer store are diagnosable from a single file.
 */
class Logger extends \Monolog\Logger
{
}
