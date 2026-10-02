<?php

declare(strict_types=1);

namespace MengBao\MEBCustomItem\Listener;

use MengBao\MEBCustomItem\Main;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerItemUseEvent;
use pocketmine\event\player\PlayerItemConsumeEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\player\Player;

/**
 * 物品使用监听器
 */
class ItemUseListener implements Listener
{
    private Main $plugin;

    public function __construct(Main $plugin)
    {
        $this->plugin = $plugin;
    }

    /**
     * 监听物品使用
     */
    public function onItemUse(PlayerItemUseEvent $event): void
    {
        $player = $event->getPlayer();
        $item = $event->getItem();

        // 检查是否为自定义物品
        if (!$this->plugin->getItemManager()->isCustomItem($item)) {
            return;
        }

        $customItem = $this->plugin->getItemManager()->getCustomItemByInstance($item);
        if ($customItem === null) {
            return;
        }

        // 检查冷却时间
        if ($customItem->isOnCooldown($item, "on_use")) {
            $player->sendMessage("§c技能冷却中...");
            $event->cancel();
            return;
        }

        // 应用使用效果
        if ($customItem->applyEffect($player, "on_use", $item, $event)) {
            // 消耗成本
            if (!$customItem->consumeCost($player, $item, "on_use")) {
                $player->sendMessage("§c物品已损坏！");
                $player->getInventory()->setItemInHand($item->setCount(0));
                return;
            }

            // 设置冷却时间
            $customItem->setLastUseTime($item, "on_use");

            // 更新物品
            $player->getInventory()->setItemInHand($item);
        }
    }

    /**
     * 监听物品消耗（吃、喝）
     */
    public function onItemConsume(PlayerItemConsumeEvent $event): void
    {
        $player = $event->getPlayer();
        $item = $event->getItem();

        // 检查是否为自定义物品
        if (!$this->plugin->getItemManager()->isCustomItem($item)) {
            return;
        }

        $customItem = $this->plugin->getItemManager()->getCustomItemByInstance($item);
        if ($customItem === null) {
            return;
        }

        // 应用消耗效果
        $customItem->applyEffect($player, "on_consume", $item, $event);
    }

    /**
     * 监听实体受伤（攻击触发）
     */
    public function onEntityDamage(EntityDamageByEntityEvent $event): void
    {
        $damager = $event->getDamager();

        // 只处理玩家攻击
        if (!$damager instanceof Player) {
            return;
        }

        $item = $damager->getInventory()->getItemInHand();

        // 检查是否为自定义物品
        if (!$this->plugin->getItemManager()->isCustomItem($item)) {
            return;
        }

        $customItem = $this->plugin->getItemManager()->getCustomItemByInstance($item);
        if ($customItem === null) {
            return;
        }

        // 应用攻击效果
        $customItem->applyEffect($damager, "on_attack", $item, $event);

        // 应用额外伤害
        $attributes = $customItem->getAttributes();
        if (isset($attributes["damage"])) {
            $extraDamage = (float)$attributes["damage"];
            $event->setBaseDamage($event->getBaseDamage() + $extraDamage);
        }
    }
}
