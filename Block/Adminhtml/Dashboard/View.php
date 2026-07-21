<?php
declare(strict_types=1);

namespace Focus\Licensing\Block\Adminhtml\Dashboard;

use Focus\Licensing\ViewModel\Dashboard\DeveloperInfo;
use Focus\Licensing\ViewModel\Dashboard\Diagnostics;
use Focus\Licensing\ViewModel\Dashboard\Domains;
use Focus\Licensing\ViewModel\Dashboard\LicenseState;
use Focus\Licensing\ViewModel\Dashboard\ModuleStatus;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;

/**
 * Root block of the licensing dashboard (rendered at the top of the
 * Stores > Configuration > Focus > Licensing section, and re-rendered by the
 * AJAX action controllers so the page never needs a manual reload).
 *
 * Cards live in their own templates under dashboard/card/ and are rendered
 * through renderCard() so each card stays independently replaceable.
 */
class View extends Template
{
    protected $_template = 'Focus_Licensing::dashboard/main.phtml';

    public function __construct(
        Context $context,
        private readonly LicenseState $licenseState,
        private readonly ModuleStatus $moduleStatus,
        private readonly Diagnostics $diagnostics,
        private readonly Domains $domains,
        private readonly DeveloperInfo $developerInfo,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

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
        ]);
    }
}
