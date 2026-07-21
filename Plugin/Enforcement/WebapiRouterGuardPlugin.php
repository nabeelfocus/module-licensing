<?php
declare(strict_types=1);

/**
 * Copyright © Focus. All rights reserved.
 * @author Focus Team
 * @package Focus_Licensing
 */

namespace Focus\Licensing\Plugin\Enforcement;

use Focus\Licensing\Api\ProtectedModuleRegistryInterface;
use Focus\Licensing\Model\Enforcement\EnforcementGuard;
use Magento\Framework\Webapi\Exception as WebapiException;
use Magento\Webapi\Controller\Rest\Router;
use Magento\Webapi\Controller\Rest\Router\Route;

/**
 * Web API (REST) guard — after the router matches a request to a service class,
 * this checks whether that class belongs to a guarded, unlicensed module and,
 * if so, throws a 403 so the caller gets a clean JSON error instead of the
 * commercial endpoint running.
 *
 * Registered in etc/webapi_rest/di.xml, so it only loads on the REST area.
 */
class WebapiRouterGuardPlugin
{
    /**
     * @param EnforcementGuard $enforcementGuard
     * @param ProtectedModuleRegistryInterface $registry
     */
    public function __construct(
        private readonly EnforcementGuard $enforcementGuard,
        private readonly ProtectedModuleRegistryInterface $registry
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
                    $this->registry->getLabel($blockedModule)
                ),
                0,
                WebapiException::HTTP_FORBIDDEN
            );
        }

        return $route;
    }
}
