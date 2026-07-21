<?php
declare(strict_types=1);

namespace Focus\Licensing\Controller\Adminhtml\License;

use Focus\Licensing\Block\Adminhtml\Dashboard\View;
use Focus\Licensing\Service\DashboardActions;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\View\LayoutFactory;

/**
 * Base for the dashboard AJAX actions. Each action runs its operation and
 * returns {success, message, html} — html being the freshly re-rendered
 * dashboard, so the page updates without a reload.
 */
abstract class AbstractDashboardAction extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Focus_Licensing::config';

    public function __construct(
        Context $context,
        protected readonly DashboardActions $dashboardActions,
        private readonly JsonFactory $jsonFactory,
        private readonly LayoutFactory $layoutFactory
    ) {
        parent::__construct($context);
    }

    public function execute(): Json
    {
        try {
            $result = $this->runAction();
        } catch (\Exception $e) {
            $result = [
                'success' => false,
                'message' => (string) __('Something went wrong: %1', $e->getMessage()),
            ];
        }

        try {
            $result['html'] = $this->layoutFactory->create()
                ->createBlock(View::class, 'focus_licensing_dashboard')
                ->toHtml();
        } catch (\Exception) {
            // Without fresh HTML the JS falls back to showing just the message
            $result['html'] = null;
        }

        return $this->jsonFactory->create()->setData($result);
    }

    /**
     * @return array{success: bool, message: string}
     */
    abstract protected function runAction(): array;
}
