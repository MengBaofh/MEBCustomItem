<?php

declare(strict_types=1);

namespace MengBao\MEBCustomItem\Item;

use MengBao\MEBCustomItem\Main;
use pocketmine\item\Item;
use pocketmine\player\Player;

/**
 * 自定义物品管理器
 */
class CustomItemManager
{
    private Main $plugin;

    /** @var CustomItem[] */
    private array $customItems = [];

    public function __construct(Main $plugin)
    {
        $this->plugin = $plugin;
    }

    /**
     * 从配置加载所有物品
     */
    public function loadItems(): void
    {
        $this->customItems = [];

        $itemsConfig = $this->plugin->getItemsConfig();
        $items = $itemsConfig->get("items", []);

        foreach ($items as $id => $config) {
            try {
                $customItem = new CustomItem($id, $config);
                $this->customItems[$id] = $customItem;
            } catch (\Exception $e) {
                $this->plugin->getLogger()->error("加载物品 {$id} 失败: " . $e->getMessage());
            }
        }

        $this->plugin->getLogger()->info("已加载 " . count($this->customItems) . " 个自定义物品");
    }

    /**
     * 注册自定义物品
     */
    public function registerItem(CustomItem $item): void
    {
        $this->customItems[$item->getId()] = $item;
    }

    /**
     * 获取自定义物品
     */
    public function getItem(string $id): ?CustomItem
    {
        return $this->customItems[$id] ?? null;
    }

    /**
     * 获取所有自定义物品
     */
    public function getAllItems(): array
    {
        return $this->customItems;
    }

    /**
     * 获取所有物品ID
     */
    public function getAllItemIds(): array
    {
        return array_keys($this->customItems);
    }

    /**
     * 检查物品是否存在
     */
    public function itemExists(string $id): bool
    {
        return isset($this->customItems[$id]);
    }

    /**
     * 检查是否为自定义物品
     */
    public function isCustomItem(Item $item): bool
    {
        $nbt = $item->getNamedTag();
        return $nbt->getString(CustomItem::TAG_CUSTOM_ITEM, "") === "true";
    }

    /**
     * 获取物品的自定义ID
     */
    public function getCustomItemId(Item $item): ?string
    {
        if (!$this->isCustomItem($item)) {
            return null;
        }

        $nbt = $item->getNamedTag();
        return $nbt->getString(CustomItem::TAG_ITEM_ID, null);
    }

    /**
     * 通过Item实例获取CustomItem
     */
    public function getCustomItemByInstance(Item $item): ?CustomItem
    {
        $id = $this->getCustomItemId($item);
        if ($id === null) {
            return null;
        }

        return $this->getItem($id);
    }

    /**
     * 通过Item实例获取CustomItem（别名方法）
     */
    public function getCustomItemByItem(Item $item): ?CustomItem
    {
        return $this->getCustomItemByInstance($item);
    }

    /**
     * 给予玩家自定义物品
     */
    public function giveItem(Player $player, string $itemId, int $count = 1): bool
    {
        $customItem = $this->getItem($itemId);
        if ($customItem === null) {
            return false;
        }

        $item = $customItem->create($count);
        if ($item === null) {
            return false;
        }

        if (!$player->getInventory()->canAddItem($item)) {
            $player->sendMessage("§c背包空间不足！");
            return false;
        }

        $player->getInventory()->addItem($item);
        return true;
    }

    /**
     * 保存物品到配置
     */
    public function saveItemToConfig(string $id, array $config): void
    {
        $itemsConfig = $this->plugin->getItemsConfig();
        $items = $itemsConfig->get("items", []);
        $items[$id] = $config;
        $itemsConfig->set("items", $items);
        $itemsConfig->save();
    }

    /**
     * 从配置删除物品
     */
    public function deleteItemFromConfig(string $id): bool
    {
        if (!$this->itemExists($id)) {
            return false;
        }

        $itemsConfig = $this->plugin->getItemsConfig();
        $items = $itemsConfig->get("items", []);
        unset($items[$id]);
        $itemsConfig->set("items", $items);
        $itemsConfig->save();

        unset($this->customItems[$id]);
        return true;
    }

