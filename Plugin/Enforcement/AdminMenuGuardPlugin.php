<?php
declare(strict_types=1);

/**
 * Copyright © Focus. All rights reserved.
 * @author Focus Team
 * @package Focus_Licensing
 */

namespace Focus\Licensing\Plugin\Enforcement;

use Focus\Licensing\Logger\Logger;
use Focus\Licensing\Model\Enforcement\EnforcementGuard;
use Magento\Backend\Model\Menu;
use Magento\Backend\Model\Menu\Config;
use Magento\Backend\Model\Menu\Item;

class AdminMenuGuardPlugin
{
    /**
     * @param EnforcementGuard $enforcementGuard
     * @param Logger $logger
     */
    public function __construct(
        private readonly EnforcementGuard $enforcementGuard,
        private readonly Logger $logger
    ) {}

    /**
     * @param Config $subject
     * @param Menu $menu
     * @return Menu
     */
    public function afterGetMenu(Config $subject, Menu $menu): Menu
    {
        try {
            $blockedIds = [];
            $this->collectBlockedIds($menu, $blockedIds);
            foreach ($blockedIds as $id) {
                $menu->remove($id);
            }
        } catch (\Throwable $e) {
            $this->logger->error('Focus_Licensing: admin menu guard failed (fail-open)', [
                'exception' => $e->getMessage(),
            ]);
        }

        return $menu;
    }

    /**
     * @param iterable<Item> $menu
     * @param string[] $blockedIds
     */
    private function collectBlockedIds(iterable $menu, array &$blockedIds): void
    {
        foreach ($menu as $item) {
            /** @var Item $item */
            $id = (string) $item->getId();
            $module = strtok($id, ':');

            if (is_string($module)
                && str_starts_with($module, 'Focus_')
                && $this->enforcementGuard->isModuleBlocked($module)
            ) {
                $blockedIds[] = $id;
                continue;
            }

            if ($item->hasChildren()) {
                $this->collectBlockedIds($item->getChildren(), $blockedIds);
            }
        }
    }
}
