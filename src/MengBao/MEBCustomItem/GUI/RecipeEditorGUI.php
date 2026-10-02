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
 * 配方编辑器 GUI
 */
class RecipeEditorGUI
{
    private Main $plugin;

    public function __construct(Main $plugin)
    {
        $this->plugin = $plugin;
    }

    /**
     * 配方列表菜单（分页）
     */
    public function sendRecipeListMenu(Player $player, int $page = 0): void
    {
        $config = new Config($this->plugin->getDataFolder() . "recipes.yml", Config::YAML);
        $recipesData = $config->get("recipes", []);

        // 分离不同类型的配方
        $craftingRecipes = $recipesData["crafting"] ?? [];
        $furnaceRecipes = $recipesData["smelting"] ?? [];
        $allRecipes = array_merge($craftingRecipes, $furnaceRecipes);

        $recipesPerPage = 10; // 每页显示10个配方
        $totalRecipes = count($allRecipes);
        $totalPages = max(1, (int)ceil($totalRecipes / $recipesPerPage));
        $page = max(0, min($page, $totalPages - 1));

        $recipeIds = array_keys($allRecipes);
        $startIndex = $page * $recipesPerPage;
        $pageRecipes = array_slice($recipeIds, $startIndex, $recipesPerPage, true);

        $form = new SimpleForm(function (Player $player, ?int $data) use ($allRecipes, $page, $totalPages, $pageRecipes) {
            if ($data === null) {
                // 返回主菜单
                $itemEditor = $this->plugin->getItemEditorGUI();
                if ($itemEditor !== null) {
                    $itemEditor->sendMainMenu($player);
                }
                return;
            }

            $buttonIndex = 0;

            // 创建新配方按钮
            if ($data === $buttonIndex) {
                $this->sendCreateRecipeTypeMenu($player);
                return;
            }
            $buttonIndex++;

            // 配方选择按钮
            $recipeCount = count($pageRecipes);
            if ($data >= $buttonIndex && $data < $buttonIndex + $recipeCount) {
                $recipeIndex = $data - $buttonIndex;
                $recipeIdsArray = array_values($pageRecipes);
                if (isset($recipeIdsArray[$recipeIndex])) {
                    $selectedId = $recipeIdsArray[$recipeIndex];
                    $this->sendRecipeEditMenu($player, $selectedId);
                }
                return;
            }
            $buttonIndex += $recipeCount;

            // 上一页按钮（仅在不是第一页时显示）
            if ($page > 0) {
                if ($data === $buttonIndex) {
                    $this->sendRecipeListMenu($player, $page - 1);
                    return;
                }
                $buttonIndex++;
            }

            // 下一页按钮（仅在不是最后一页时显示）
            if ($page < $totalPages - 1) {
                if ($data === $buttonIndex) {
                    $this->sendRecipeListMenu($player, $page + 1);
                    return;
                }
                $buttonIndex++;
            }

            // 返回按钮（最后一个）
            if ($data === $buttonIndex) {
                $itemEditor = $this->plugin->getItemEditorGUI();
                if ($itemEditor !== null) {
                    $itemEditor->sendMainMenu($player);
                }
                return;
            }
        });

        $form->setTitle("§l§e配方列表");
        $form->setContent("§f共 §e{$totalRecipes} §f个配方 | 第 §e" . ($page + 1) . "§f/§e{$totalPages} §f页\n§f点击配方进行编辑：");

        // 创建新配方按钮
        $form->addButton("§l§d创建新配方\n§r§f添加合成配方", 0, "textures/ui/color_plus");

        // 显示当前页的配方
        foreach ($pageRecipes as $id) {
            $recipe = $allRecipes[$id];
            $type = $recipe["type"] ?? "unknown";
            $result = $recipe["result"] ?? "unknown";

            // 确保result是字符串
            if (is_array($result)) {
                $result = $result["item_id"] ?? $result[0] ?? "unknown";
            }

            $typeText = $type === "shaped" ? "有序" : ($type === "shapeless" ? "无序" : "熔炉");
            $form->addButton("§l{$result}\n§r§f类型: {$typeText} | ID: {$id}", 0, "textures/ui/recipe_book_icon");
        }

        // 上一页按钮（仅在不是第一页时显示）
        if ($page > 0) {
            $form->addButton("§l§e◀ 上一页", 0, "textures/ui/arrow_left");
        }

        // 下一页按钮（仅在不是最后一页时显示）
        if ($page < $totalPages - 1) {
            $form->addButton("§l§e下一页 ▶", 0, "textures/ui/arrow_right");
        }

        // 返回按钮，使用与分页相同的图标
        $form->addButton("§l§c返回主菜单", 0, "textures/ui/arrow_left");

        $player->sendForm($form);
    }