    /**
     * 获取可购买的物品
     */
    public function getShoppableItems(): array
    {
        $shoppable = [];
        foreach ($this->customItems as $id => $item) {
            if ($item->isShoppable()) {
                $shoppable[$id] = $item;
            }
        }
        return $shoppable;
    }

    /**
     * 获取可合成的物品
     */
    public function getCraftableItems(): array
    {
        $craftable = [];
        foreach ($this->customItems as $id => $item) {
            if ($item->isCraftable()) {
                $craftable[$id] = $item;
            }
        }
        return $craftable;
    }

    /**
     * 获取可掉落的物品
     */
    public function getDroppableItems(): array
    {
        $droppable = [];
        foreach ($this->customItems as $id => $item) {
            if ($item->isDroppable()) {
                $droppable[$id] = $item;
            }
        }
        return $droppable;
    }

    /**
     * 将自定义物品注册到MEBSociety商店
     */
    public function registerToMEBShop(): void
    {
        if (!$this->plugin->isMEBSocietyAvailable()) {
            return;
        }

        try {
            $mebSociety = $this->plugin->getServer()->getPluginManager()->getPlugin("MEBSociety");
            if ($mebSociety === null) {
                return;
            }

            // 获取Shop单例
            $shopClass = "MengBao\\MEBSociety\\Units\\Shop";
            if (!class_exists($shopClass)) {
                return;
            }

            $shop = $shopClass::getInstance($mebSociety);

            // 注册所有可购买的自定义物品
            foreach ($this->getShoppableItems() as $id => $item) {
                $customItemInstance = $this->createItem($id);
                if ($customItemInstance === null) continue;

                $shop->addItemShop(
                    $item->getName(),
                    $item->getBaseItem(),  // 使用基础物品ID（如 minecraft:diamond_sword）
                    1,
                    $item->getPrice(),
                    $item->getSellPrice(),
                    $customItemInstance    // 传入自定义物品实例
                );
            }

            $this->plugin->getLogger()->info("已将自定义物品注册到MEBSociety商店");
        } catch (\Exception $e) {
            $this->plugin->getLogger()->warning("注册到MEBSociety商店失败: " . $e->getMessage());
        }
    }

    /**
     * 从MEBSociety商店移除物品
     */
    public function removeFromMEBShop(string $itemId): void
    {
        if (!$this->plugin->isMEBSocietyAvailable()) {
            return;
        }

        try {
            $mebSociety = $this->plugin->getServer()->getPluginManager()->getPlugin("MEBSociety");
            if ($mebSociety === null) {
                return;
            }

            // 获取Shop单例
            $shopClass = "MengBao\\MEBSociety\\Units\\Shop";
            if (!class_exists($shopClass)) {
                return;
            }

            $shop = $shopClass::getInstance($mebSociety);

            // 根据NBT中的CustomItemId删除商品
            if (method_exists($shop, 'removeItemByCustomId')) {
                $shop->removeItemByCustomId($itemId);
            } else {
                // 降级方案：遍历所有商品查找匹配的CustomItemId
                $shops = $shop->getAllShops();
                foreach ($shops as $sid => $shopItem) {
                    if ($shopItem["类型"] === "item" && !empty($shopItem["物品NBT"])) {
                        // 检查NBT中的CustomItemId
                        $nbt = @unserialize(base64_decode($shopItem["物品NBT"]));
                        if ($nbt && isset($nbt->getValue()["CustomItemId"])) {
                            $customId = $nbt->getValue()["CustomItemId"]->getValue();
                            if ($customId === $itemId) {
                                $shop->delShop($sid);
                            }
                        }
                    }
                }
            }

            $this->plugin->getLogger()->info("已从MEBSociety商店移除物品: {$itemId}");
        } catch (\Exception $e) {
            $this->plugin->getLogger()->warning("从MEBSociety商店移除失败: " . $e->getMessage());
        }
    }
}
