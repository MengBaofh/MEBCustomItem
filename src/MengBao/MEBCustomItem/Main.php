<?php

declare(strict_types=1);

namespace MengBao\MEBCustomItem;

use MengBao\MEBCustomItem\Item\CustomItemManager;
use MengBao\MEBCustomItem\Recipe\RecipeManager;
use MengBao\MEBCustomItem\Command\CommandRouter;
use MengBao\MEBCustomItem\Listener\ItemUseListener;
use MengBao\MEBCustomItem\Listener\EntityDeathListener;
use MengBao\MEBCustomItem\Listener\BlockBreakListener;
use MengBao\MEBCustomItem\Listener\PlayerListener;
use MengBao\MEBCustomItem\GUI\ItemEditorGUI;
use MengBao\MEBCustomItem\GUI\RecipeEditorGUI;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;

class Main extends PluginBase
{
    private static ?Main $instance = null;

    private Config $settings;
    private Config $itemsConfig;
    private Config $recipesConfig;

    private CustomItemManager $itemManager;
    private RecipeManager $recipeManager;
    private CommandRouter $router;
    private \MengBao\MEBCustomItem\Listener\PlayerListener $playerListener;

    private ?ItemEditorGUI $itemEditorGUI = null;
    private ?RecipeEditorGUI $recipeEditorGUI = null;

    private bool $mebFormsAvailable = false;
    private bool $mebSocietyAvailable = false;
    private bool $mebMobAIAvailable = false;

    public function onLoad(): void
    {
        self::$instance = $this;
    }

    public function onEnable(): void
    {
        @mkdir($this->getDataFolder(), 0777, true);

        $this->loadConfigs();
        $this->checkDependencies();

        $this->itemManager = new CustomItemManager($this);
        $this->recipeManager = new RecipeManager($this);
        $this->router = new CommandRouter($this);

        // 初始化 GUI 管理器
        if ($this->mebFormsAvailable) {
            $this->itemEditorGUI = new ItemEditorGUI($this);
            $this->recipeEditorGUI = new RecipeEditorGUI($this);
            $this->getLogger()->info("§aGUI系统已启用");
        }

        $this->itemManager->loadItems();

        if ($this->settings->getNested("settings.enable_recipes", true)) {
            $this->recipeManager->registerRecipes();
        }

        $this->registerListeners();

        // 启动持有效果检测任务
        $this->startHoldEffectTask();

        // 集成 MEBSociety 商店
        if ($this->mebSocietyAvailable) {
            $this->integrateWithMEBSociety();
        }

        if (!$this->mebFormsAvailable) {
            $this->getLogger()->warning("MEBForms未安装，GUI功能不可用");
        }

        $this->getLogger()->info("§aMEBCustomItem 已启用！");
    }

    public function onDisable(): void
    {
        $this->getLogger()->info("§cMEBCustomItem 已禁用！");
    }

    private function loadConfigs(): void
    {
        $this->saveDefaultConfig();
        $this->settings = $this->getConfig();

        $this->saveResource("items.yml");
        $this->itemsConfig = new Config($this->getDataFolder() . "items.yml", Config::YAML);

        $this->saveResource("recipes.yml");
        $this->recipesConfig = new Config($this->getDataFolder() . "recipes.yml", Config::YAML);
    }

    private function checkDependencies(): void
    {
        $pluginManager = $this->getServer()->getPluginManager();

        $this->mebFormsAvailable = $pluginManager->getPlugin("MEBForms") !== null;
        $this->mebSocietyAvailable = $pluginManager->getPlugin("MEBSociety") !== null;
        $this->mebMobAIAvailable = $pluginManager->getPlugin("MEBMobAI") !== null;
    }

    private function registerListeners(): void
    {
        $pluginManager = $this->getServer()->getPluginManager();

        $pluginManager->registerEvents(new ItemUseListener($this), $this);

        // 创建并保存 PlayerListener 实例
        $this->playerListener = new PlayerListener($this);
        $pluginManager->registerEvents($this->playerListener, $this);

        if ($this->settings->getNested("settings.enable_drops", true)) {
            $pluginManager->registerEvents(new EntityDeathListener($this), $this);
            $pluginManager->registerEvents(new BlockBreakListener($this), $this);
        }
    }

