<?php

declare(strict_types=1);

namespace MengBao\MEBCustomItem\Listener;

use MengBao\MEBCustomItem\Main;
use pocketmine\event\Listener;
use pocketmine\event\entity\EntityDeathEvent;
use pocketmine\entity\Living;

/**
 * 实体死亡监听器（怪物掉落）
 */
class EntityDeathListener implements Listener
{
    private Main $plugin;

    public function __construct(Main $plugin)
    {
        $this->plugin = $plugin;
    }

    /**
     * 监听实体死亡
     */
    public function onEntityDeath(EntityDeathEvent $event): void
    {
        $entity = $event->getEntity();

        // 获取实体类型
        $entityType = $this->getEntityType($entity);
        if ($entityType === null) {
            return;
        }

        // 获取该实体的掉落配置
        $recipesConfig = $this->plugin->getRecipesConfig();
        $mobDrops = $recipesConfig->get("mob_drops", []);

        if (!isset($mobDrops[$entityType])) {
            return;
        }

        $drops = $mobDrops[$entityType];

        // 处理每个掉落物品
        foreach ($drops as $drop) {
            $itemId = $drop["item_id"] ?? "";
            $chance = (float)($drop["chance"] ?? 0);
            $min = (int)($drop["min"] ?? 1);
            $max = (int)($drop["max"] ?? 1);

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
                // 添加到掉落物
                $drops = $event->getDrops();
                $drops[] = $item;
                $event->setDrops($drops);
            }
        }
    }

    /**
     * 获取实体类型名称
     */
    private function getEntityType(Living $entity): ?string
    {
        $class = get_class($entity);
        $parts = explode("\\", $class);
        $name = end($parts);

        // 转换为小写并移除前缀
        $name = strtolower($name);

        // 常见实体类型映射
        $typeMap = [
            "zombie" => "zombie",
            "skeleton" => "skeleton",
            "creeper" => "creeper",
            "spider" => "spider",
            "enderman" => "enderman",
            "blaze" => "blaze",
            "witch" => "witch",
            "pig" => "pig",
            "cow" => "cow",
            "sheep" => "sheep",
            "chicken" => "chicken",
        ];

        return $typeMap[$name] ?? null;
    }
}