    /**
     * 选择配方类型菜单
     */
    public function sendCreateRecipeTypeMenu(Player $player): void
    {
        $form = new SimpleForm(function (Player $player, ?int $data) {
            if ($data === null) {
                $this->sendRecipeListMenu($player, 0);
                return;
            }

            switch ($data) {
                case 0:
                    $this->sendCreateShapedRecipeMenu($player);
                    break;
                case 1:
                    $this->sendCreateShapelessRecipeMenu($player);
                    break;
                case 2:
                    $this->sendCreateFurnaceRecipeMenu($player);
                    break;
                case 3:
                    $this->sendRecipeListMenu($player, 0);
                    break;
            }
        });

        $form->setTitle("§l§d选择配方类型");
        $form->setContent("§f选择要创建的配方类型：");

        $form->addButton("§l§a有序合成\n§r§f需要特定摆放顺序", 0, "textures/ui/crafting_table");
        $form->addButton("§l§b无序合成\n§r§f材料顺序随意", 0, "textures/ui/recipe_book_icon");
        $form->addButton("§l§e熔炉冶炼\n§r§f通过熔炉制作", 0, "textures/ui/furnace_icon");
        $form->addButton("§l§c返回配方列表", 0, "textures/ui/arrow_left");

        $player->sendForm($form);
    }

    /**
     * 创建有序合成配方
     */
    public function sendCreateShapedRecipeMenu(Player $player): void
    {
        // 获取所有自定义物品
        $items = $this->plugin->getItemManager()->getAllItems();
        $itemIds = array_keys($items);

        if (empty($itemIds)) {
            $player->sendMessage("§c错误：没有可用的自定义物品！请先创建自定义物品。");
            $this->sendCreateRecipeTypeMenu($player);
            return;
        }

        $form = new CustomForm(function (Player $player, ?array $data) use ($itemIds) {
            if ($data === null) {
                $this->sendCreateRecipeTypeMenu($player);
                return;
            }

            $recipeId = strtolower(trim($data[0] ?? ""));
            $resultIndex = (int)($data[1] ?? 0);
            $result = $itemIds[$resultIndex] ?? "";
            $amount = max(1, (int)($data[2] ?? 1));

            $row1 = trim($data[3] ?? "");
            $row2 = trim($data[4] ?? "");
            $row3 = trim($data[5] ?? "");

            $mapping1 = trim($data[6] ?? "");
            $mapping2 = trim($data[7] ?? "");
            $mapping3 = trim($data[8] ?? "");
            $mapping4 = trim($data[9] ?? "");
            $mapping5 = trim($data[10] ?? "");
            $mapping6 = trim($data[11] ?? "");
            $mapping7 = trim($data[12] ?? "");
            $mapping8 = trim($data[13] ?? "");
            $mapping9 = trim($data[14] ?? "");

            // 验证
            if (empty($recipeId) || empty($result)) {
                $player->sendMessage("§c错误：配方ID和结果物品是必填的！");
                return;
            }

            // 解析材料映射
            $ingredientMap = [];
            $mappings = [$mapping1, $mapping2, $mapping3, $mapping4, $mapping5, $mapping6, $mapping7, $mapping8, $mapping9];

            foreach ($mappings as $mapping) {
                if (empty($mapping)) continue;

                $parts = explode(":", $mapping, 2);
                if (count($parts) === 2) {
                    $key = trim($parts[0]);
                    $value = trim($parts[1]);
                    if (!empty($key) && !empty($value)) {
                        $ingredientMap[$key] = $value;
                    }
                }
            }

            if (empty($ingredientMap)) {
                $player->sendMessage("§c错误：请至少定义一个材料映射！");
                return;
            }

            // 创建配方
            $recipe = [
                "type" => "shaped",
                "result" => [
                    "item_id" => $result,
                    "count" => $amount
                ],
                "shape" => array_filter([$row1, $row2, $row3]),
                "ingredients" => $ingredientMap
            ];

            $this->saveRecipe($recipeId, $recipe, "crafting");
            $player->sendMessage("§a有序合成配方创建成功！ID: {$recipeId}");
            $this->sendRecipeListMenu($player, 0);
        });

        $form->setTitle("§l§a创建有序合成配方");
        $form->addInput("§f配方ID §c*", "flame_sword", "");
        $form->addDropdown("§f结果物品 §c*", $itemIds, 0);
        $form->addInput("§f数量", "1", "1");
        $form->addLabel("§f合成形状（最多3行，每行最多3个字符）：");
        $form->addInput("§f第一行", "DDD", "");
        $form->addInput("§f第二行", " S ", "");
        $form->addInput("§f第三行", " S ", "");
        $form->addLabel("§f材料映射（格式: 字符:物品ID）：");
        $form->addInput("§f映射1 §c*", "D:minecraft:diamond", "");
        $form->addInput("§f映射2", "S:minecraft:stick", "");
        $form->addInput("§f映射3（可选）", "G:minecraft:gold_ingot", "");
        $form->addInput("§f映射4（可选）", "", "");
        $form->addInput("§f映射5（可选）", "", "");
        $form->addInput("§f映射6（可选）", "", "");
        $form->addInput("§f映射7（可选）", "", "");
        $form->addInput("§f映射8（可选）", "", "");
        $form->addInput("§f映射9（可选）", "", "");

        $player->sendForm($form);
    }

