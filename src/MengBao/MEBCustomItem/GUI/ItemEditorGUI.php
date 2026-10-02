<?php

declare(strict_types=1);

namespace MengBao\MEBCustomItem\GUI;

use MengBao\MEBCustomItem\Main;
use MengBao\MEBForms\SimpleForm;
use MengBao\MEBForms\CustomForm;
use MengBao\MEBForms\ModalForm;
use pocketmine\player\Player;
use pocketmine\utils\Config;

/**
 * 物品编辑器 GUI
 */
class ItemEditorGUI
{
    private Main $plugin;

    public function __construct(Main $plugin)
    {
        $this->plugin = $plugin;
    }

    /**
     * 主菜单
     */
    public function sendMainMenu(Player $player): void
    {
        $form = new SimpleForm(function (Player $player, ?int $data) {
            if ($data === null) return;

            switch ($data) {
                case 0:
                    $this->sendItemListMenu($player, 0);
                    break;
                case 1:
                    $recipeEditor = $this->plugin->getRecipeEditorGUI();
                    if ($recipeEditor !== null) {
                        $recipeEditor->sendRecipeListMenu($player, 0);
                    }
                    break;
                case 2:
                    $this->sendReloadMenu($player);
                    break;
            }
        });

        $form->setTitle("§l§6自定义物品管理");
        $form->setContent("§f选择一个操作：");
        $form->addButton("§l§a查看/编辑物品\n§r§f管理现有物品", 0, "textures/ui/icon_recipe_item");
        $form->addButton("§l§e查看/编辑配方\n§r§f管理合成配方", 0, "textures/ui/recipe_book_icon");
        $form->addButton("§l§3重载配置\n§r§f重新加载所有配置", 0, "textures/ui/refresh");

        $player->sendForm($form);
    }

    /**
     * 物品列表菜单（分页）
     */
    public function sendItemListMenu(Player $player, int $page = 0): void
    {
        $items = $this->plugin->getItemManager()->getAllItems();
        $itemsPerPage = 10; // 每页显示10个物品
        $totalItems = count($items);
        $totalPages = max(1, (int)ceil($totalItems / $itemsPerPage));
        $page = max(0, min($page, $totalPages - 1));

        $itemIds = array_keys($items);
        $startIndex = $page * $itemsPerPage;
        $endIndex = min($startIndex + $itemsPerPage, $totalItems);
        $pageItems = array_slice($itemIds, $startIndex, $itemsPerPage, true);

        $form = new SimpleForm(function (Player $player, ?int $data) use ($items, $page, $totalPages, $pageItems) {
            if ($data === null) {
                $this->sendMainMenu($player);
                return;
            }

            $buttonIndex = 0;

            // 创建新物品按钮
            if ($data === $buttonIndex) {
                $this->sendCreateItemMenu($player);
                return;
            }
            $buttonIndex++;

            // 物品选择按钮
            $itemCount = count($pageItems);
            if ($data >= $buttonIndex && $data < $buttonIndex + $itemCount) {
                $itemIndex = $data - $buttonIndex;
                $itemIdsArray = array_values($pageItems);
                if (isset($itemIdsArray[$itemIndex])) {
                    $selectedId = $itemIdsArray[$itemIndex];
                    $this->sendItemEditMenu($player, $selectedId);
                }
                return;
            }
            $buttonIndex += $itemCount;

            // 上一页按钮（仅在不是第一页时显示）
            if ($page > 0) {
                if ($data === $buttonIndex) {
                    $this->sendItemListMenu($player, $page - 1);
                    return;
                }
                $buttonIndex++;
            }

            // 下一页按钮（仅在不是最后一页时显示）
            if ($page < $totalPages - 1) {
                if ($data === $buttonIndex) {
                    $this->sendItemListMenu($player, $page + 1);
                    return;
                }
                $buttonIndex++;
            }

            // 返回按钮（最后一个）
            if ($data === $buttonIndex) {
                $this->sendMainMenu($player);
                return;
            }
        });

        $form->setTitle("§l§a物品列表");
        $form->setContent("§f共 §e{$totalItems} §f个物品 | 第 §e" . ($page + 1) . "§f/§e{$totalPages} §f页\n§f点击物品进行编辑：");

        // 创建新物品按钮
        $form->addButton("§l§b创建新物品\n§r§f添加自定义物品", 0, "textures/ui/color_plus");

        // 显示当前页的物品
        foreach ($pageItems as $id) {
            $item = $items[$id];
            $name = $item->getName();
            $baseItem = $item->getBaseItem();
            $form->addButton("§l{$name}\n§r§fID: {$id}", 0, "textures/items/diamond");
        }

        // 上一页按钮（仅在不是第一页时显示）
        if ($page > 0) {
            $form->addButton("§l§e◀ 上一页", 0, "textures/ui/arrow_left");
        }

        // 下一页按钮（仅在不是最后一页时显示）
        if ($page < $totalPages - 1) {
            $form->addButton("§l§e下一页 ▶", 0, "textures/ui/arrow_right");
        }

        // 返回按钮放在最后，使用与分页相同的图标
        $form->addButton("§l§c返回主菜单", 0, "textures/ui/arrow_left");

        $player->sendForm($form);
    }

