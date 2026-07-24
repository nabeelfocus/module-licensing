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