    /**
     * 创建无序合成配方
     */
    public function sendCreateShapelessRecipeMenu(Player $player): void
    {
        // 获取所有自定义物品
        $items = $this->plugin->getItemManager()->getAllItems();
        $itemIds = array_keys($items);

        if (empty($itemIds)) {
            $player->sendMessage("§c错误：没有可用的自定义物品！请先创建自定义物品。");
            $this->sendCreateRecipeTypeMenu($player);
            return;
        }

        $form = new CustomForm(function (Player $player, ?array $data) use ($itemIds) {
            if ($data === null) {
                $this->sendCreateRecipeTypeMenu($player);
                return;
            }

            $recipeId = strtolower(trim($data[0] ?? ""));
            $resultIndex = (int)($data[1] ?? 0);
            $result = $itemIds[$resultIndex] ?? "";
            $amount = max(1, (int)($data[2] ?? 1));

            $ingredient1 = trim($data[3] ?? "");
            $ingredient2 = trim($data[4] ?? "");
            $ingredient3 = trim($data[5] ?? "");
            $ingredient4 = trim($data[6] ?? "");
            $ingredient5 = trim($data[7] ?? "");
            $ingredient6 = trim($data[8] ?? "");
            $ingredient7 = trim($data[9] ?? "");
            $ingredient8 = trim($data[10] ?? "");
            $ingredient9 = trim($data[11] ?? "");

            // 验证
            if (empty($recipeId) || empty($result)) {
                $player->sendMessage("§c错误：配方ID和结果物品是必填的！");
                return;
            }

            // 收集材料
            $ingredients = array_filter([
                $ingredient1, $ingredient2, $ingredient3,
                $ingredient4, $ingredient5, $ingredient6,
                $ingredient7, $ingredient8, $ingredient9
            ]);

            if (empty($ingredients)) {
                $player->sendMessage("§c错误：请至少定义一个材料！");
                return;
            }

            // 创建配方
            $recipe = [
                "type" => "shapeless",
                "result" => [
                    "item_id" => $result,
                    "count" => $amount
                ],
                "ingredients" => array_values($ingredients)
            ];

            $this->saveRecipe($recipeId, $recipe, "crafting");
            $this->plugin->reload();
            $player->sendMessage("§a无序合成配方创建成功！ID: {$recipeId}");
            $this->sendRecipeListMenu($player, 0);
        });

        $form->setTitle("§l§b创建无序合成配方");
        $form->addInput("§f配方ID §c*", "healing_apple", "");
        $form->addDropdown("§f结果物品 §c*", $itemIds, 0);
        $form->addInput("§f数量", "1", "1");
        $form->addLabel("§f材料列表（最多9个）：");
        $form->addInput("§f材料1 §c*", "minecraft:golden_apple", "");
        $form->addInput("§f材料2", "minecraft:diamond", "");
        $form->addInput("§f材料3（可选）", "", "");
        $form->addInput("§f材料4（可选）", "", "");
        $form->addInput("§f材料5（可选）", "", "");
        $form->addInput("§f材料6（可选）", "", "");
        $form->addInput("§f材料7（可选）", "", "");
        $form->addInput("§f材料8（可选）", "", "");
        $form->addInput("§f材料9（可选）", "", "");

        $player->sendForm($form);
    }

