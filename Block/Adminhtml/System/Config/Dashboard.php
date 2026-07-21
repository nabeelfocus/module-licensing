<?php
declare(strict_types=1);

/**
 * Copyright © Focus. All rights reserved.
 * @author Focus Team
 * @package Focus_Licensing
 */

namespace Focus\Licensing\Block\Adminhtml\System\Config;

use Focus\Licensing\Block\Adminhtml\Dashboard\View;
use Magento\Config\Block\System\Config\Form\Fieldset;
use Magento\Framework\Data\Form\Element\AbstractElement;

/**
 * frontend_model of the (field-less) "dashboard" group in system.xml.
 * Replaces the standard fieldset chrome with the licensing dashboard block,
 * so the section opens on a product-style dashboard instead of a form.
 */
class Dashboard extends Fieldset
{
    /**
     * @param AbstractElement $element
     */
    public function render(AbstractElement $element)
    {
        return $this->getLayout()
            ->createBlock(View::class, 'focus_licensing_dashboard')
            ->toHtml();
    }
}