    /**
     * 创建新物品菜单
     */
    public function sendCreateItemMenu(Player $player): void
    {
        $form = new CustomForm(function (Player $player, ?array $data) {
            if ($data === null) {
                $this->sendMainMenu($player);
                return;
            }

            $itemId = strtolower(trim($data[0]));
            $itemName = trim($data[1]);
            $baseItem = trim($data[2]);

            // 验证输入
            if (empty($itemId) || empty($itemName) || empty($baseItem)) {
                $player->sendMessage("§c错误：所有字段都是必填的！");
                return;
            }

            // 检查ID是否已存在
            if ($this->plugin->getItemManager()->getItem($itemId) !== null) {
                $player->sendMessage("§c错误：物品ID '{$itemId}' 已存在！");
                return;
            }

            // 创建物品配置
            $itemConfig = [
                "name" => $itemName,
                "lore" => [],
                "base_item" => $baseItem,
                "custom_model" => false,
                "texture_path" => "",
                "model_path" => "",
                "count" => 1,
                "enchantments" => [],
                "attributes" => [],
                "effects" => [],
                "obtainable" => [
                    "craft" => true,
                    "drop" => false,
                    "shop" => false,
                    "price" => 0,
                    "sell_price" => 0
                ]
            ];

            // 保存到配置文件
            $this->saveItemToConfig($itemId, $itemConfig);

            // 重新加载物品管理器
            $this->plugin->reload();

            $player->sendMessage("§a物品创建成功！ID: {$itemId}");
            $player->sendMessage("§7你可以继续编辑此物品来添加更多属性。");

            // 打开编辑菜单
            $this->sendItemEditMenu($player, $itemId);
        });

        $form->setTitle("§l§b创建新物品");
        $form->addInput("§7物品ID §c*\n§8小写字母+下划线，例如: flame_sword", "flame_sword");
        $form->addInput("§7显示名称 §c*\n§8支持颜色代码，例如: §c§l烈焰之剑", "§c§l烈焰之剑");
        $form->addInput("§7基础物品 §c*\n§8原版物品ID，例如: minecraft:diamond_sword", "minecraft:diamond_sword");

        $player->sendForm($form);
    }

