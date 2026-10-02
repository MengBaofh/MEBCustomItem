<?php

declare(strict_types=1);

namespace MengBao\MEBCustomItem\Listener;

use MengBao\MEBCustomItem\Main;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\player\Player;

/**
 * 玩家监听器
 */
class PlayerListener implements Listener
{
    private Main $plugin;

    /** @var array<string, float> 缓存每个玩家的速度倍率 */
    private array $playerSpeedCache = [];

    public function __construct(Main $plugin)
    {
        $this->plugin = $plugin;
    }

    /**
     * 玩家加入
     */
    public function onPlayerJoin(PlayerJoinEvent $event): void
    {
        // 玩家加入时无需特殊处理，定时任务会处理效果应用
    }

    /**
     * 玩家退出
     */
    public function onPlayerQuit(PlayerQuitEvent $event): void
    {
        // 玩家退出时清理缓存
        $playerName = $event->getPlayer()->getName();
        unset($this->playerSpeedCache[$playerName]);
    }

    /**
     * 检查并应用玩家装备的持有效果
     */
    public function checkAndApplyHoldEffects(Player $player): void
    {
        $itemManager = $this->plugin->getItemManager();

        // 重置速度倍率（默认为1.0）
        $speedMultiplier = 1.0;

        // 检查手持物品
        $handItem = $player->getInventory()->getItemInHand();
        $this->applyItemHoldEffect($player, $handItem);
        $speedMultiplier = $this->getSpeedMultiplier($handItem, $speedMultiplier);

        // 检查盔甲装备
        $armorInventory = $player->getArmorInventory();

        $helmet = $armorInventory->getHelmet();
        $this->applyItemHoldEffect($player, $helmet);
        $speedMultiplier = $this->getSpeedMultiplier($helmet, $speedMultiplier);

        $chestplate = $armorInventory->getChestplate();
        $this->applyItemHoldEffect($player, $chestplate);
        $speedMultiplier = $this->getSpeedMultiplier($chestplate, $speedMultiplier);

        $leggings = $armorInventory->getLeggings();
        $this->applyItemHoldEffect($player, $leggings);
        $speedMultiplier = $this->getSpeedMultiplier($leggings, $speedMultiplier);

        $boots = $armorInventory->getBoots();
        $this->applyItemHoldEffect($player, $boots);
        $speedMultiplier = $this->getSpeedMultiplier($boots, $speedMultiplier);

        // 只在速度倍率改变时才应用，避免频繁重复设置
        $playerName = $player->getName();
        $cachedMultiplier = $this->playerSpeedCache[$playerName] ?? null;

        if ($cachedMultiplier === null || abs($cachedMultiplier - $speedMultiplier) > 0.001) {
            $this->applySpeedMultiplier($player, $speedMultiplier);
            $this->playerSpeedCache[$playerName] = $speedMultiplier;
        }
    }

    /**
     * 获取物品的速度倍率
     */
    private function getSpeedMultiplier(\pocketmine\item\Item $item, float $currentMultiplier): float
    {
        if ($item->isNull()) {
            return $currentMultiplier;
        }

        $customItem = $this->plugin->getItemManager()->getCustomItemByItem($item);
        if ($customItem === null) {
            return $currentMultiplier;
        }

        $attributes = $customItem->getAttributes();
        if (isset($attributes["speed_multiplier"]) && $attributes["speed_multiplier"] > 0) {
            // 速度倍率叠加（相乘）
            return $currentMultiplier * (float)$attributes["speed_multiplier"];
        }

        return $currentMultiplier;
    }

    /**
     * 应用速度倍率到玩家
     */
    private function applySpeedMultiplier(Player $player, float $multiplier): void
    {
        // 获取玩家的移动速度属性
        $movement = $player->getMovementSpeed();
        $baseSpeed = 0.1; // 玩家默认移动速度

        // 计算新的移动速度
        $newSpeed = $baseSpeed * $multiplier;

        // 限制速度范围（避免过快或过慢）
        $newSpeed = max(0.01, min($newSpeed, 1.0));

        // 设置玩家移动速度
        $player->setMovementSpeed($newSpeed);
    }

    /**
     * 应用单个物品的持有效果
     */
    private function applyItemHoldEffect(Player $player, \pocketmine\item\Item $item): void
    {
        if ($item->isNull()) {
            return;
        }

        $customItem = $this->plugin->getItemManager()->getCustomItemByItem($item);
        if ($customItem === null) {
            return;
        }

        // 应用 on_hold 效果
        $customItem->applyEffect($player, "on_hold", $item);
    }
}
