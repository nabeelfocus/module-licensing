<?php
declare(strict_types=1);

/**
 * Copyright © Focus. All rights reserved.
 * @author Focus Team
 * @package Focus_Licensing
 */

namespace Focus\Licensing\Plugin\Enforcement;

use Focus\Licensing\Model\Enforcement\EnforcementGuard;
use Magento\Framework\View\Element\AbstractBlock;
use Magento\Framework\View\Element\Template;

class BlockGuardPlugin
{
    /**
     * @param EnforcementGuard $enforcementGuard
     */
    public function __construct(
        private readonly EnforcementGuard $enforcementGuard
    ) {}

    /**
     * @param AbstractBlock $subject
     * @param callable $proceed
     * @return string
     */
    public function aroundToHtml(AbstractBlock $subject, callable $proceed): string
    {
        // 1. The block class itself belongs to a guarded, unlicensed module.
        if ($this->enforcementGuard->getBlockedModuleForClass($subject::class) !== null) {
            return '';
        }

        // 2. A CORE block class rendering a commercial module's template — the
        //    common "add my feature to the PDP" pattern:
        //      <block class="Magento\Catalog\Block\Product\View"
        //             template="Focus_MattressAddon::mattressaddon.phtml">
        //    The class is Magento's, so only the template reveals the owner.
        if ($subject instanceof Template) {
            $template = (string) $subject->getTemplate();
            if ($template !== '' && str_starts_with($template, 'Focus_')) {
                $module = strtok($template, ':');
                if (is_string($module) && $this->enforcementGuard->isModuleBlocked($module)) {
                    return '';
                }
            }
        }

        return (string) $proceed();
    }
}