    /**
     * 物品编辑菜单
     */
    public function sendItemEditMenu(Player $player, string $itemId): void
    {
        $item = $this->plugin->getItemManager()->getItem($itemId);
        if ($item === null) {
            $player->sendMessage("§c物品不存在！");
            return;
        }

        $form = new SimpleForm(function (Player $player, ?int $data) use ($itemId) {
            if ($data === null) {
                $this->sendItemListMenu($player, 0);
                return;
            }

            switch ($data) {
                case 0:
                    $this->sendEditBasicInfoMenu($player, $itemId);
                    break;
                case 1:
                    $this->sendEditLoreMenu($player, $itemId);
                    break;
                case 2:
                    $this->sendEditEnchantmentsMenu($player, $itemId);
                    break;
                case 3:
                    $this->sendEditAttributesMenu($player, $itemId);
                    break;
                case 4:
                    $this->sendEditEffectsMenu($player, $itemId);
                    break;
                case 5:
                    $this->sendEditObtainableMenu($player, $itemId);
                    break;
                case 6:
                    $this->sendDeleteItemConfirm($player, $itemId);
                    break;
                case 7:
                    $this->sendItemListMenu($player, 0);
                    break;
            }
        });

        $form->setTitle("§l§e编辑物品");
        $form->setContent("§fID: §e{$itemId}\n§f名称: {$item->getName()}\n\n§f选择要编辑的部分：");

        $form->addButton("§l§a基础信息\n§r§f名称、基础物品等", 0, "textures/ui/icon_setting");
        $form->addButton("§l§b物品描述\n§r§fLore文本", 0, "textures/ui/book_edit_default");
        $form->addButton("§l§d附魔\n§r§f添加附魔效果", 0, "textures/ui/enchanting_table");
        $form->addButton("§l§6属性\n§r§f耐久、伤害等", 0, "textures/ui/strength_effect");
        $form->addButton("§l§3特殊效果\n§r§f使用、攻击效果", 0, "textures/ui/icon_recipe_nature");
        $form->addButton("§l§2获取方式\n§r§f合成、商店等", 0, "textures/ui/trade_icon");
        $form->addButton("§l§4删除物品\n§r§f永久删除", 0, "textures/ui/realms_red_x");
        $form->addButton("§l§c返回物品列表", 0, "textures/ui/arrow_left");

        $player->sendForm($form);
    }

    /**
     * 编辑基础信息
     */
    public function sendEditBasicInfoMenu(Player $player, string $itemId): void
    {
        $config = $this->getItemConfig($itemId);
        if ($config === null) {
            $player->sendMessage("§c物品配置不存在！");
            return;
        }

        $form = new CustomForm(function (Player $player, ?array $data) use ($itemId, $config) {
            if ($data === null) {
                $this->sendItemEditMenu($player, $itemId);
                return;
            }

            $config["name"] = trim($data[0]);
            $config["base_item"] = trim($data[1]);
            $config["count"] = max(1, (int)$data[2]);
            $config["custom_model"] = (bool)$data[3];
            $config["texture_path"] = trim($data[4]);
            $config["model_path"] = trim($data[5]);

            $this->saveItemToConfig($itemId, $config);
            $this->plugin->reload();

            $player->sendMessage("§a基础信息已更新！");
            $this->sendItemEditMenu($player, $itemId);
        });

        $form->setTitle("§l§a编辑基础信息");
        $form->addInput("显示名称", "§c§l烈焰之剑", $config["name"] ?? "");
        $form->addInput("基础物品", "minecraft:diamond_sword", $config["base_item"] ?? "");
        $form->addInput("默认数量", "1", (string)($config["count"] ?? 1));
        $form->addToggle("启用自定义模型", $config["custom_model"] ?? false);
        $form->addInput("材质路径 (可选)", "textures/items/...", $config["texture_path"] ?? "");
        $form->addInput("模型路径 (可选)", "models/items/...", $config["model_path"] ?? "");

        $player->sendForm($form);
    }

    /**
     * 编辑Lore描述
     */
    public function sendEditLoreMenu(Player $player, string $itemId): void
    {
        $config = $this->getItemConfig($itemId);
        if ($config === null) {
            $player->sendMessage("§c物品配置不存在！");
            return;
        }

        $lore = $config["lore"] ?? [];
        $loreText = implode("\n", $lore);

        $form = new CustomForm(function (Player $player, ?array $data) use ($itemId, $config) {
            if ($data === null) {
                $this->sendItemEditMenu($player, $itemId);
                return;
            }

            $loreText = trim($data[0]);
            $config["lore"] = empty($loreText) ? [] : explode("\n", $loreText);

            $this->saveItemToConfig($itemId, $config);
            $this->plugin->reload();

            $player->sendMessage("§a物品描述已更新！");
            $this->sendItemEditMenu($player, $itemId);
        });

        $form->setTitle("§l§b编辑物品描述");
        $form->addLabel("§7每行一条描述，支持颜色代码\n§7例如: §7§o这是一把神奇的剑");
        $form->addInput("物品描述 (多行)", "§7第一行描述\n§e第二行描述", $loreText);

        $player->sendForm($form);
    }