    /**
     * 创建熔炉配方
     */
    public function sendCreateFurnaceRecipeMenu(Player $player): void
    {
        // 获取所有自定义物品
        $items = $this->plugin->getItemManager()->getAllItems();
        $itemIds = array_keys($items);

        if (empty($itemIds)) {
            $player->sendMessage("§c错误：没有可用的自定义物品！请先创建自定义物品。");
            $this->sendCreateRecipeTypeMenu($player);
            return;
        }

        $form = new CustomForm(function (Player $player, ?array $data) use ($itemIds) {
            if ($data === null) {
                $this->sendCreateRecipeTypeMenu($player);
                return;
            }

            $recipeId = strtolower(trim($data[0] ?? ""));
            $resultIndex = (int)($data[1] ?? 0);
            $result = $itemIds[$resultIndex] ?? "";
            $amount = max(1, (int)($data[2] ?? 1));
            $input = trim($data[3] ?? "");

            // 验证
            if (empty($recipeId) || empty($result) || empty($input)) {
                $player->sendMessage("§c错误：所有字段都是必填的！");
                return;
            }

            // 创建配方
            $recipe = [
                "type" => "smelting",
                "result" => [
                    "item_id" => $result,
                    "count" => $amount
                ],
                "input" => $input
            ];

            $this->saveRecipe($recipeId, $recipe, "smelting");
            $player->sendMessage("§a熔炉配方创建成功！ID: {$recipeId}");
            $this->sendRecipeListMenu($player, 0);
        });

        $form->setTitle("§l§e创建熔炉配方");
        $form->addInput("配方ID §c*", "custom_ingot", "");
        $form->addDropdown("结果物品 §c*", $itemIds, 0);
        $form->addInput("数量", "1", "1");
        $form->addInput("输入材料 §c*", "minecraft:iron_ore", "");

        $player->sendForm($form);
    }

    /**
     * 编辑配方菜单
     */
    public function sendRecipeEditMenu(Player $player, string $recipeId): void
    {
        $recipe = $this->getRecipe($recipeId);
        if ($recipe === null) {
            $player->sendMessage("§c配方不存在！");
            return;
        }

        $type = $recipe["type"] ?? "unknown";
        $result = $recipe["result"] ?? "unknown";

        // 确保result是字符串
        if (is_array($result)) {
            $result = $result["item_id"] ?? $result[0] ?? "unknown";
        }

        $form = new SimpleForm(function (Player $player, ?int $data) use ($recipeId, $type) {
            if ($data === null) {
                $this->sendRecipeListMenu($player, 0);
                return;
            }

            switch ($data) {
                case 0:
                    if ($type === "shaped") {
                        $this->sendEditShapedRecipeMenu($player, $recipeId);
                    } elseif ($type === "shapeless") {
                        $this->sendEditShapelessRecipeMenu($player, $recipeId);
                    } else {
                        $this->sendEditFurnaceRecipeMenu($player, $recipeId);
                    }
                    break;
                case 1:
                    $this->sendDeleteRecipeConfirm($player, $recipeId);
                    break;
                case 2:
                    $this->sendRecipeListMenu($player, 0);
                    break;
            }
        });

        $form->setTitle("§l§e编辑配方");
        $form->setContent("§fID: §e{$recipeId}\n§f结果: §a{$result}\n§f类型: §b{$type}");

        $form->addButton("§l§a编辑配方\n§r§f修改配方内容", 0, "textures/ui/book_edit_default");
        $form->addButton("§l§4删除配方\n§r§f永久删除", 0, "textures/ui/realms_red_x");
        $form->addButton("§l§c返回配方列表", 0, "textures/ui/arrow_left");

        $player->sendForm($form);
    }

