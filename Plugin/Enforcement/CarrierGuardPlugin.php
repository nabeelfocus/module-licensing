<?php
declare(strict_types=1);

/**
 * Copyright © Focus. All rights reserved.
 * @author Focus Team
 * @package Focus_Licensing
 */

namespace Focus\Licensing\Plugin\Enforcement;

use Focus\Licensing\Model\Enforcement\EnforcementGuard;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Shipping\Model\Carrier\AbstractCarrierInterface;

class CarrierGuardPlugin
{
    /**
     * @param EnforcementGuard $enforcementGuard
     */
    public function __construct(
        private readonly EnforcementGuard $enforcementGuard
    ) {}

    /**
     * @param AbstractCarrierInterface $subject
     * @param callable $proceed
     * @param RateRequest $request
     * @return \Magento\Framework\DataObject|bool|null
     */
    public function aroundCollectRates(AbstractCarrierInterface $subject, callable $proceed, RateRequest $request)
    {
        if ($this->enforcementGuard->getBlockedModuleForClass($subject::class) !== null) {
            return false;
        }

        return $proceed($request);
    }
}
