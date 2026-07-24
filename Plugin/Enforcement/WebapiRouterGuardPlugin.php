<?php
declare(strict_types=1);

/**
 * Copyright © Focus. All rights reserved.
 * @author Focus Team
 * @package Focus_Licensing
 */

namespace Focus\Licensing\Plugin\Enforcement;

use Focus\Licensing\Model\Enforcement\ModuleLabelResolver;
use Focus\Licensing\Model\Enforcement\EnforcementGuard;
use Magento\Framework\Webapi\Exception as WebapiException;
use Magento\Webapi\Controller\Rest\Router;
use Magento\Webapi\Controller\Rest\Router\Route;

class WebapiRouterGuardPlugin
{
    /**
     * @param EnforcementGuard $enforcementGuard
     * @param ModuleLabelResolver $labelResolver
     */
    public function __construct(
        private readonly EnforcementGuard $enforcementGuard,
        private readonly ModuleLabelResolver $labelResolver
    ) {}

    /**
     * @param Router $subject
     * @param Route $route
     * @return Route
     * @throws WebapiException
     */
    public function afterMatch(Router $subject, Route $route): Route
    {
        $serviceClass = (string) $route->getServiceClass();
        if ($serviceClass === '') {
            return $route;
        }

        $blockedModule = $this->enforcementGuard->getBlockedModuleForClass($serviceClass);
        if ($blockedModule !== null) {
            throw new WebapiException(
                __(
                    '%1 is not licensed on this store. This API endpoint is unavailable in restricted mode.',
                    $this->labelResolver->getLabel($blockedModule)
                ),
                0,
                WebapiException::HTTP_FORBIDDEN
            );
        }

        return $route;
    }
}