    /**
     * 编辑有序合成配方
     */
    public function sendEditShapedRecipeMenu(Player $player, string $recipeId): void
    {
        $recipe = $this->getRecipe($recipeId);
        if ($recipe === null) {
            $player->sendMessage("§c配方不存在！");
            return;
        }

        // 获取所有自定义物品
        $items = $this->plugin->getItemManager()->getAllItems();
        $itemIds = array_keys($items);

        if (empty($itemIds)) {
            $player->sendMessage("§c错误：没有可用的自定义物品！");
            $this->sendRecipeEditMenu($player, $recipeId);
            return;
        }

        // 提取结果物品ID和数量
        $result = $recipe["result"] ?? [];
        $resultId = is_array($result) ? ($result["item_id"] ?? "") : $result;
        $resultCount = is_array($result) ? ($result["count"] ?? 1) : 1;

        // 找到结果物品在下拉框中的索引
        $resultIndex = array_search($resultId, $itemIds);
        if ($resultIndex === false) {
            $resultIndex = 0;
        }

        $shape = $recipe["shape"] ?? [];
        $ingredients = $recipe["ingredients"] ?? [];

        // 将材料映射转换为数组（最多9个）
        $mappings = array_fill(0, 9, "");
        $index = 0;
        foreach ($ingredients as $key => $value) {
            if ($index < 9) {
                $mappings[$index] = "{$key}:{$value}";
                $index++;
            }
        }

        $form = new CustomForm(function (Player $player, ?array $data) use ($recipeId, $recipe, $itemIds) {
            if ($data === null) {
                $this->sendRecipeEditMenu($player, $recipeId);
                return;
            }

            $resultIndex = (int)($data[0] ?? 0);
            $resultItemId = $itemIds[$resultIndex] ?? "";

            $recipe["result"] = [
                "item_id" => $resultItemId,
                "count" => max(1, (int)($data[1] ?? 1))
            ];

            $row1 = trim($data[2] ?? "");
            $row2 = trim($data[3] ?? "");
            $row3 = trim($data[4] ?? "");

            $mapping1 = trim($data[5] ?? "");
            $mapping2 = trim($data[6] ?? "");
            $mapping3 = trim($data[7] ?? "");
            $mapping4 = trim($data[8] ?? "");
            $mapping5 = trim($data[9] ?? "");
            $mapping6 = trim($data[10] ?? "");
            $mapping7 = trim($data[11] ?? "");
            $mapping8 = trim($data[12] ?? "");
            $mapping9 = trim($data[13] ?? "");

            // 解析材料映射
            $ingredientMap = [];
            $mappings = [$mapping1, $mapping2, $mapping3, $mapping4, $mapping5, $mapping6, $mapping7, $mapping8, $mapping9];

            foreach ($mappings as $mapping) {
                if (empty($mapping)) continue;

                $parts = explode(":", $mapping, 2);
                if (count($parts) === 2) {
                    $key = trim($parts[0]);
                    $value = trim($parts[1]);
                    if (!empty($key) && !empty($value)) {
                        $ingredientMap[$key] = $value;
                    }
                }
            }

            if (empty($ingredientMap)) {
                $player->sendMessage("§c错误：请至少定义一个材料映射！");
                $this->sendEditShapedRecipeMenu($player, $recipeId);
                return;
            }

            $recipe["shape"] = array_filter([$row1, $row2, $row3]);
            $recipe["ingredients"] = $ingredientMap;

            $this->saveRecipe($recipeId, $recipe, "crafting");
            $this->plugin->reload();

            $player->sendMessage("§a配方已更新！");
            $this->sendRecipeEditMenu($player, $recipeId);
        });

        $form->setTitle("§l§a编辑有序合成配方");
        $form->addDropdown("§f结果物品", $itemIds, $resultIndex);
        $form->addInput("§f数量", "1", (string)$resultCount);
        $form->addInput("§f第一行", "DDD", $shape[0] ?? "");
        $form->addInput("§f第二行", " S ", $shape[1] ?? "");
        $form->addInput("§f第三行", " S ", $shape[2] ?? "");
        $form->addLabel("§f材料映射（格式: 字符:物品ID）：");
        $form->addInput("§f映射1 §c*", "D:minecraft:diamond", $mappings[0]);
        $form->addInput("§f映射2", "S:minecraft:stick", $mappings[1]);
        $form->addInput("§f映射3（可选）", "G:minecraft:gold_ingot", $mappings[2]);
        $form->addInput("§f映射4（可选）", "", $mappings[3]);
        $form->addInput("§f映射5（可选）", "", $mappings[4]);
        $form->addInput("§f映射6（可选）", "", $mappings[5]);
        $form->addInput("§f映射7（可选）", "", $mappings[6]);
        $form->addInput("§f映射8（可选）", "", $mappings[7]);
        $form->addInput("§f映射9（可选）", "", $mappings[8]);

        $player->sendForm($form);
    }

