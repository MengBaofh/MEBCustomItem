<?php

declare(strict_types=1);

namespace MengBao\MEBCustomItem\Listener;

use MengBao\MEBCustomItem\Main;
use pocketmine\event\Listener;
use pocketmine\event\block\BlockBreakEvent;

/**
 * 方块破坏监听器（方块掉落）
 */
class BlockBreakListener implements Listener
{
    private Main $plugin;

    public function __construct(Main $plugin)
    {
        $this->plugin = $plugin;
    }

    /**
     * 监听方块破坏
     */
    public function onBlockBreak(BlockBreakEvent $event): void
    {
        $block = $event->getBlock();
        $player = $event->getPlayer();

        // 获取方块类型
        $blockType = $this->getBlockType($block);
        if ($blockType === null) {
            return;
        }

        // 获取该方块的掉落配置
        $recipesConfig = $this->plugin->getRecipesConfig();
        $blockDrops = $recipesConfig->get("block_drops", []);

        if (!isset($blockDrops[$blockType])) {
            return;
        }

        $drops = $blockDrops[$blockType];

        // 处理每个掉落物品
        foreach ($drops as $drop) {
            $itemId = $drop["item_id"] ?? "";
            $chance = (float)($drop["chance"] ?? 0);
            $min = (int)($drop["min"] ?? 1);
            $max = (int)($drop["max"] ?? 1);
            $requireTool = (bool)($drop["require_tool"] ?? false);

            // 检查是否需要工具
            if ($requireTool) {
                $item = $player->getInventory()->getItemInHand();
                if (!$this->isValidTool($item, $block)) {
                    continue;
                }
            }

            // 随机判断是否掉落
            if (mt_rand(1, 10000) > ($chance * 100)) {
                continue;
            }

            // 获取自定义物品
            $customItem = $this->plugin->getItemManager()->getItem($itemId);
            if ($customItem === null) {
                continue;
            }

            // 随机数量
            $count = mt_rand($min, $max);
            $item = $customItem->create($count);

            if ($item !== null) {
                // 在方块位置掉落物品
                $block->getPosition()->getWorld()->dropItem($block->getPosition(), $item);
            }
        }
    }

    /**
     * 获取方块类型名称
     */
    private function getBlockType($block): ?string
    {
        $class = get_class($block);
        $parts = explode("\\", $class);
        $name = end($parts);

        // 转换为小写并转为下划线命名
        $name = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $name));

        return $name;
    }

    /**
     * 检查工具是否有效
     */
    private function isValidTool($item, $block): bool
    {
        // 简单检查：只要拿着工具就可以
        return !$item->isNull();
    }
}
