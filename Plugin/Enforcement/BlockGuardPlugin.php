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
     * @param string[] $coreOverrideExempt Block classes standing in for a core Magento block via <preference> — populated per-project, never here.
     * @param string[] $coreTemplateExempt Templates re-pointed onto a core block class, for the same reason.
     */
    public function __construct(
        private readonly EnforcementGuard $enforcementGuard,
        private readonly array $coreOverrideExempt = [],
        private readonly array $coreTemplateExempt = []
    ) {}

    /**
     * @param AbstractBlock $subject
     * @param callable $proceed
     * @return string
     */
    public function aroundToHtml(AbstractBlock $subject, callable $proceed): string
    {
        if (in_array($subject::class, $this->coreOverrideExempt, true)) {
            return (string) $proceed();
        }

        if ($this->enforcementGuard->getBlockedModuleForClass($subject::class) !== null) {
            return '';
        }

        if ($subject instanceof Template) {
            $template = (string) $subject->getTemplate();
            if ($template !== ''
                && str_starts_with($template, 'Focus_')
                && !in_array($template, $this->coreTemplateExempt, true)
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