    /**
     * 编辑附魔菜单
     */
    public function sendEditEnchantmentsMenu(Player $player, string $itemId): void
    {
        $config = $this->getItemConfig($itemId);
        if ($config === null) {
            $player->sendMessage("§c物品配置不存在！");
            return;
        }

        $enchantments = $config["enchantments"] ?? [];

        $form = new SimpleForm(function (Player $player, ?int $data) use ($itemId, $enchantments) {
            if ($data === null || $data === 0) {
                $this->sendItemEditMenu($player, $itemId);
                return;
            }

            if ($data === 1) {
                $this->sendAddEnchantmentMenu($player, $itemId);
                return;
            }

            // 删除附魔
            $index = $data - 2;
            if (isset($enchantments[$index])) {
                $this->removeEnchantment($itemId, $index);
                $player->sendMessage("§a附魔已删除！");
            }
            $this->sendEditEnchantmentsMenu($player, $itemId);
        });

        $form->setTitle("§l§d编辑附魔");
        $form->setContent("§7当前附魔数量: §e" . count($enchantments));

        $form->addButton("§l§c返回", 0, "textures/ui/arrow_left");
        $form->addButton("§l§a添加附魔", 0, "textures/ui/color_plus");

        foreach ($enchantments as $enchant) {
            $id = $enchant["id"] ?? 0;
            $level = $enchant["level"] ?? 1;
            $form->addButton("§l§e附魔ID: {$id}\n§r§7等级: {$level} §c[删除]", 0, "textures/ui/trash");
        }

        $player->sendForm($form);
    }

    /**
     * 添加附魔
     */
    public function sendAddEnchantmentMenu(Player $player, string $itemId): void
    {
        $form = new CustomForm(function (Player $player, ?array $data) use ($itemId) {
            if ($data === null) {
                $this->sendEditEnchantmentsMenu($player, $itemId);
                return;
            }

            $enchantId = (int)$data[0];
            $level = max(1, (int)$data[1]);

            $this->addEnchantment($itemId, $enchantId, $level);
            $player->sendMessage("§a附魔已添加！");
            $this->sendEditEnchantmentsMenu($player, $itemId);
        });

        $form->setTitle("§l§a添加附魔");
        $form->addInput("附魔ID", "9", "9");
        $form->addInput("等级", "5", "5");
        $form->addLabel("§7常用附魔ID:\n§e9§7=锋利 §e0§7=保护 §e13§7=火焰附加\n§e17§7=耐久 §e15§7=效率 §e18§7=时运");

        $player->sendForm($form);
    }

