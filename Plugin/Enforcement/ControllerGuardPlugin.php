<?php
declare(strict_types=1);

namespace Focus\Licensing\Plugin\Enforcement;

use Focus\Licensing\Api\ProtectedModuleRegistryInterface;
use Focus\Licensing\Logger\Logger;
use Focus\Licensing\Model\Enforcement\EnforcementGuard;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\State;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\UrlInterface;

/**
 * Controller guard — the routing enforcement point.
 *
 * Runs on every controller's execute(). If the controller belongs to a guarded
 * module that is currently unlicensed, its execution is replaced with a safe
 * redirect (admin → dashboard + notice; frontend → home). Everything else —
 * the overwhelming majority of requests — passes straight through after a
 * single inexpensive namespace check.
 *
 * Note: this protects a Focus module's OWN controllers/routes. Rendering that a
 * commercial module injects into a CORE page (e.g., a PDP block) is guarded at
 * the block level by that module's own isLicensed() check, not here.
 */
class ControllerGuardPlugin
{
    public function __construct(
        private readonly EnforcementGuard $enforcementGuard,
        private readonly ProtectedModuleRegistryInterface $registry,
        private readonly State $appState,
        private readonly ResultFactory $resultFactory,
        private readonly UrlInterface $url,
        private readonly ManagerInterface $messageManager,
        private readonly Logger $logger
    ) {}

    /**
     * @param ActionInterface $subject
     * @param callable $proceed
     * @return mixed
     */
    public function aroundExecute(ActionInterface $subject, callable $proceed): mixed
    {
        $blockedModule = $this->enforcementGuard->getBlockedModuleForClass($subject::class);
        if ($blockedModule === null) {
            return $proceed();
        }

        try {
            return $this->buildBlockedResult($blockedModule);
        } catch (\Throwable $e) {
            $this->logger->error('Focus_Licensing: controller guard could not build block result (fail-open)', [
                'module'    => $blockedModule,
                'exception' => $e->getMessage(),
            ]);
            return $proceed();
        }
    }

    private function buildBlockedResult(string $moduleName)
    {
        $label = $this->registry->getLabel($moduleName);

        if ($this->isAdmin()) {
            $this->messageManager->addErrorMessage(
                (string) __(
                    '%1 is not licensed on this store and is running in restricted mode. '
                    . 'Verify the license under Stores > Configuration > Focus > Licensing or contact Focus support.',
                    $label
                )
            );
            /** @var \Magento\Framework\Controller\Result\Redirect $redirect */
            $redirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
            $redirect->setPath('adminhtml/dashboard');

            return $redirect;
        }

        $this->logger->info('Focus_Licensing: blocked frontend controller of unlicensed module', [
            'module' => $moduleName,
        ]);
        /** @var \Magento\Framework\Controller\Result\Redirect $redirect */
        $redirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        $redirect->setUrl($this->url->getBaseUrl());

        return $redirect;
    }

    private function isAdmin(): bool
    {
        try {
            return $this->appState->getAreaCode() === \Magento\Framework\App\Area::AREA_ADMINHTML;
        } catch (\Throwable) {
            return false;
        }
    }
}
