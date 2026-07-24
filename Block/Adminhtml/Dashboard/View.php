<?php
declare(strict_types=1);

/**
 * Copyright © Focus. All rights reserved.
 * @author Focus Team
 * @package Focus_Licensing
 */

namespace Focus\Licensing\Block\Adminhtml\Dashboard;

use Focus\Licensing\ViewModel\Dashboard\Activity;
use Focus\Licensing\ViewModel\Dashboard\DeveloperInfo;
use Focus\Licensing\ViewModel\Dashboard\Diagnostics;
use Focus\Licensing\ViewModel\Dashboard\Domains;
use Focus\Licensing\ViewModel\Dashboard\LicenseState;
use Focus\Licensing\ViewModel\Dashboard\ModuleStatus;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;

class View extends Template
{
    protected $_template = 'Focus_Licensing::dashboard/main.phtml';

    /**
     * @param Context $context
     * @param LicenseState $licenseState
     * @param ModuleStatus $moduleStatus
     * @param Diagnostics $diagnostics
     * @param Domains $domains
     * @param DeveloperInfo $developerInfo
     * @param Activity $activity
     * @param array $data
     */
    public function __construct(
        Context $context,
        private readonly LicenseState $licenseState,
        private readonly ModuleStatus $moduleStatus,
        private readonly Diagnostics $diagnostics,
        private readonly Domains $domains,
        private readonly DeveloperInfo $developerInfo,
        private readonly Activity $activity,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * @return LicenseState
     */
    public function getLicenseState(): LicenseState
    {
        return $this->licenseState;
    }

    /**
     * Render a card template with the shared view models available.
     */
    public function renderCard(string $template, array $data = []): string
    {
        /** @var Template $block */
        $block = $this->getLayout()->createBlock(Template::class);
        $block->setTemplate($template);
        $block->setData($data);
        $block->setData('license_state', $this->licenseState);
        $block->setData('module_status', $this->moduleStatus);
        $block->setData('diagnostics', $this->diagnostics);
        $block->setData('domains', $this->domains);
        $block->setData('developer_info', $this->developerInfo);
        $block->setData('activity', $this->activity);

        return $block->toHtml();
    }

    /**
     * URLs for the AJAX action buttons, consumed by license-actions.js.
     */
    public function getActionUrlsJson(): string
    {
        return (string) json_encode([
            'validate'   => $this->getUrl('focus_licensing/license/validate'),
            'refresh'    => $this->getUrl('focus_licensing/license/refresh'),
            'deactivate' => $this->getUrl('focus_licensing/license/deactivate'),
            'test'       => $this->getUrl('focus_licensing/license/testconnection'),
            'release'    => $this->getUrl('focus_licensing/license/releasedomain'),
        ]);
    }
}