    /**
     * 编辑属性菜单
     */
    public function sendEditAttributesMenu(Player $player, string $itemId): void
    {
        $config = $this->getItemConfig($itemId);
        if ($config === null) {
            $player->sendMessage("§c物品配置不存在！");
            return;
        }

        $attributes = $config["attributes"] ?? [];

        $form = new CustomForm(function (Player $player, ?array $data) use ($itemId, $config, $attributes) {
            if ($data === null) {
                $this->sendItemEditMenu($player, $itemId);
                return;
            }

            $newAttributes = [];

            $damage = (int)$data[0];
            if ($damage > 0) {
                $newAttributes["damage"] = $damage;
            }

            $durability = (int)$data[1];
            if ($durability > 0) {
                $newAttributes["durability"] = $durability;
            }

            $attackSpeed = (float)$data[2];
            if ($attackSpeed > 0) {
                $newAttributes["attack_speed"] = $attackSpeed;
            }

            $speedMultiplier = (float)$data[3];
            if ($speedMultiplier > 0) {
                $newAttributes["speed_multiplier"] = $speedMultiplier;
            }

            $config["attributes"] = $newAttributes;
            $this->saveItemToConfig($itemId, $config);
            $this->plugin->reload();

            $player->sendMessage("§a属性已更新！");
            $this->sendItemEditMenu($player, $itemId);
        });

        $form->setTitle("§l§6编辑属性");
        $form->addInput("额外伤害 (0=不设置)", "15", (string)($attributes["damage"] ?? 0));
        $form->addInput("自定义耐久度 (0=不设置)", "2000", (string)($attributes["durability"] ?? 0));
        $form->addInput("攻击速度 (0=不设置)", "1.6", (string)($attributes["attack_speed"] ?? 0));
        $form->addInput("速度倍率 (0=不设置)", "1.5", (string)($attributes["speed_multiplier"] ?? 0));

        $player->sendForm($form);
    }

    /**
     * 编辑效果菜单
     */
    public function sendEditEffectsMenu(Player $player, string $itemId): void
    {
        $form = new SimpleForm(function (Player $player, ?int $data) use ($itemId) {
            if ($data === null || $data === 0) {
                $this->sendItemEditMenu($player, $itemId);
                return;
            }

            switch ($data) {
                case 1:
                    $this->sendEditEffectTypeMenu($player, $itemId, "on_use");
                    break;
                case 2:
                    $this->sendEditEffectTypeMenu($player, $itemId, "on_attack");
                    break;
                case 3:
                    $this->sendEditEffectTypeMenu($player, $itemId, "on_consume");
                    break;
                case 4:
                    $this->sendEditEffectTypeMenu($player, $itemId, "on_hold");
                    break;
            }
        });

        $form->setTitle("§l§3编辑特殊效果");
        $form->setContent("§7选择要编辑的效果触发类型：");

        $form->addButton("§l§c返回", 0, "textures/ui/arrow_left");
        $form->addButton("§l§e使用效果\n§r§7右键/长按触发", 0, "textures/ui/icon_recipe_item");
        $form->addButton("§l§c攻击效果\n§r§7攻击实体触发", 0, "textures/ui/attack_effect");
        $form->addButton("§l§a食用效果\n§r§7吃掉物品触发", 0, "textures/ui/regeneration_effect");
        $form->addButton("§l§b持有效果\n§r§7装备/手持触发", 0, "textures/ui/speed_effect");

        $player->sendForm($form);
    }

    /**
     * 编辑特定效果类型
     */
    public function sendEditEffectTypeMenu(Player $player, string $itemId, string $effectType): void
    {
        $config = $this->getItemConfig($itemId);
        if ($config === null) {
            $player->sendMessage("§c物品配置不存在！");
            return;
        }

        $effects = $config["effects"] ?? [];
        $effect = $effects[$effectType] ?? ["enabled" => false, "type" => "", "effects" => []];

        $typeNames = [
            "on_use" => "使用效果",
            "on_attack" => "攻击效果",
            "on_consume" => "食用效果",
            "on_hold" => "持有效果"
        ];

        $form = new CustomForm(function (Player $player, ?array $data) use ($itemId, $config, $effectType) {
            if ($data === null) {
                $this->sendEditEffectsMenu($player, $itemId);
                return;
            }

            $enabled = (bool)$data[0];
            $type = trim($data[1]);
            $target = trim($data[2]);

            if (!isset($config["effects"])) {
                $config["effects"] = [];
            }

            $config["effects"][$effectType] = [
                "enabled" => $enabled,
                "type" => $type,
                "target" => $target,
                "effects" => []
            ];

            // 根据类型添加额外配置
            if ($type === "projectile") {
                $config["effects"][$effectType]["projectile"] = trim($data[3]);
                $config["effects"][$effectType]["cooldown"] = max(0, (int)$data[4]);
            } elseif ($type === "heal") {
                $config["effects"][$effectType]["amount"] = max(1, (int)$data[3]);
            }

            $this->saveItemToConfig($itemId, $config);
            $this->plugin->reload();

            $player->sendMessage("§a效果已更新！");
            $this->sendEditEffectsMenu($player, $itemId);
        });

        $form->setTitle("§l§3编辑" . ($typeNames[$effectType] ?? $effectType));
        $form->addToggle("启用此效果", $effect["enabled"] ?? false);
        $form->addDropdown("效果类型", ["projectile", "heal", "effect", "teleport"], 0);
        $form->addDropdown("目标", ["self", "enemy", "both"], 0);
        $form->addInput("投射物类型 (projectile)", "fireball", "fireball");
        $form->addInput("冷却时间/恢复量", "5", "5");

        $player->sendForm($form);
    }

