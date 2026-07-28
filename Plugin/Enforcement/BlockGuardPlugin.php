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
    /** Core-block <preference> overrides — must keep rendering the page even when unlicensed. */
    private const CORE_OVERRIDE_EXEMPT = [
        'Focus\\ProductSearch\\Block\\CustomResult',
        'Focus\\ProductSearch\\Block\\Product\\CustomListing',
    ];

    /** Templates re-pointed onto a core block class, for the same reason as CORE_OVERRIDE_EXEMPT. */
    private const CORE_TEMPLATE_EXEMPT = [
        'Focus_ProductNameOnCategoryPage::productname.phtml',
    ];

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
        if (in_array($subject::class, self::CORE_OVERRIDE_EXEMPT, true)) {
            return (string) $proceed();
        }

        if ($this->enforcementGuard->getBlockedModuleForClass($subject::class) !== null) {
            return '';
        }

        if ($subject instanceof Template) {
            $template = (string) $subject->getTemplate();
            if ($template !== ''
                && str_starts_with($template, 'Focus_')
                && !in_array($template, self::CORE_TEMPLATE_EXEMPT, true)
            ) {
                $module = strtok($template, ':');
                if (is_string($module) && $this->enforcementGuard->isModuleBlocked($module)) {
                    return '';
                }
            }
        }

        return (string) $proceed();
    }
}
