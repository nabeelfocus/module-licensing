<?php
declare(strict_types=1);

/**
 * Copyright © Focus. All rights reserved.
 * @author Focus Team
 * @package Focus_Licensing
 */

namespace Focus\Licensing\Plugin\Enforcement;

use Focus\Licensing\Logger\Logger;
use Focus\Licensing\Model\Enforcement\EnforcementGuard;
use Magento\Framework\Event\Invoker\InvokerDefault;
use Magento\Framework\Event\Observer;

/**
 * Observer guard — silently skips observers that belong to a guarded, unlicensed
 * module. Intercepts the framework observer invoker, so it covers every event
 * observer on the store with one plugin.
 *
 * Runs on every observer dispatch, so it must stay cheap: the resolver's fast
 * path returns immediately for any non-Focus observer (virtually all of them).
 */
class ObserverGuardPlugin
{
    /**
     * @param EnforcementGuard $enforcementGuard
     * @param Logger $logger
     */
    public function __construct(
        private readonly EnforcementGuard $enforcementGuard,
        private readonly Logger $logger
    ) {}

    /**
     * @param InvokerDefault $subject
     * @param callable $proceed
     * @param array $configuration observer configuration; 'instance' is the observer class
     * @param Observer $observer
     * @return mixed
     */
    public function aroundDispatch(InvokerDefault $subject, callable $proceed, array $configuration, Observer $observer): mixed
    {
        $observerClass = $configuration['instance'] ?? '';
        if ($observerClass === '') {
            return $proceed($configuration, $observer);
        }

        $blockedModule = $this->enforcementGuard->getBlockedModuleForClass($observerClass);
        if ($blockedModule === null) {
            return $proceed($configuration, $observer);
        }

        // Unlicensed → skip the observer entirely.
        $this->logger->info('Focus_Licensing: skipped observer of unlicensed module', [
            'module'   => $blockedModule,
            'observer' => $observerClass,
        ]);

        return null;
    }
}