    /**
     * 编辑获取方式
     */
    public function sendEditObtainableMenu(Player $player, string $itemId): void
    {
        $config = $this->getItemConfig($itemId);
        if ($config === null) {
            $player->sendMessage("§c物品配置不存在！");
            return;
        }

        $obtainable = $config["obtainable"] ?? [];

        $form = new CustomForm(function (Player $player, ?array $data) use ($itemId, $config) {
            if ($data === null) {
                $this->sendItemEditMenu($player, $itemId);
                return;
            }

            $config["obtainable"] = [
                "craft" => (bool)$data[0],
                "drop" => (bool)$data[1],
                "shop" => (bool)$data[2],
                "price" => max(0, (int)$data[3]),
                "sell_price" => max(0, (int)$data[4])
            ];

            $this->saveItemToConfig($itemId, $config);
            $this->plugin->reload();

            $player->sendMessage("§a获取方式已更新！");
            $this->sendItemEditMenu($player, $itemId);
        });

        $form->setTitle("§l§2编辑获取方式");
        $form->addToggle("可合成", $obtainable["craft"] ?? false);
        $form->addToggle("可掉落", $obtainable["drop"] ?? false);
        $form->addToggle("可在商店购买", $obtainable["shop"] ?? false);
        $form->addInput("购买价格", "1000", (string)($obtainable["price"] ?? 0));
        $form->addInput("出售价格", "500", (string)($obtainable["sell_price"] ?? 0));

        $player->sendForm($form);
    }

    /**
     * 删除物品确认
     */
    public function sendDeleteItemConfirm(Player $player, string $itemId): void
    {
        $form = new ModalForm(function (Player $player, ?bool $data) use ($itemId) {
            if ($data === true) {
                $this->deleteItem($itemId);
                // 自动重载
                $this->plugin->reload();
                $player->sendMessage("§a物品已删除并重载配置！");
                $this->sendItemListMenu($player, 0);
            } else {
                $this->sendItemEditMenu($player, $itemId);
            }
        });

        $form->setTitle("§l§4确认删除");
        $form->setContent("§c确定要删除物品 §e{$itemId}§c 吗？\n§f此操作无法撤销！\n§f删除后将自动重载配置。");
        $form->setButton1("§l§c确认删除");
        $form->setButton2("§l§a取消");

        $player->sendForm($form);
    }

    /**
     * 重载配置菜单
     */
    public function sendReloadMenu(Player $player): void
    {
        $form = new ModalForm(function (Player $player, ?bool $data) {
            if ($data === true) {
                $player->sendMessage("§e正在重载配置...");
                $this->plugin->reload();
                $player->sendMessage("§a配置已重载！");
            }
            $this->sendMainMenu($player);
        });

        $form->setTitle("§l§3重载配置");
        $form->setContent("§f确定要重载所有配置吗？\n§f这将重新加载：\n§e• 所有自定义物品\n§e• 所有合成配方\n§e• 插件设置");
        $form->setButton1("§l§a确认重载");
        $form->setButton2("§l§c取消");

        $player->sendForm($form);
    }

