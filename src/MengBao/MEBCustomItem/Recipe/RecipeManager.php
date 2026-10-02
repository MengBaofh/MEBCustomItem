<?php

declare(strict_types=1);

namespace MengBao\MEBCustomItem\Recipe;

use MengBao\MEBCustomItem\Main;
use pocketmine\crafting\ShapedRecipe;
use pocketmine\crafting\ShapelessRecipe;
use pocketmine\crafting\ShapelessRecipeType;
use pocketmine\crafting\ExactRecipeIngredient;
use pocketmine\item\StringToItemParser;
use pocketmine\item\Item;

/**
 * 配方管理器
 */
class RecipeManager
{
    private Main $plugin;

    /** @var ShapedRecipe[] */
    private array $shapedRecipes = [];

    /** @var ShapelessRecipe[] */
    private array $shapelessRecipes = [];

    public function __construct(Main $plugin)
    {
        $this->plugin = $plugin;
    }

    /**
     * 注册所有配方
     */
    public function registerRecipes(): void
    {
        $recipesConfig = $this->plugin->getRecipesConfig();
        $recipes = $recipesConfig->get("recipes", []);

        $craftingRecipes = $recipes["crafting"] ?? [];
        foreach ($craftingRecipes as $itemId => $recipe) {
            $this->registerCraftingRecipe($itemId, $recipe);
        }

        $smeltingRecipes = $recipes["smelting"] ?? [];
        foreach ($smeltingRecipes as $itemId => $recipe) {
            $this->registerFurnaceRecipe($itemId, $recipe);
        }

        $this->plugin->getLogger()->info("已注册 " . (count($this->shapedRecipes) + count($this->shapelessRecipes)) . " 个工作台配方和 " . count($smeltingRecipes) . " 个熔炉配方");
    }

    /**
     * 注册工作台配方
     */
    private function registerCraftingRecipe(string $itemId, array $recipe): void
    {
        $type = $recipe["type"] ?? "shaped";

        if ($type === "shaped") {
            $this->registerShapedRecipe($itemId, $recipe);
        } else {
            $this->registerShapelessRecipe($itemId, $recipe);
        }
    }

    /**
     * 注册有序配方
     */
    private function registerShapedRecipe(string $itemId, array $recipe): void
    {
        try {
            // 获取结果物品
            $result = $this->getResultItem($recipe["result"] ?? []);
            if ($result === null) {
                $this->plugin->getLogger()->warning("配方 {$itemId} 的结果物品无效");
                return;
            }

            // 解析形状
            $shape = $recipe["shape"] ?? [];
            if (count($shape) !== 3) {
                $this->plugin->getLogger()->warning("配方 {$itemId} 的形状必须是3行");
                return;
            }

            // 解析材料 - 转换为 RecipeIngredient
            $ingredients = $recipe["ingredients"] ?? [];
            $itemIngredients = [];

            foreach ($ingredients as $key => $itemString) {
                $item = $this->parseItem($itemString);
                if ($item !== null) {
                    // 将 Item 转换为 ExactRecipeIngredient
                    $itemIngredients[$key] = new ExactRecipeIngredient($item);
                }
            }

            // 创建有序配方
            $shapedRecipe = new ShapedRecipe(
                $shape,
                $itemIngredients,
                [$result]
            );

            // 注册配方 - PM5 使用 registerShapedRecipe 方法
            $this->plugin->getServer()->getCraftingManager()->registerShapedRecipe($shapedRecipe);
            $this->shapedRecipes[$itemId] = $shapedRecipe;

        } catch (\Exception $e) {
            $this->plugin->getLogger()->error("注册有序配方 {$itemId} 失败: " . $e->getMessage());
        }
    }