    /**
     * 编辑无序合成配方
     */
    public function sendEditShapelessRecipeMenu(Player $player, string $recipeId): void
    {
        $recipe = $this->getRecipe($recipeId);
        if ($recipe === null) {
            $player->sendMessage("§c配方不存在！");
            return;
        }

        // 获取所有自定义物品
        $items = $this->plugin->getItemManager()->getAllItems();
        $itemIds = array_keys($items);

        if (empty($itemIds)) {
            $player->sendMessage("§c错误：没有可用的自定义物品！");
            $this->sendRecipeEditMenu($player, $recipeId);
            return;
        }

        // 提取结果物品ID和数量
        $result = $recipe["result"] ?? [];
        $resultId = is_array($result) ? ($result["item_id"] ?? "") : $result;
        $resultCount = is_array($result) ? ($result["count"] ?? 1) : 1;

        // 找到结果物品在下拉框中的索引
        $resultIndex = array_search($resultId, $itemIds);
        if ($resultIndex === false) {
            $resultIndex = 0;
        }

        $ingredients = $recipe["ingredients"] ?? [];

        // 将材料列表填充到9个输入框
        $ingredientInputs = array_fill(0, 9, "");
        for ($i = 0; $i < min(count($ingredients), 9); $i++) {
            $ingredientInputs[$i] = $ingredients[$i];
        }

        $form = new CustomForm(function (Player $player, ?array $data) use ($recipeId, $recipe, $itemIds) {
            if ($data === null) {
                $this->sendRecipeEditMenu($player, $recipeId);
                return;
            }

            $resultIndex = (int)($data[0] ?? 0);
            $resultItemId = $itemIds[$resultIndex] ?? "";

            $recipe["result"] = [
                "item_id" => $resultItemId,
                "count" => max(1, (int)($data[1] ?? 1))
            ];

            // 收集材料
            $ingredients = array_filter([
                trim($data[2] ?? ""),
                trim($data[3] ?? ""),
                trim($data[4] ?? ""),
                trim($data[5] ?? ""),
                trim($data[6] ?? ""),
                trim($data[7] ?? ""),
                trim($data[8] ?? ""),
                trim($data[9] ?? ""),
                trim($data[10] ?? "")
            ]);

            if (empty($ingredients)) {
                $player->sendMessage("§c错误：请至少定义一个材料！");
                $this->sendEditShapelessRecipeMenu($player, $recipeId);
                return;
            }

            $recipe["ingredients"] = array_values($ingredients);

            $this->saveRecipe($recipeId, $recipe, "crafting");
            $this->plugin->reload();

            $player->sendMessage("§a配方已更新！");
            $this->sendRecipeEditMenu($player, $recipeId);
        });

        $form->setTitle("§l§b编辑无序合成配方");
        $form->addDropdown("§f结果物品", $itemIds, $resultIndex);
        $form->addInput("§f数量", "1", (string)$resultCount);
        $form->addLabel("§f材料列表（最多9个）：");
        $form->addInput("§f材料1 §c*", "minecraft:golden_apple", $ingredientInputs[0]);
        $form->addInput("§f材料2", "minecraft:diamond", $ingredientInputs[1]);
        $form->addInput("§f材料3（可选）", "", $ingredientInputs[2]);
        $form->addInput("§f材料4（可选）", "", $ingredientInputs[3]);
        $form->addInput("§f材料5（可选）", "", $ingredientInputs[4]);
        $form->addInput("§f材料6（可选）", "", $ingredientInputs[5]);
        $form->addInput("§f材料7（可选）", "", $ingredientInputs[6]);
        $form->addInput("§f材料8（可选）", "", $ingredientInputs[7]);
        $form->addInput("§f材料9（可选）", "", $ingredientInputs[8]);

        $player->sendForm($form);
    }