    /**
     * 配方列表菜单
     */
    public function sendRecipeListMenu(Player $player): void
    {
        // 委托给 RecipeEditorGUI
        $recipeEditor = $this->plugin->getRecipeEditorGUI();
        if ($recipeEditor !== null) {
            $recipeEditor->sendRecipeListMenu($player, 0);
        } else {
            $player->sendMessage("§c配方编辑器未初始化！");
            $this->sendMainMenu($player);
        }
    }

    /**
     * 创建新配方菜单
     */
    public function sendCreateRecipeMenu(Player $player): void
    {
        // 委托给 RecipeEditorGUI
        $recipeEditor = $this->plugin->getRecipeEditorGUI();
        if ($recipeEditor !== null) {
            $recipeEditor->sendCreateRecipeTypeMenu($player);
        } else {
            $player->sendMessage("§c配方编辑器未初始化！");
            $this->sendMainMenu($player);
        }
    }

    // ========== 辅助方法 ==========

    private function getItemConfig(string $itemId): ?array
    {
        $config = new Config($this->plugin->getDataFolder() . "items.yml", Config::YAML);
        $items = $config->get("items", []);
        return $items[$itemId] ?? null;
    }

    private function saveItemToConfig(string $itemId, array $itemConfig): void
    {
        $config = new Config($this->plugin->getDataFolder() . "items.yml", Config::YAML);
        $items = $config->get("items", []);
        $items[$itemId] = $itemConfig;
        $config->set("items", $items);
        $config->save();
    }

    private function deleteItem(string $itemId): void
    {
        // 从商店移除
        $this->plugin->getItemManager()->removeFromMEBShop($itemId);

        // 删除相关配方
        $this->deleteRelatedRecipes($itemId);

        // 从配置文件删除
        $config = new Config($this->plugin->getDataFolder() . "items.yml", Config::YAML);
        $items = $config->get("items", []);
        unset($items[$itemId]);
        $config->set("items", $items);
        $config->save();
    }

    private function deleteRelatedRecipes(string $itemId): void
    {
        $recipeConfig = new Config($this->plugin->getDataFolder() . "recipes.yml", Config::YAML);
        $recipes = $recipeConfig->get("recipes", []);
        $deleted = false;

        // 检查工作台配方
        if (isset($recipes["crafting"])) {
            foreach ($recipes["crafting"] as $recipeId => $recipe) {
                $result = $recipe["result"] ?? [];
                $resultItemId = is_array($result) ? ($result["item_id"] ?? "") : $result;

                if ($resultItemId === $itemId) {
                    unset($recipes["crafting"][$recipeId]);
                    $deleted = true;
                }
            }
        }

        // 检查熔炉配方
        if (isset($recipes["smelting"])) {
            foreach ($recipes["smelting"] as $recipeId => $recipe) {
                $result = $recipe["result"] ?? [];
                $resultItemId = is_array($result) ? ($result["item_id"] ?? "") : $result;

                if ($resultItemId === $itemId) {
                    unset($recipes["smelting"][$recipeId]);
                    $deleted = true;
                }
            }
        }

        if ($deleted) {
            $recipeConfig->set("recipes", $recipes);
            $recipeConfig->save();
        }
    }

    private function addEnchantment(string $itemId, int $enchantId, int $level): void
    {
        $config = $this->getItemConfig($itemId);
        if ($config === null) return;

        if (!isset($config["enchantments"])) {
            $config["enchantments"] = [];
        }

        $config["enchantments"][] = [
            "id" => $enchantId,
            "level" => $level
        ];

        $this->saveItemToConfig($itemId, $config);
        $this->plugin->getItemManager()->loadItems();
    }

    private function removeEnchantment(string $itemId, int $index): void
    {
        $config = $this->getItemConfig($itemId);
        if ($config === null) return;

        if (isset($config["enchantments"][$index])) {
            unset($config["enchantments"][$index]);
            $config["enchantments"] = array_values($config["enchantments"]);
        }

        $this->saveItemToConfig($itemId, $config);
        $this->plugin->getItemManager()->loadItems();
    }
}
