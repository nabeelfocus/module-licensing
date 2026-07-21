<?php
declare(strict_types=1);

namespace Focus\Licensing\Plugin\Enforcement;

use Focus\Licensing\Logger\Logger;
use Focus\Licensing\Model\Enforcement\EnforcementGuard;
use Magento\Backend\Model\Menu;
use Magento\Backend\Model\Menu\Config;
use Magento\Backend\Model\Menu\Item;

/**
 * Admin menu guard — hides admin menu items owned by guarded, unlicensed
 * modules. The controller guard already blocks the underlying routes; hiding
 * the menu entry removes the dead link so the admin never sees an option that
 * only leads to a "not licensed" redirect.
 *
 * Menu item ids follow the Vendor_Module::resource convention, so the owning
 * module is the segment before ":."
 */
class AdminMenuGuardPlugin
{
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
            // Fail open: never hide/break the admin menu because of an internal error.
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
                // Removing the parent removes its whole subtree — no need to recurse.
                $blockedIds[] = $id;
                continue;
            }

            if ($item->hasChildren()) {
                $this->collectBlockedIds($item->getChildren(), $blockedIds);
            }
        }
    }
}
