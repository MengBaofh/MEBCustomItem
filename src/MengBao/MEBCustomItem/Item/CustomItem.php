<?php

declare(strict_types=1);

namespace MengBao\MEBCustomItem\Item;

use pocketmine\item\Item;
use pocketmine\item\StringToItemParser;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\enchantment\VanillaEnchantments;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\player\Player;
use pocketmine\entity\projectile\Snowball;
use pocketmine\world\sound\BlazeShootSound;
use pocketmine\entity\Location;

/**
 * 自定义物品类
 */
class CustomItem
{
    // NBT标签
    public const TAG_CUSTOM_ITEM = "MEBCustomItem";
    public const TAG_ITEM_ID = "CustomItemId";
    public const TAG_DURABILITY = "CustomDurability";
    public const TAG_MAX_DURABILITY = "CustomMaxDurability";
    public const TAG_COOLDOWN = "LastUseTime";
    public const TAG_CUSTOM_MODEL_DATA = "CustomModelData";
    public const TAG_TEXTURE_PATH = "TexturePath";
    public const TAG_MODEL_PATH = "ModelPath";

    private string $id;
    private string $name;
    private array $lore;
    private string $baseItem;
    private bool $customModel;
    private string $texturePath;
    private string $modelPath;
    private int $count;
    private array $enchantments;
    private array $attributes;
    private array $effects;
    private array $obtainable;

    public function __construct(string $id, array $config)
    {
        $this->id = $id;
        $this->name = $config["name"] ?? "§fCustom Item";
        $this->lore = $config["lore"] ?? [];
        $this->baseItem = $config["base_item"] ?? "minecraft:stone";
        $this->customModel = $config["custom_model"] ?? false;
        $this->texturePath = $config["texture_path"] ?? "";
        $this->modelPath = $config["model_path"] ?? "";
        $this->count = max(1, $config["count"] ?? 1);
        $this->enchantments = $config["enchantments"] ?? [];
        $this->attributes = $config["attributes"] ?? [];
        $this->effects = $config["effects"] ?? [];
        $this->obtainable = $config["obtainable"] ?? [];
    }

    /**
     * 创建物品实例
     */
    public function create(int $count = 1): ?Item
    {
        $item = $this->parseBaseItem();
        if ($item === null) {
            return null;
        }

        $item->setCount($count);
        $item->setCustomName($this->name);
        $item->setLore($this->lore);

        // 添加附魔
        foreach ($this->enchantments as $enchant) {
            $enchantmentInstance = $this->parseEnchantment($enchant);
            if ($enchantmentInstance !== null) {
                $item->addEnchantment($enchantmentInstance);
            }
        }

        // 添加NBT标签标记为自定义物品
        $nbt = $item->getNamedTag();
        $nbt->setString(self::TAG_CUSTOM_ITEM, "true");
        $nbt->setString(self::TAG_ITEM_ID, $this->id);

        // 设置自定义耐久度
        if (isset($this->attributes["durability"]) && $this->attributes["durability"] > 0) {
            $nbt->setInt(self::TAG_DURABILITY, (int)$this->attributes["durability"]);
            $nbt->setInt(self::TAG_MAX_DURABILITY, (int)$this->attributes["durability"]);
        }

        // 设置自定义模型数据
        if ($this->customModel) {
            // CustomModelData 用于资源包识别自定义模型
            // 使用物品ID的哈希值作为模型数据
            $modelData = crc32($this->id);
            $nbt->setInt(self::TAG_CUSTOM_MODEL_DATA, $modelData);

            // 保存材质和模型路径信息
            if (!empty($this->texturePath)) {
                $nbt->setString(self::TAG_TEXTURE_PATH, $this->texturePath);
            }
            if (!empty($this->modelPath)) {
                $nbt->setString(self::TAG_MODEL_PATH, $this->modelPath);
            }
        }

        $item->setNamedTag($nbt);

        return $item;
    }

    /**
     * 检查是否为此自定义物品
     */
    public function isThisItem(Item $item): bool
    {
        $nbt = $item->getNamedTag();
        return $nbt->getString(self::TAG_CUSTOM_ITEM, "") === "true" &&
               $nbt->getString(self::TAG_ITEM_ID, "") === $this->id;
    }