    /**
     * 注册无序配方
     */
    private function registerShapelessRecipe(string $itemId, array $recipe): void
    {
        try {
            // 获取结果物品
            $result = $this->getResultItem($recipe["result"] ?? []);
            if ($result === null) {
                $this->plugin->getLogger()->warning("配方 {$itemId} 的结果物品无效");
                return;
            }

            // 解析材料 - 转换为 RecipeIngredient
            $ingredientStrings = $recipe["ingredients"] ?? [];
            $ingredients = [];

            foreach ($ingredientStrings as $itemString) {
                $item = $this->parseItem($itemString);
                if ($item !== null) {
                    // 将 Item 转换为 ExactRecipeIngredient
                    $ingredients[] = new ExactRecipeIngredient($item);
                }
            }

            if (empty($ingredients)) {
                $this->plugin->getLogger()->warning("配方 {$itemId} 没有有效的材料");
                return;
            }

            // 创建无序配方 - PM5 需要传入 ShapelessRecipeType
            $shapelessRecipe = new ShapelessRecipe(
                $ingredients,
                [$result],
                ShapelessRecipeType::CRAFTING
            );

            // 注册配方 - PM5 使用 registerShapelessRecipe 方法
            $this->plugin->getServer()->getCraftingManager()->registerShapelessRecipe($shapelessRecipe);
            $this->shapelessRecipes[$itemId] = $shapelessRecipe;

        } catch (\Exception $e) {
            $this->plugin->getLogger()->error("注册无序配方 {$itemId} 失败: " . $e->getMessage());
        }
    }

    /**
     * 获取结果物品
     */
    private function getResultItem(array $resultConfig): ?Item
    {
        $itemId = $resultConfig["item_id"] ?? "";
        $count = $resultConfig["count"] ?? 1;

        // 检查是否为自定义物品
        $customItem = $this->plugin->getItemManager()->getItem($itemId);
        if ($customItem !== null) {
            return $customItem->create($count);
        }

        return null;
    }

    /**
     * 解析物品字符串
     */
    private function parseItem(string $itemString): ?Item
    {
        $parser = StringToItemParser::getInstance();
        $item = $parser->parse($itemString);

        if ($item !== null) {
            return $item;
        }

        // 检查是否为自定义物品（格式：mebci:item_id）
        if (strpos($itemString, "mebci:") === 0) {
            $itemId = substr($itemString, 6);
            $customItem = $this->plugin->getItemManager()->getItem($itemId);
            if ($customItem !== null) {
                return $customItem->create(1);
            }
        }

        return null;
    }

    /**
     * 注册熔炉配方
     */
    private function registerFurnaceRecipe(string $itemId, array $recipe): void
    {
        try {
            // 获取结果物品
            $result = $this->getResultItem($recipe["result"] ?? []);
            if ($result === null) {
                $this->plugin->getLogger()->warning("熔炉配方 {$itemId} 的结果物品无效");
                return;
            }

            // 获取输入物品
            $inputString = $recipe["input"] ?? "";
            $input = $this->parseItem($inputString);
            if ($input === null) {
                $this->plugin->getLogger()->warning("熔炉配方 {$itemId} 的输入物品无效: {$inputString}");
                return;
            }

            // 将输入物品转换为 ExactRecipeIngredient
            $inputIngredient = new ExactRecipeIngredient($input);

            // 注册熔炉配方
            $furnaceType = $this->plugin->getServer()->getCraftingManager()->getFurnaceRecipeManager(\pocketmine\crafting\FurnaceType::FURNACE());
            $furnaceType->register(new \pocketmine\crafting\FurnaceRecipe($result, $inputIngredient));

            $this->plugin->getLogger()->info("已注册熔炉配方: {$itemId}");

        } catch (\Exception $e) {
            $this->plugin->getLogger()->error("注册熔炉配方 {$itemId} 失败: " . $e->getMessage());
        }
    }

    /**
     * 注销所有配方
     */
    public function unregisterAll(): void
    {
        // PM5 不支持直接删除配方，我们需要清空并重建配方管理器
        // 清空本地缓存
        $this->shapedRecipes = [];
        $this->shapelessRecipes = [];

        // 获取服务器的配方管理器
        $craftingManager = $this->plugin->getServer()->getCraftingManager();

        // 通过反射清空配方管理器中的配方（这是一个 workaround）
        try {
            $reflection = new \ReflectionClass($craftingManager);

            // 清空工作台配方
            if ($reflection->hasProperty('craftingRecipeIndex')) {
                $property = $reflection->getProperty('craftingRecipeIndex');
                $property->setAccessible(true);
                $property->setValue($craftingManager, []);
            }
        } catch (\ReflectionException $e) {
            $this->plugin->getLogger()->warning("无法清空配方管理器: " . $e->getMessage());
        }
    }

    /**
     * 获取所有有序配方
     */
    public function getShapedRecipes(): array
    {
        return $this->shapedRecipes;
    }

    /**
     * 获取所有无序配方
     */
    public function getShapelessRecipes(): array
    {
        return $this->shapelessRecipes;
    }
}
