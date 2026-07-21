<?php
declare(strict_types=1);

namespace Focus\Licensing\Observer;

use Focus\Licensing\Api\LicenseGuardInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Message\ManagerInterface;

/**
 * Validates the global license immediately after the Focus Licensing config section is saved.
 */
class ConfigSaveObserver implements ObserverInterface
{
    public function __construct(
        private readonly LicenseGuardInterface $guard,
        private readonly ManagerInterface $messageManager
    ) {}

    public function execute(Observer $observer): void
    {
        try {
            $isValid = $this->guard->forceRevalidate();

            if ($isValid) {
                $this->messageManager->addSuccessMessage(
                    __('Focus License verified successfully.')
                );
            } else {
                $this->messageManager->addErrorMessage(
                    __('Focus License is invalid or missing. Commercial modules are running in restricted mode.')
                );
            }
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(
                __('License validation failed (%1).', $e->getMessage())
            );
        }
    }
}