    /**
     * 解析基础物品
     */
    private function parseBaseItem(): ?Item
    {
        $parser = StringToItemParser::getInstance();
        return $parser->parse($this->baseItem);
    }

    /**
     * 解析附魔
     */
    private function parseEnchantment(array $enchant): ?EnchantmentInstance
    {
        $id = $enchant["id"] ?? -1;
        $level = $enchant["level"] ?? 1;

        // 通过ID获取附魔
        $enchantmentIdMap = \pocketmine\data\bedrock\EnchantmentIdMap::getInstance();
        try {
            $enchantmentType = $enchantmentIdMap->fromId($id);
            return new EnchantmentInstance($enchantmentType, $level);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * 应用物品效果
     */
    public function applyEffect(Player $player, string $trigger, Item $item, $event = null): bool
    {
        if (!isset($this->effects[$trigger]) || !($this->effects[$trigger]["enabled"] ?? false)) {
            return false;
        }

        $effect = $this->effects[$trigger];
        $type = $effect["type"] ?? "";

        switch ($type) {
            case "projectile":
                return $this->applyProjectileEffect($player, $effect);

            case "heal":
                return $this->applyHealEffect($player, $effect);

            case "effect":
                return $this->applyPotionEffect($player, $effect, $event);

            case "teleport":
                return $this->applyTeleportEffect($player, $effect);

            default:
                return false;
        }
    }

    /**
     * 发射物效果
     */
    private function applyProjectileEffect(Player $player, array $effect): bool
    {
        $projectileType = $effect["projectile"] ?? "fireball";

        // 获取玩家位置和朝向
        $location = $player->getLocation();
        $directionVector = $player->getDirectionVector();

        // 创建发射物实体（使用雪球作为火球的替代）
        $projectile = new Snowball(
            Location::fromObject(
                $player->getEyePos(),
                $player->getWorld(),
                $location->yaw,
                $location->pitch
            ),
            $player
        );

        // 设置发射物速度
        $projectile->setMotion($directionVector->multiply(1.5));

        // 生成实体
        $projectile->spawnToAll();

        // 播放音效
        $player->getWorld()->addSound($player->getLocation(), new BlazeShootSound());

        // 显示消息
        if (isset($effect["message"])) {
            $player->sendMessage($effect["message"]);
        }

        return true;
    }

    /**
     * 治疗效果
     */
    private function applyHealEffect(Player $player, array $effect): bool
    {
        $amount = $effect["amount"] ?? 20;
        $player->setHealth(min($player->getMaxHealth(), $player->getHealth() + $amount));

        // 应用额外药水效果
        if (isset($effect["effects"])) {
            foreach ($effect["effects"] as $potionEffect) {
                $this->addPotionEffect($player, $potionEffect);
            }
        }

        if (isset($effect["message"])) {
            $player->sendMessage($effect["message"]);
        }

        return true;
    }

    /**
     * 药水效果
     */
    private function applyPotionEffect(Player $player, array $effect, $event): bool
    {
        $target = $effect["target"] ?? "self";
        $effects = $effect["effects"] ?? [];

        $targets = [];
        if ($target === "self" || $target === "both") {
            $targets[] = $player;
        }

        if (($target === "enemy" || $target === "both") && $event !== null) {
            if (method_exists($event, "getEntity")) {
                $entity = $event->getEntity();
                if ($entity instanceof Player) {
                    $targets[] = $entity;
                }
            }
        }

        foreach ($targets as $targetEntity) {
            foreach ($effects as $potionEffect) {
                $this->addPotionEffect($targetEntity, $potionEffect);
            }
        }

        return true;
    }

    /**
     * 添加药水效果
     */
    private function addPotionEffect(Player $player, array $potionEffect): void
    {
        $effectId = $potionEffect["effect_id"] ?? -1;
        $duration = $potionEffect["duration"] ?? 100;
        $amplifier = $potionEffect["amplifier"] ?? 0;

        if ($effectId < 0) {
            return;
        }

        try {
            // 数字ID映射
            $effectIdMap = [
                1 => \pocketmine\entity\effect\VanillaEffects::SPEED(),
                2 => \pocketmine\entity\effect\VanillaEffects::SLOWNESS(),
                3 => \pocketmine\entity\effect\VanillaEffects::HASTE(),
                4 => \pocketmine\entity\effect\VanillaEffects::MINING_FATIGUE(),
                5 => \pocketmine\entity\effect\VanillaEffects::STRENGTH(),
                6 => \pocketmine\entity\effect\VanillaEffects::INSTANT_HEALTH(),
                7 => \pocketmine\entity\effect\VanillaEffects::INSTANT_DAMAGE(),
                8 => \pocketmine\entity\effect\VanillaEffects::JUMP_BOOST(),
                9 => \pocketmine\entity\effect\VanillaEffects::NAUSEA(),
                10 => \pocketmine\entity\effect\VanillaEffects::REGENERATION(),
                11 => \pocketmine\entity\effect\VanillaEffects::RESISTANCE(),
                12 => \pocketmine\entity\effect\VanillaEffects::FIRE_RESISTANCE(),
                13 => \pocketmine\entity\effect\VanillaEffects::WATER_BREATHING(),
                14 => \pocketmine\entity\effect\VanillaEffects::INVISIBILITY(),
                15 => \pocketmine\entity\effect\VanillaEffects::BLINDNESS(),
                16 => \pocketmine\entity\effect\VanillaEffects::NIGHT_VISION(),
                17 => \pocketmine\entity\effect\VanillaEffects::HUNGER(),
                18 => \pocketmine\entity\effect\VanillaEffects::WEAKNESS(),
                19 => \pocketmine\entity\effect\VanillaEffects::POISON(),
                20 => \pocketmine\entity\effect\VanillaEffects::WITHER(),
                22 => \pocketmine\entity\effect\VanillaEffects::ABSORPTION(),
                23 => \pocketmine\entity\effect\VanillaEffects::SATURATION(),
            ];

            // 字符串名称映射
            $effectNameMap = [
                "speed" => \pocketmine\entity\effect\VanillaEffects::SPEED(),
                "slowness" => \pocketmine\entity\effect\VanillaEffects::SLOWNESS(),
                "haste" => \pocketmine\entity\effect\VanillaEffects::HASTE(),
                "mining_fatigue" => \pocketmine\entity\effect\VanillaEffects::MINING_FATIGUE(),
                "strength" => \pocketmine\entity\effect\VanillaEffects::STRENGTH(),
                "instant_health" => \pocketmine\entity\effect\VanillaEffects::INSTANT_HEALTH(),
                "instant_damage" => \pocketmine\entity\effect\VanillaEffects::INSTANT_DAMAGE(),
                "jump_boost" => \pocketmine\entity\effect\VanillaEffects::JUMP_BOOST(),
                "nausea" => \pocketmine\entity\effect\VanillaEffects::NAUSEA(),
                "regeneration" => \pocketmine\entity\effect\VanillaEffects::REGENERATION(),
                "resistance" => \pocketmine\entity\effect\VanillaEffects::RESISTANCE(),
                "fire_resistance" => \pocketmine\entity\effect\VanillaEffects::FIRE_RESISTANCE(),
                "water_breathing" => \pocketmine\entity\effect\VanillaEffects::WATER_BREATHING(),
                "invisibility" => \pocketmine\entity\effect\VanillaEffects::INVISIBILITY(),
                "blindness" => \pocketmine\entity\effect\VanillaEffects::BLINDNESS(),
                "night_vision" => \pocketmine\entity\effect\VanillaEffects::NIGHT_VISION(),
                "hunger" => \pocketmine\entity\effect\VanillaEffects::HUNGER(),
                "weakness" => \pocketmine\entity\effect\VanillaEffects::WEAKNESS(),
                "poison" => \pocketmine\entity\effect\VanillaEffects::POISON(),
                "wither" => \pocketmine\entity\effect\VanillaEffects::WITHER(),
                "absorption" => \pocketmine\entity\effect\VanillaEffects::ABSORPTION(),
                "saturation" => \pocketmine\entity\effect\VanillaEffects::SATURATION(),
            ];

            $effectType = null;

            // 先尝试数字ID
            if (is_numeric($effectId)) {
                $effectType = $effectIdMap[(int)$effectId] ?? null;
            }

            // 如果数字ID没找到，尝试字符串名称
            if ($effectType === null) {
                $effectType = $effectNameMap[strtolower((string)$effectId)] ?? null;
            }

            if ($effectType !== null) {
                // duration 为 -1 时设置为持续效果（40秒，每秒刷新）
                $effectDuration = ($duration === -1) ? 40 : $duration;
                $effect = new \pocketmine\entity\effect\EffectInstance($effectType, $effectDuration, $amplifier);
                $player->getEffects()->add($effect);
            }
        } catch (\Exception $e) {
            // 忽略无效的效果ID
        }
    }

    /**
     * 传送效果
     */
    private function applyTeleportEffect(Player $player, array $effect): bool
    {
        // TODO: 实现传送效果
        if (isset($effect["message"])) {
            $player->sendMessage($effect["message"]);
        }

        return true;
    }

    /**
     * 检查冷却时间
     */
    public function isOnCooldown(Item $item, string $trigger): bool
    {
        if (!isset($this->effects[$trigger])) {
            return false;
        }

        $cooldown = $this->effects[$trigger]["cooldown"] ?? 0;
        if ($cooldown <= 0) {
            return false;
        }

        $nbt = $item->getNamedTag();
        $lastUseTime = $nbt->getLong(self::TAG_COOLDOWN . "_" . $trigger, 0);
        $currentTime = time();

        return ($currentTime - $lastUseTime) < $cooldown;
    }

    /**
     * 设置使用时间
     */
    public function setLastUseTime(Item $item, string $trigger): void
    {
        $nbt = $item->getNamedTag();
        $nbt->setLong(self::TAG_COOLDOWN . "_" . $trigger, time());
        $item->setNamedTag($nbt);
    }

    /**
     * 消耗物品成本
     */
    public function consumeCost(Player $player, Item $item, string $trigger): bool
    {
        if (!isset($this->effects[$trigger]["cost"])) {
            return true;
        }

        $cost = $this->effects[$trigger]["cost"];
        $type = $cost["type"] ?? "none";
        $value = $cost["value"] ?? 0;

        switch ($type) {
            case "durability":
                return $this->consumeDurability($item, $value);

            case "item":
                // TODO: 实现物品消耗
                return true;

            case "none":
            default:
                return true;
        }
    }

    /**
     * 消耗耐久度
     */
    private function consumeDurability(Item $item, int $amount): bool
    {
        $nbt = $item->getNamedTag();
        $currentDurability = $nbt->getInt(self::TAG_DURABILITY, -1);

        if ($currentDurability === -1) {
            return true; // 无耐久限制
        }

        $newDurability = $currentDurability - $amount;
        if ($newDurability <= 0) {
            return false; // 耐久度耗尽
        }

        $nbt->setInt(self::TAG_DURABILITY, $newDurability);
        $item->setNamedTag($nbt);

        return true;
    }

    // Getters

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getLore(): array
    {
        return $this->lore;
    }

    public function getBaseItem(): string
    {
        return $this->baseItem;
    }

    public function isCustomModel(): bool
    {
        return $this->customModel;
    }

    public function getTexturePath(): string
    {
        return $this->texturePath;
    }

    public function getModelPath(): string
    {
        return $this->modelPath;
    }

    public function getCount(): int
    {
        return $this->count;
    }

    public function getEnchantments(): array
    {
        return $this->enchantments;
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function getEffects(): array
    {
        return $this->effects;
    }

    public function getObtainable(): array
    {
        return $this->obtainable;
    }

    public function getPrice(): float
    {
        return (float)($this->obtainable["price"] ?? 0);
    }

    public function getSellPrice(): float
    {
        return (float)($this->obtainable["sell_price"] ?? 0);
    }

    public function isCraftable(): bool
    {
        return (bool)($this->obtainable["craft"] ?? false);
    }

    public function isDroppable(): bool
    {
        return (bool)($this->obtainable["drop"] ?? false);
    }

    public function isShoppable(): bool
    {
        return (bool)($this->obtainable["shop"] ?? false);
    }
}