    /**
     * 编辑熔炉配方
     */
    public function sendEditFurnaceRecipeMenu(Player $player, string $recipeId): void
    {
        $recipe = $this->getRecipe($recipeId);
        if ($recipe === null) {
            $player->sendMessage("§c配方不存在！");
            return;
        }

        // 获取所有自定义物品
        $items = $this->plugin->getItemManager()->getAllItems();
        $itemIds = array_keys($items);

        if (empty($itemIds)) {
            $player->sendMessage("§c错误：没有可用的自定义物品！");
            $this->sendRecipeEditMenu($player, $recipeId);
            return;
        }

        // 提取结果物品ID和数量
        $result = $recipe["result"] ?? [];
        $resultId = is_array($result) ? ($result["item_id"] ?? "") : $result;
        $resultCount = is_array($result) ? ($result["count"] ?? 1) : 1;

        // 找到结果物品在下拉框中的索引
        $resultIndex = array_search($resultId, $itemIds);
        if ($resultIndex === false) {
            $resultIndex = 0;
        }

        $form = new CustomForm(function (Player $player, ?array $data) use ($recipeId, $recipe, $itemIds) {
            if ($data === null) {
                $this->sendRecipeEditMenu($player, $recipeId);
                return;
            }

            $resultIndex = (int)($data[0] ?? 0);
            $resultItemId = $itemIds[$resultIndex] ?? "";

            $recipe["result"] = [
                "item_id" => $resultItemId,
                "count" => max(1, (int)($data[1] ?? 1))
            ];
            $recipe["input"] = trim($data[2] ?? "");

            $this->saveRecipe($recipeId, $recipe, "smelting");
            $this->plugin->reload();

            $player->sendMessage("§a配方已更新！");
            $this->sendRecipeEditMenu($player, $recipeId);
        });

        $form->setTitle("§l§e编辑熔炉配方");
        $form->addDropdown("§f结果物品", $itemIds, $resultIndex);
        $form->addInput("§f数量", "1", (string)$resultCount);
        $form->addInput("§f输入材料", "minecraft:iron_ore", $recipe["input"] ?? "");

        $player->sendForm($form);
    }

    /**
     * 删除配方确认
     */
    public function sendDeleteRecipeConfirm(Player $player, string $recipeId): void
    {
        $form = new ModalForm(function (Player $player, ?bool $data) use ($recipeId) {
            if ($data === true) {
                $this->deleteRecipe($recipeId);
                $player->sendMessage("§a配方已删除！");
                $player->sendMessage("§e工作台已禁用，请重启服务器使配置生效！");
                $this->sendRecipeListMenu($player, 0);
            } else {
                $this->sendRecipeEditMenu($player, $recipeId);
            }
        });

        $form->setTitle("§l§4确认删除");
        $form->setContent("§c确定要删除配方 §e{$recipeId}§c 吗？\n§f此操作无法撤销！\n\n§e注意：配方将从配置文件中删除，需要重启服务器才能完全生效。");
        $form->setButton1("§l§c确认删除");
        $form->setButton2("§l§a取消");

        $player->sendForm($form);
    }

    // ========== 辅助方法 ==========

    private function getRecipe(string $recipeId): ?array
    {
        $config = new Config($this->plugin->getDataFolder() . "recipes.yml", Config::YAML);
        $recipesData = $config->get("recipes", []);

        // 在 crafting 中查找
        if (isset($recipesData["crafting"][$recipeId])) {
            return $recipesData["crafting"][$recipeId];
        }

        // 在 smelting 中查找
        if (isset($recipesData["smelting"][$recipeId])) {
            return $recipesData["smelting"][$recipeId];
        }

        return null;
    }

    private function saveRecipe(string $recipeId, array $recipe, string $category): void
    {
        $config = new Config($this->plugin->getDataFolder() . "recipes.yml", Config::YAML);
        $recipesData = $config->get("recipes", []);

        if (!isset($recipesData[$category])) {
            $recipesData[$category] = [];
        }

        $recipesData[$category][$recipeId] = $recipe;
        $config->set("recipes", $recipesData);
        $config->save();

        $this->plugin->reload();
    }

    private function deleteRecipe(string $recipeId): void
    {
        $config = new Config($this->plugin->getDataFolder() . "recipes.yml", Config::YAML);
        $recipesData = $config->get("recipes", []);

        // 从所有分类中删除
        if (isset($recipesData["crafting"][$recipeId])) {
            unset($recipesData["crafting"][$recipeId]);
        }
        if (isset($recipesData["smelting"][$recipeId])) {
            unset($recipesData["smelting"][$recipeId]);
        }

        $config->set("recipes", $recipesData);
        $config->save();

        $this->plugin->reload();
    }
}
