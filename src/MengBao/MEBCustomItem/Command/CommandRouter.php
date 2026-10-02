<?php

declare(strict_types=1);

namespace MengBao\MEBCustomItem\Command;

use MengBao\MEBCustomItem\Main;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;

/**
 * 命令路由器
 */
class CommandRouter
{
    private Main $plugin;

    public function __construct(Main $plugin)
    {
        $this->plugin = $plugin;
    }

    /**
     * 分发命令
     */
    public function dispatch(CommandSender $sender, array $args): bool
    {
        // 没有参数且是玩家，尝试打开GUI
        if (empty($args) && $sender instanceof Player) {
            if ($this->plugin->isMEBFormsAvailable()) {
                $itemEditorGUI = $this->plugin->getItemEditorGUI();
                if ($itemEditorGUI !== null) {
                    $itemEditorGUI->sendMainMenu($sender);
                    return true;
                } else {
                    $sender->sendMessage("§cGUI系统未初始化");
                    return true;
                }
            } else {
                $sender->sendMessage("§c未安装MEBForms，请使用 /mebci help 查看命令帮助");
                return true;
            }
        }

        // 没有参数且不是玩家
        if (empty($args)) {
            return $this->handleHelp($sender, []);
        }

        $subCommand = strtolower(array_shift($args));

        switch ($subCommand) {
            case "help":
                return $this->handleHelp($sender, $args);

            case "list":
                return $this->handleList($sender, $args);

            case "info":
                return $this->handleInfo($sender, $args);

            case "give":
                return $this->handleGive($sender, $args);

            case "reload":
                return $this->handleReload($sender, $args);

            default:
                $sender->sendMessage("§c未知的子命令: {$subCommand}");
                $sender->sendMessage("§e使用 /mebci help 查看帮助");
                return false;
        }
    }

    /**
     * 帮助命令
     */
    private function handleHelp(CommandSender $sender, array $args): bool
    {
        $sender->sendMessage("§l§b===== MEBCustomItem 帮助 =====");
        $sender->sendMessage("§f/mebci §a- 打开GUI管理界面（需要MEBForms）");
        $sender->sendMessage("§f/mebci help §a- 显示此帮助信息");
        $sender->sendMessage("§f/mebci list §a- 列出所有自定义物品");
        $sender->sendMessage("§f/mebci info <物品ID> §a- 查看物品详细信息");
        $sender->sendMessage("§f/mebci give <玩家> <物品ID> [数量] §a- 给予玩家物品");
        $sender->sendMessage("§f/mebci reload §a- 重载配置");
        return true;
    }

    /**
     * 列表命令
     */
    private function handleList(CommandSender $sender, array $args): bool
    {
        $items = $this->plugin->getItemManager()->getAllItems();

        if (empty($items)) {
            $sender->sendMessage("§e暂无自定义物品");
            return true;
        }

        $sender->sendMessage("§l§b===== 自定义物品列表 =====");
        foreach ($items as $id => $item) {
            $sender->sendMessage("§f- §e{$id} §7: {$item->getName()}");
        }
        $sender->sendMessage("§f总计: §e" . count($items) . " §f个物品");

        return true;
    }

    /**
     * 信息命令
     */
    private function handleInfo(CommandSender $sender, array $args): bool
    {
        if (empty($args)) {
            $sender->sendMessage("§c用法: /mebci info <物品ID>");
            return false;
        }

        $itemId = $args[0];
        $item = $this->plugin->getItemManager()->getItem($itemId);

        if ($item === null) {
            $sender->sendMessage("§c物品 {$itemId} 不存在");
            return false;
        }

        $sender->sendMessage("§l§b===== 物品信息: {$itemId} =====");
        $sender->sendMessage("§f名称: {$item->getName()}");
        $sender->sendMessage("§f基础物品: §e{$item->getBaseItem()}");
        $sender->sendMessage("§f自定义模型: §e" . ($item->isCustomModel() ? "是" : "否"));

        if (!empty($item->getLore())) {
            $sender->sendMessage("§f描述:");
            foreach ($item->getLore() as $line) {
                $sender->sendMessage("  {$line}");
            }
        }

        if (!empty($item->getEnchantments())) {
            $sender->sendMessage("§f附魔数量: §e" . count($item->getEnchantments()));
        }

        $obtainable = $item->getObtainable();
        $sender->sendMessage("§f可合成: §e" . ($item->isCraftable() ? "是" : "否"));
        $sender->sendMessage("§f可掉落: §e" . ($item->isDroppable() ? "是" : "否"));
        $sender->sendMessage("§f可购买: §e" . ($item->isShoppable() ? "是 (价格: {$item->getPrice()})" : "否"));

        return true;
    }

    /**
     * 给予命令
     */
    private function handleGive(CommandSender $sender, array $args): bool
    {
        if (!$sender->hasPermission("MEBCustomItem.admin")) {
            $sender->sendMessage("§c你没有权限使用此命令");
            return false;
        }

        if (count($args) < 2) {
            $sender->sendMessage("§c用法: /mebci give <玩家> <物品ID> [数量]");
            return false;
        }

        $playerName = $args[0];
        $itemId = $args[1];
        $count = isset($args[2]) ? max(1, (int)$args[2]) : 1;

        $player = $this->plugin->getServer()->getPlayerExact($playerName);
        if ($player === null) {
            $sender->sendMessage("§c玩家 {$playerName} 不在线");
            return false;
        }

        $item = $this->plugin->getItemManager()->getItem($itemId);
        if ($item === null) {
            $sender->sendMessage("§c物品 {$itemId} 不存在");
            return false;
        }

        if ($this->plugin->getItemManager()->giveItem($player, $itemId, $count)) {
            $sender->sendMessage("§a已给予 {$playerName} {$count} 个 {$item->getName()}");
            $player->sendMessage("§a你收到了 {$count} 个 {$item->getName()}");
            return true;
        } else {
            $sender->sendMessage("§c给予物品失败");
            return false;
        }
    }

    /**
     * 重载命令
     */
    private function handleReload(CommandSender $sender, array $args): bool
    {
        if (!$sender->hasPermission("MEBCustomItem.admin")) {
            $sender->sendMessage("§c你没有权限使用此命令");
            return false;
        }

        $sender->sendMessage("§e正在重载配置...");
        $this->plugin->reload();
        $sender->sendMessage("§a配置已重载！");

        return true;
    }
}