    /**
     * 启动持有效果检测任务
     */
    private function startHoldEffectTask(): void
    {
        $this->getScheduler()->scheduleRepeatingTask(new class($this, $this->playerListener) extends \pocketmine\scheduler\Task {
            private Main $plugin;
            private \MengBao\MEBCustomItem\Listener\PlayerListener $playerListener;

            public function __construct(Main $plugin, \MengBao\MEBCustomItem\Listener\PlayerListener $playerListener)
            {
                $this->plugin = $plugin;
                $this->playerListener = $playerListener;
            }

            public function onRun(): void
            {
                foreach ($this->plugin->getServer()->getOnlinePlayers() as $player) {
                    $this->playerListener->checkAndApplyHoldEffects($player);
                }
            }
        }, 20); // 每秒检测一次
    }

    /**
     * 集成 MEBSociety 商店
     */
    private function integrateWithMEBSociety(): void
    {
        try {
            // 获取 MEBSociety 插件实例
            $mebSociety = $this->getServer()->getPluginManager()->getPlugin("MEBSociety");
            if ($mebSociety === null) {
                return;
            }

            // 获取 Shop 单例
            $shopClass = "MengBao\\MEBSociety\\Units\\Shop";
            if (!class_exists($shopClass)) {
                $this->getLogger()->warning("MEBSociety Shop 类不存在，无法集成商店");
                return;
            }

            $shop = $shopClass::getInstance($mebSociety);
            $registeredCount = 0;

            // 遍历所有自定义物品
            foreach ($this->itemManager->getAllItems() as $itemId => $customItem) {
                // 获取物品配置
                $obtainable = $customItem->getObtainable();

                // 检查是否允许在商店出售
                if (!isset($obtainable["shop"]) || !$obtainable["shop"]) {
                    continue;
                }

                // 获取价格信息
                $buyPrice = $customItem->getPrice();
                $sellPrice = $customItem->getSellPrice();

                if ($buyPrice <= 0 && $sellPrice <= 0) {
                    continue;
                }

                // 检查商店中是否已存在该物品
                $displayName = $customItem->getName();
                $alreadyExists = false;
                foreach ($shop->getAllShops() as $existingShop) {
                    if ($existingShop["名称"] === $displayName && $existingShop["类型"] === "item") {
                        $alreadyExists = true;
                        break;
                    }
                }

                if ($alreadyExists) {
                    continue; // 已存在，跳过
                }

                // 创建实际的自定义物品实例
                $itemInstance = $customItem->create();
                if ($itemInstance === null) {
                    continue;
                }

                // 注册到商店（传入完整的自定义物品对象）
                $baseItem = $customItem->getBaseItem();

                $shop->addItemShop(
                    $displayName,      // 显示名称
                    $baseItem,         // 基础物品ID
                    1,                 // 数量
                    (float)$buyPrice,  // 购买价格
                    (float)$sellPrice, // 出售价格
                    $itemInstance      // 自定义物品实例（包含NBT数据）
                );

                $registeredCount++;
            }

            if ($registeredCount > 0) {
                $this->getLogger()->info("§a已向 MEBSociety 商店注册 {$registeredCount} 个自定义物品");
            } else {
                $this->getLogger()->info("§e没有可注册到 MEBSociety 商店的物品（可能已存在）");
            }

        } catch (\Exception $e) {
            $this->getLogger()->error("集成 MEBSociety 商店失败: " . $e->getMessage());
        }
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool
    {
        if ($command->getName() !== "mebci") {
            return false;
        }
        return $this->router->dispatch($sender, $args);
    }

    public function reload(): void
    {
        $this->loadConfigs();
        $this->itemManager->loadItems();

        if ($this->settings->getNested("settings.enable_recipes", true)) {
            $this->recipeManager->unregisterAll();
            $this->recipeManager->registerRecipes();
        }
    }

    // Getters

    public static function getInstance(): self
    {
        return self::$instance;
    }

    public function getItemManager(): CustomItemManager
    {
        return $this->itemManager;
    }

    public function getRecipeManager(): RecipeManager
    {
        return $this->recipeManager;
    }

    public function getRouter(): CommandRouter
    {
        return $this->router;
    }

    public function getSettings(): Config
    {
        return $this->settings;
    }

    public function getItemsConfig(): Config
    {
        return $this->itemsConfig;
    }

    public function getRecipesConfig(): Config
    {
        return $this->recipesConfig;
    }

    public function isMEBFormsAvailable(): bool
    {
        return $this->mebFormsAvailable;
    }

    public function isMEBSocietyAvailable(): bool
    {
        return $this->mebSocietyAvailable;
    }

    public function isMEBMobAIAvailable(): bool
    {
        return $this->mebMobAIAvailable;
    }

    public function getItemEditorGUI(): ?ItemEditorGUI
    {
        return $this->itemEditorGUI;
    }

    public function getRecipeEditorGUI(): ?RecipeEditorGUI
    {
        return $this->recipeEditorGUI;
    }
}
