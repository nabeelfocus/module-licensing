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

/**
 * Block guard — the rendering enforcement point.
 *
 * A commercial module usually injects its feature into a CORE page (a PDP, a
 * category page) by adding its own block via layout XML. Those renders are NOT
 * covered by the controller guard, because the owning controller is Magento's,
 * not the module's. This plugin closes that gap centrally: any block class
 * belonging to a guarded, unlicensed module renders as an empty string, so the
 * feature simply disappears while the host page stays completely intact.
 *
 * This is the single most important guard for "block-only" modules and removes
 * the need for a per-block hard check in every commercial module.
 *
 * Performance: toHtml() runs for every block on every page, so the hot path is
 * just the resolver's fast namespace check (non-Focus → immediate proceed),
 * memoized per request.
 */
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
