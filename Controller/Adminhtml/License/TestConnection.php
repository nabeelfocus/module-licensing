<?php
declare(strict_types=1);

/**
 * Copyright © Focus. All rights reserved.
 * @author Focus Team
 * @package Focus_Licensing
 */

namespace Focus\Licensing\Controller\Adminhtml\License;

use Focus\Licensing\Service\ConnectionTester;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;

/**
 * "Test Connection": read-only diagnostics probe. Returns per-step checks;
 * never modifies license state on either side.
 */
class TestConnection extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Focus_Licensing::config';

    /**
     * @param Context $context
     * @param ConnectionTester $connectionTester
     * @param JsonFactory $jsonFactory
     */
    public function __construct(
        Context $context,
        private readonly ConnectionTester $connectionTester,
        private readonly JsonFactory $jsonFactory
    ) {
        parent::__construct($context);
    }

    /**
     * @return Json
     */
    public function execute(): Json
    {
        try {
            $result = $this->connectionTester->run();
        } catch (\Exception $e) {
            $result = [
                'success' => false,
                'message' => (string) __('Test failed unexpectedly: %1', $e->getMessage()),
                'checks'  => [],
            ];
        }

        return $this->jsonFactory->create()->setData($result);
    }
}
