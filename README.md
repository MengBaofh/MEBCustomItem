# MEBCustomItem - 自定义物品插件

强大的自定义物品、配方和装备效果管理系统，可用于RPG定义武器装备。

[![PocketMine-MP](https://img.shields.io/badge/PocketMine--MP-5.0-blue)](https://github.com/pmmp/PocketMine-MP)
[![PHP](https://img.shields.io/badge/PHP-8.0%2B-purple)](https://www.php.net/)
[![MEB交流群](https://img.shields.io/badge/MEB交流群-495262926-orange?style=flat-square&logo=tencentqq)](https://qun.qq.com/universal-share/share?ac=1&authKey=HJqOZiQeXeja5NyPiqbfbPPRGX6UdRYf%2FZ8jxAr5B52Bl8a2L4ZqpgQ4%2FZ2JTQ%2BG&busi_data=eyJncm91cENvZGUiOiI0OTUyNjI5MjYiLCJ0b2tlbiI6Imh4Q01pWkpkQVgvekFSK0cwbTJjWU5xdHFBMGJJN01qQVN6SmhRMUZHTmcwRzNBOXpvdlArcW1EaTRNcFI1MEsiLCJ1aW4iOiI4MjU1ODUzOTgifQ%3D%3D&data=_H46ENc_fxiIeBZm8xNKqFoGMVQ2ZbAayO2_xLQ7-24neRXx2M6uoWZqOCk2iPBw_MgYalDv4PNB8uOLvhl3ww&svctype=4&tempid=h5_group_info)

> ⚠️ **依赖插件**：[MEBForms](https://github.com/MengBaofh/MEBForms) (可选，解锁GUI实时配置界面，未安装则只能在配置文件中修改), [MEBSociety](https://github.com/MengBaofh/MEBSociety) (可选，安装后解锁自定义物品商店，会自动注册可售卖的自定义物品至MEB商店)

---

## 📋 目录

- [功能特性](#-功能特性)
- [安装说明](#-安装说明)
- [使用指南](#-使用指南)
- [配置说明](#-配置说明)
- [注意事项](#-注意事项)

---

## ✨ 功能特性

### 🎨 自定义物品系统
- **可视化 GUI 创建自定义物品**：通过表单界面轻松创建、修改或删除自定义物品（需要MEBForms）。
- **完整的物品属性配置**：
  - 基础属性：名称、描述（支持颜色代码）、材质、最大堆叠数量
  - 装备属性：攻击伤害、护甲值、耐久度
  - 特殊效果：持有效果（药水效果）、速度倍率

### 🎨 自定义材质系统（测试中，慎用）
支持为自定义物品设置独特的材质外观：

#### 材质配置方式
- **使用原版物品材质**：直接指定原版物品 ID
  - 格式：`minecraft:物品ID`
  - 示例：`minecraft:diamond_sword`、`minecraft:golden_apple`
  - 优点：无需资源包，即开即用
  
- **使用自定义材质**：通过资源包加载自定义材质
  - 在资源包中定义物品材质和模型
  - 插件自动应用材质到自定义物品
  - 支持完整的 3D 模型和纹理

#### 材质特性
- ✅ **完整的物品外观**：自定义物品在游戏中显示指定的材质和模型
- ✅ **NBT 数据保存**：材质信息保存在物品 NBT 中，掉落、交易不丢失
- ✅ **兼容性强**：支持所有原版物品类型作为基础材质
- ✅ **资源包集成**：配合资源包可实现完全自定义的物品外观
- ✅ **热更新支持**：修改材质配置后无需重启服务器

#### 使用示例
```yaml
items:
  flame_sword:
    texture: "minecraft:diamond_sword"  # 使用钻石剑的材质
    # 物品在游戏中显示为钻石剑的外观，但拥有自定义的属性
  
  custom_wand:
    texture: "minecraft:blaze_rod"      # 使用烈焰棒的材质
    # 配合资源包可以显示为魔杖
```

#### 资源包配合使用
1. 创建资源包，定义自定义物品的材质和模型
2. 在 `items.yml` 中设置 `texture` 为对应的原版物品 ID
3. 玩家安装资源包后，自定义物品显示为资源包中定义的外观
4. 未安装资源包的玩家看到的是原版物品外观

> **提示**：通过材质系统，你可以创建完全自定义外观的武器、工具、装备和道具，极大地丰富游戏内容！

### ⚒️ 自定义合成配方系统
为自定义的物品创建合成配方，支持三种配方类型，都可通过 GUI 可视化管理（需要MEBForms）：

#### 1️⃣ 有序合成配方（Shaped Recipe）
- 工作台 3x3 网格布局，材料位置固定，必须按照特定形状摆放
- 支持最多 9 个材料映射（A-I 字符映射）
- 优先级高于无序合成，同时存在时仅使用有序合成配方

#### 2️⃣ 无序合成配方（Shapeless Recipe）
- 材料顺序和位置不限，只要材料齐全即可合成
- 支持最多 9 种材料

#### 3️⃣ 熔炉冶炼配方（Smelting Recipe）
- 支持自定义熔炉配方
- 输入材料 → 输出物品

#### 配方管理特性
- ✅ **配方编辑**：可实时新增或修改已有配方的所有参数，自动定位选项
- ✅ **配方删除**：支持GUI删除配方，但由于PM5核心限制，仍需要重启服务器才会生效
- ✅ **分页浏览**：配方列表支持分页显示

### 🛡️ 装备效果系统
- **持有效果（Hold Effects）**：
  - 手持或穿戴物品时自动施加药水效果
  - 支持所有原版药水效果（力量、速度、再生等）
  - 多件装备效果可叠加
  - 每秒自动检测并应用
  
- **速度倍率系统**：
  - 为装备设置移动速度倍率
  - 智能缓存机制，确保速度稳定不抖动
  - 多件装备速度倍率相乘叠加
  - 实时更新，无延迟

### 🎁 掉落系统（测试中，慎用）
- **怪物掉落**：配置怪物击杀时掉落自定义物品
- **方块掉落**：配置方块破坏时掉落自定义物品
- 支持掉落概率、数量范围、工具要求等

### 🏪 商店集成
- 集成 **MEBSociety** 商店系统（需安装MEBSociety）
- 自定义物品可直接在商店中交易
- 完整保留物品的所有属性和 NBT 数据

---

## 📦 安装说明

1. **下载插件**
   - 将 `phar` 文件放入服务器的 `plugins` 目录

2. **安装依赖**
   - **可选**：安装 **MEBForms** 插件（GUI 系统依赖）
   - **可选**：安装 **MEBSociety**（商店集成）

3. **启动服务器**
   - 首次启动会自动生成配置文件和示例物品

---

## 🎮 使用指南

### 基础命令
```
/mebci          - 打开主菜单（GUI 管理界面，需要MEBForms）
/mebci help     - 显示帮助信息
/mebci reload   - 重载配置文件（删除配方需要重启服务器，该指令无效）
/mebci give <玩家> <物品ID> [数量]  - 给予玩家自定义物品
/mebci list     - 列出所有已注册的自定义物品
```

### 编辑和删除
- **编辑物品/配方**：
  1. 在列表中点击要编辑的物品/配方
  2. 选择 **"编辑"**
  3. 修改参数后保存
  4. 自动重载配置
  
- **删除物品/配方**：
  1. 在列表中点击要删除的物品/配方
  2. 选择 **"删除"**
  3. 确认删除
  4. **注意**：删除配方后会禁用工作台，需重启服务器完全生效

---

## ⚙️ 配置说明

### 目录结构
```
plugin_data/MEBCustomItem/
├── config.yml          # 主配置文件
├── items.yml           # 物品配置文件
└── recipes.yml         # 配方配置文件
```

### 物品配置示例：`items.yml`
```yaml
items:
  flame_sword:
    name: "§c§l烈焰之剑"
    lore:
      - "§7一把燃烧着烈焰的神剑"
      - "§e持有时获得力量效果"
    texture: "minecraft:diamond_sword"
    type: "sword"
    max_stack_size: 1
    attributes:
      attack_damage: 10
      durability: 2000
      hold_effects:
        - effect: "strength"
          amplifier: 1
          visible: true
      speed_multiplier: 1.2
```

### 配方配置示例：`recipes.yml`
```yaml
recipes:
  crafting:
    # 有序合成配方
    flame_sword:
      type: "shaped"
      shape:
        - "DFD"
        - "DSD"
        - " B "
      ingredients:
        D: "minecraft:diamond"
        F: "minecraft:blaze_powder"
        S: "minecraft:diamond_sword"
        B: "minecraft:stick"
      result:
        item_id: "flame_sword"
        count: 1
    
    # 无序合成配方
    healing_apple:
      type: "shapeless"
      ingredients:
        - "minecraft:golden_apple"
        - "minecraft:gold_ingot"
        - "minecraft:gold_ingot"
        - "minecraft:gold_ingot"
        - "minecraft:glowstone_dust"
        - "minecraft:glowstone_dust"
      result:
        item_id: "healing_apple"
        count: 1
  
  # 熔炉配方
  smelting:
    custom_ingot:
      type: "smelting"
      input: "minecraft:iron_ore"
      result:
        item_id: "custom_ingot"
        count: 1
```

### 主配置文件：`config.yml`
```yaml
settings:
  enable_recipes: true      # 是否启用配方系统
  enable_drops: true        # 是否启用掉落系统
  enable_shop: true         # 是否启用商店集成
```

---

## ⚠️ 注意事项

### 配方删除
- 删除配方后，配置文件会立即更新
- 但游戏中的工作台配方需要**重启服务器**才能完全清除
- 提示信息：**"工作台已禁用，请重启服务器使配置生效！"**
- 建议在服务器维护时批量删除配方

### 物品 ID 命名规范
- 只能使用小写字母、数字和下划线
- 不能与原版物品 ID 冲突
- 建议使用描述性名称（如 `flame_sword`、`healing_apple`）
- 避免使用特殊字符和空格

### 速度倍率设置
- 倍率值建议在 **0.5 - 2.0** 之间
- 过高的速度可能导致移动异常或游戏崩溃
- 多件装备的速度倍率会**相乘叠加**
- 示例：两件 1.5 倍速度的装备 = 2.25 倍速度

### 材质路径
- 使用原版材质：`minecraft:物品ID`（如 `minecraft:diamond_sword`）
- 使用资源包材质需要客户端安装对应资源包
- 材质名称必须与 PocketMine 物品 ID 匹配