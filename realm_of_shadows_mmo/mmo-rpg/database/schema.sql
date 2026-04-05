-- ============================================================================
-- ============================================================================
--  MMORPG DATABASE SCHEMA  /  БАЗА ДАННЫХ ММОRPG
--  Browser-based text MMORPG (similar to neverlands.ru)
--  Текстовая браузерная ММОRPG
-- ============================================================================
-- ============================================================================
--  Engine:    InnoDB (MySQL 8.0+)
--  Charset:   utf8mb4 / Collation: utf8mb4_unicode_ci
--  Author:    Schema Generator
--  Version:   1.1.0 (added skills table, in_combat column)
--  Date:      2026
-- ============================================================================
-- ============================================================================

SET NAMES utf8mb4;

-- Устанавливаем кодировку по умолчанию / Set default charset
SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================================
-- Таблица: users / Пользователи системы
-- Хранит учётные записи игроков, модераторов и администраторов
-- ============================================================================
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
    `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT  COMMENT 'Уникальный идентификатор пользователя / Unique user ID',
    `username`        VARCHAR(50)     NOT NULL                 COMMENT 'Имя пользователя (логин) / Username (login)',
    `email`           VARCHAR(255)    NOT NULL                 COMMENT 'Электронная почта / Email address',
    `password_hash`   VARCHAR(255)    NOT NULL                 COMMENT 'Хеш пароля (bcrypt/argon2) / Password hash',
    `role`            ENUM('player','moderator','admin') NOT NULL DEFAULT 'player'
                                                                COMMENT 'Роль пользователя / User role',
    `is_active`       BOOLEAN         NOT NULL DEFAULT TRUE    COMMENT 'Активен ли аккаунт / Is account active',
    `created_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP
                                                                COMMENT 'Дата регистрации / Registration timestamp',
    `updated_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                                                                COMMENT 'Дата последнего обновления / Last update timestamp',
    `last_login_at`   DATETIME        NULL                     COMMENT 'Дата последнего входа / Last login timestamp',
    `last_ip`         VARCHAR(45)     NULL                     COMMENT 'Последний IP-адрес (IPv4/IPv6) / Last IP address',

    PRIMARY KEY (`id`),

    UNIQUE KEY `uk_users_username` (`username`),
    UNIQUE KEY `uk_users_email`    (`email`),

    INDEX `idx_users_username` (`username`),
    INDEX `idx_users_email`    (`email`),
    INDEX `idx_users_role`     (`role`),
    INDEX `idx_users_active`   (`is_active`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Пользователи системы / System users (accounts)';


-- ============================================================================
-- Таблица: characters / Игровые персонажи
-- Основная таблица персонажей, привязанных к учётным записям
-- ============================================================================
DROP TABLE IF EXISTS `characters`;
CREATE TABLE `characters` (
    `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT  COMMENT 'Уникальный идентификатор персонажа / Unique character ID',
    `user_id`         BIGINT UNSIGNED NOT NULL                 COMMENT 'ID владельца (users.id) / Owner user ID',
    `name`            VARCHAR(50)     NOT NULL                 COMMENT 'Имя персонажа / Character name',
    `race`            ENUM('human','elf','dwarf','orc','undead') NOT NULL DEFAULT 'human'
                                                                COMMENT 'Раса персонажа / Character race',
    `class`           ENUM('warrior','mage','rogue','paladin','archer') NOT NULL DEFAULT 'warrior'
                                                                COMMENT 'Класс персонажа / Character class',
    `level`           INT UNSIGNED    NOT NULL DEFAULT 1       COMMENT 'Уровень персонажа / Character level',
    `experience`      BIGINT UNSIGNED NOT NULL DEFAULT 0       COMMENT 'Текущий опыт / Current experience points',
    `current_hp`      INT UNSIGNED    NOT NULL DEFAULT 100     COMMENT 'Текущее здоровье / Current HP',
    `max_hp`          INT UNSIGNED    NOT NULL DEFAULT 100     COMMENT 'Максимальное здоровье / Maximum HP',
    `current_mana`    INT UNSIGNED    NOT NULL DEFAULT 50      COMMENT 'Текущая мана / Current mana',
    `max_mana`        INT UNSIGNED    NOT NULL DEFAULT 50      COMMENT 'Максимальная мана / Maximum mana',
    `current_energy`  INT UNSIGNED    NOT NULL DEFAULT 100     COMMENT 'Текущая энергия / Current energy',
    `max_energy`      INT UNSIGNED    NOT NULL DEFAULT 100     COMMENT 'Максимальная энергия / Maximum energy',
    `gold`            BIGINT UNSIGNED NOT NULL DEFAULT 100     COMMENT 'Золото / Gold',
    `silver`          INT UNSIGNED    NOT NULL DEFAULT 0       COMMENT 'Серебро / Silver',
    `rating`          INT             NOT NULL DEFAULT 0       COMMENT 'Рейтинг (PvP/достижения) / Rating',
    `location_id`     BIGINT UNSIGNED NULL                     COMMENT 'Текущая локация / Current location ID',
    `is_alive`        BOOLEAN         NOT NULL DEFAULT TRUE    COMMENT 'Жив ли персонаж / Is character alive',
    `deaths`          INT UNSIGNED    NOT NULL DEFAULT 0       COMMENT 'Количество смертей / Death count',
    `kills`           INT UNSIGNED    NOT NULL DEFAULT 0       COMMENT 'Количество убийств / Kill count',
    `in_combat`       BOOLEAN         NOT NULL DEFAULT FALSE   COMMENT 'Находится ли персонаж в бою / Is in active battle',
    `created_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP
                                                                COMMENT 'Дата создания персонажа / Character creation timestamp',
    `updated_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                                                                COMMENT 'Дата последнего обновления / Last update timestamp',

    PRIMARY KEY (`id`),

    UNIQUE KEY `uk_characters_name` (`name`),

    INDEX `idx_characters_user_id`     (`user_id`),
    INDEX `idx_characters_name`        (`name`),
    INDEX `idx_characters_location_id` (`location_id`),
    INDEX `idx_characters_race`        (`race`),
    INDEX `idx_characters_class`       (`class`),
    INDEX `idx_characters_level`       (`level`),
    INDEX `idx_characters_rating`      (`rating`),
    INDEX `idx_characters_alive`       (`is_alive`),

    CONSTRAINT `fk_characters_user_id`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,

    CONSTRAINT `fk_characters_location_id`
        FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,

    CONSTRAINT `chk_characters_level`       CHECK (`level` >= 1 AND `level` <= 100),
    CONSTRAINT `chk_characters_hp`          CHECK (`current_hp` <= `max_hp`),
    CONSTRAINT `chk_characters_mana`        CHECK (`current_mana` <= `max_mana`),
    CONSTRAINT `chk_characters_energy`      CHECK (`current_energy` <= `max_energy`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Игровые персонажи / Player characters';


-- ============================================================================
-- Таблица: character_stats / Характеристики персонажей
-- Детальные параметры персонажа (один-к-одному с characters)
-- ============================================================================
DROP TABLE IF EXISTS `character_stats`;
CREATE TABLE `character_stats` (
    `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT  COMMENT 'Уникальный идентификатор / Unique ID',
    `character_id`    BIGINT UNSIGNED NOT NULL                 COMMENT 'ID персонажа (characters.id) / Character ID',
    `strength`        INT             NOT NULL DEFAULT 10      COMMENT 'Сила / Strength',
    `agility`         INT             NOT NULL DEFAULT 10      COMMENT 'Ловкость / Agility',
    `endurance`       INT             NOT NULL DEFAULT 10      COMMENT 'Выносливость / Endurance',
    `intelligence`    INT             NOT NULL DEFAULT 10      COMMENT 'Интеллект / Intelligence',
    `luck`            INT             NOT NULL DEFAULT 10      COMMENT 'Удача / Luck',
    `stat_points`     INT UNSIGNED    NOT NULL DEFAULT 0       COMMENT 'Нераспределённые очки характеристик / Unspent stat points',

    PRIMARY KEY (`id`),

    UNIQUE KEY `uk_character_stats_character_id` (`character_id`),

    INDEX `idx_character_stats_character_id` (`character_id`),

    CONSTRAINT `fk_character_stats_character_id`
        FOREIGN KEY (`character_id`) REFERENCES `characters` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,

    CONSTRAINT `chk_stats_strength_min`     CHECK (`strength` >= 0),
    CONSTRAINT `chk_stats_agility_min`      CHECK (`agility` >= 0),
    CONSTRAINT `chk_stats_endurance_min`    CHECK (`endurance` >= 0),
    CONSTRAINT `chk_stats_intelligence_min` CHECK (`intelligence` >= 0),
    CONSTRAINT `chk_stats_luck_min`         CHECK (`luck` >= 0),
    CONSTRAINT `chk_stats_points_min`       CHECK (`stat_points` >= 0)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Характеристики персонажей / Character stats (1:1 with characters)';


-- ============================================================================
-- Таблица: races / Расы
-- Справочник рас с базовыми параметрами и бонусами
-- ============================================================================
DROP TABLE IF EXISTS `races`;
CREATE TABLE `races` (
    `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT  COMMENT 'Уникальный идентификатор расы / Unique race ID',
    `name`            VARCHAR(50)     NOT NULL                 COMMENT 'Название расы / Race name',
    `description`     TEXT            NULL                     COMMENT 'Описание расы / Race description',
    `base_strength`   INT             NOT NULL DEFAULT 10      COMMENT 'Базовая сила / Base strength',
    `base_agility`    INT             NOT NULL DEFAULT 10      COMMENT 'Базовая ловкость / Base agility',
    `base_endurance`  INT             NOT NULL DEFAULT 10      COMMENT 'Базовая выносливость / Base endurance',
    `base_intelligence` INT           NOT NULL DEFAULT 10      COMMENT 'Базовый интеллект / Base intelligence',
    `base_luck`       INT             NOT NULL DEFAULT 10      COMMENT 'Базовая удача / Base luck',
    `hp_bonus`        INT             NOT NULL DEFAULT 0       COMMENT 'Бонус к здоровью / HP bonus',
    `mana_bonus`      INT             NOT NULL DEFAULT 0       COMMENT 'Бонус к мане / Mana bonus',
    `icon`            VARCHAR(100)    NULL                     COMMENT 'Иконка расы / Race icon path',
    `bonuses`         JSON            NULL                     COMMENT 'Бонусы расы (JSON): {"resist_fire":5,"bonus_xp":10} / Race-specific bonuses',

    PRIMARY KEY (`id`),

    UNIQUE KEY `uk_races_name` (`name`),

    CONSTRAINT `chk_races_base_strength_min`    CHECK (`base_strength` >= 0),
    CONSTRAINT `chk_races_base_agility_min`     CHECK (`base_agility` >= 0),
    CONSTRAINT `chk_races_base_endurance_min`   CHECK (`base_endurance` >= 0),
    CONSTRAINT `chk_races_base_intelligence_min` CHECK (`base_intelligence` >= 0),
    CONSTRAINT `chk_races_base_luck_min`        CHECK (`base_luck` >= 0)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Справочник рас / Race definitions';


-- ============================================================================
-- Таблица: classes / Классы персонажей
-- Справочник классов с параметрами прокачки и способностями
-- ============================================================================
DROP TABLE IF EXISTS `classes`;
CREATE TABLE `classes` (
    `id`                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Уникальный идентификатор класса / Unique class ID',
    `name`                 VARCHAR(50)     NOT NULL               COMMENT 'Название класса / Class name',
    `description`          TEXT            NULL                   COMMENT 'Описание класса / Class description',
    `hp_per_level`         INT UNSIGNED    NOT NULL DEFAULT 10    COMMENT 'HP за уровень / HP gained per level',
    `mana_per_level`       INT UNSIGNED    NOT NULL DEFAULT 5     COMMENT 'Маны за уровень / Mana gained per level',
    `strength_per_level`   INT             NOT NULL DEFAULT 1     COMMENT 'Силы за уровень / Strength per level',
    `agility_per_level`    INT             NOT NULL DEFAULT 1     COMMENT 'Ловкости за уровень / Agility per level',
    `endurance_per_level`  INT             NOT NULL DEFAULT 1     COMMENT 'Выносливости за уровень / Endurance per level',
    `intelligence_per_level` INT           NOT NULL DEFAULT 1     COMMENT 'Интеллекта за уровень / Intelligence per level',
    `luck_per_level`       INT             NOT NULL DEFAULT 1     COMMENT 'Удачи за уровень / Luck per level',
    `icon`                 VARCHAR(100)    NULL                   COMMENT 'Иконка класса / Class icon path',
    `allowed_weapon_types` JSON            NULL                   COMMENT 'Доступные типы оружия (JSON): ["sword","axe","staff"] / Allowed weapon types',
    `abilities`            JSON            NULL                   COMMENT 'Способности по уровням (JSON): [{"level":1,"name":"Удар",...}] / Level-locked abilities',

    PRIMARY KEY (`id`),

    UNIQUE KEY `uk_classes_name` (`name`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Справочник классов / Class definitions';


-- ============================================================================
-- Таблица: skills / Навыки и умения
-- Навыки для использования в бою (магия, спец.атаки, лечение)
-- ============================================================================
DROP TABLE IF EXISTS `skills`;
CREATE TABLE `skills` (
    `id`              INT UNSIGNED    NOT NULL AUTO_INCREMENT  COMMENT 'ID навыка / Skill ID',
    `name`            VARCHAR(100)    NOT NULL                 COMMENT 'Название навыка / Skill name',
    `description`     TEXT            NULL                     COMMENT 'Описание навыка / Skill description',
    `class_require`   ENUM('warrior','mage','rogue','paladin','archer','all') NOT NULL DEFAULT 'all'
                                                               COMMENT 'Класс, которому доступен навык / Required class',
    `level_require`   INT UNSIGNED    NOT NULL DEFAULT 1       COMMENT 'Мин. уровень персонажа / Minimum character level',
    `mana_cost`       INT UNSIGNED    NOT NULL DEFAULT 0       COMMENT 'Стоимость маны / Mana cost',
    `base_damage`     INT UNSIGNED    NOT NULL DEFAULT 15      COMMENT 'Базовый урон навыка / Base damage',
    `damage_type`     ENUM('physical','magical','heal') NOT NULL DEFAULT 'magical'
                                                               COMMENT 'Тип урона / Damage type',
    `cooldown`        INT UNSIGNED    NOT NULL DEFAULT 0       COMMENT 'Кулдаун в ходах / Cooldown in turns',
    `effect_type`     VARCHAR(50)     NULL                     COMMENT 'Тип доп.эффекта (bleed, poison, stun, shield, heal_buff) / Additional effect type',
    `effect_value`    INT             NULL                     COMMENT 'Сила доп.эффекта / Effect power value',
    `icon`            VARCHAR(100)    NULL                     COMMENT 'Иконка навыка / Skill icon path',

    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_skills_name` (`name`),
    INDEX `idx_skills_class` (`class_require`),
    INDEX `idx_skills_level` (`level_require`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Навыки и умения / Combat skills and abilities';

-- Seed data: начальные навыки / Initial skills
INSERT INTO `skills` (`name`, `description`, `class_require`, `level_require`, `mana_cost`, `base_damage`, `damage_type`, `effect_type`, `effect_value`, `icon`) VALUES
('Огненный шар', 'Выпускает шар пламени, нанося магический урон', 'mage', 1, 10, 20, 'magical', 'burn', 5, 'fireball'),
('Ледяная стрела', 'Запускает ледяную стрелу, замедляя врага', 'mage', 3, 15, 25, 'magical', 'slow', 3, 'ice_arrow'),
('Мощный удар', 'Наносит усиленный физический удар', 'warrior', 1, 5, 25, 'physical', 'stun', 0, 'heavy_strike'),
('Щит ярости', 'Повышает защиту на 2 хода', 'warrior', 2, 10, 0, 'physical', 'shield', 20, 'shield_bash'),
('Удар в спину', 'Атака с повышенным крит.шансом', 'rogue', 1, 8, 30, 'physical', 'bleed', 8, 'backstab'),
('Вензорный выстрел', 'Точный выстрел на расстоянии', 'archer', 1, 7, 22, 'physical', NULL, NULL, 'aimed_shot'),
('Святой свет', 'Восстанавливает HP персонажу', 'paladin', 1, 12, 0, 'heal', 'heal_buff', 30, 'holy_light'),
('Отражение', 'Отражает часть урона обратно', 'all', 5, 15, 10, 'physical', 'reflect', 10, 'reflect');

-- ============================================================================
-- Таблица: items / Предметы
-- Общий справочник всех предметов в игре
-- ============================================================================
DROP TABLE IF EXISTS `items`;
CREATE TABLE `items` (
    `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT  COMMENT 'Уникальный идентификатор предмета / Unique item ID',
    `name`             VARCHAR(100)    NOT NULL                 COMMENT 'Название предмета / Item name',
    `description`      TEXT            NULL                     COMMENT 'Описание предмета / Item description',
    `type`             ENUM('weapon','armor','helmet','shield','accessory','consumable','material','quest') NOT NULL
                                                                COMMENT 'Тип предмета / Item type',
    `rarity`           ENUM('common','uncommon','rare','epic','legendary') NOT NULL DEFAULT 'common'
                                                                COMMENT 'Редкость / Rarity tier',
    `level_requirement` INT UNSIGNED   NOT NULL DEFAULT 1       COMMENT 'Требуемый уровень / Required level',
    `slot`             ENUM('weapon','armor','helmet','shield','accessory','ring','amulet') NULL
                                                                COMMENT 'Слот экипировки / Equipment slot (NULL for non-equippable)',
    `stats`            JSON            NULL                     COMMENT 'Характеристики предмета (JSON): {"strength":5,"agility":3,"hp":20} / Item stat bonuses',
    `price`            INT UNSIGNED    NOT NULL DEFAULT 0       COMMENT 'Цена покупки (золото) / Buy price in gold',
    `sell_price`       INT UNSIGNED    NOT NULL DEFAULT 0       COMMENT 'Цена продажи (золото) / Sell price in gold',
    `stackable`        BOOLEAN         NOT NULL DEFAULT FALSE   COMMENT 'Можно ли складывать / Is item stackable',
    `max_stack`        INT UNSIGNED    NOT NULL DEFAULT 1       COMMENT 'Максимальный размер стека / Maximum stack size',
    `icon`             VARCHAR(100)    NULL                     COMMENT 'Иконка предмета / Item icon path',
    `is_tradeable`     BOOLEAN         NOT NULL DEFAULT TRUE    COMMENT 'Можно ли торговать / Is tradeable',
    `created_at`       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP
                                                                COMMENT 'Дата создания записи / Record creation timestamp',

    PRIMARY KEY (`id`),

    INDEX `idx_items_type`        (`type`),
    INDEX `idx_items_rarity`      (`rarity`),
    INDEX `idx_items_level_req`   (`level_requirement`),
    INDEX `idx_items_slot`        (`slot`),
    INDEX `idx_items_tradeable`   (`is_tradeable`),
    INDEX `idx_items_stackable`   (`stackable`),

    CONSTRAINT `chk_items_sell_price`  CHECK (`sell_price` <= `price`),
    CONSTRAINT `chk_items_max_stack`   CHECK (`max_stack` >= 1)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Предметы (справочник) / Items (catalog)';


-- ============================================================================
-- Таблица: inventory / Инвентарь персонажей
-- Связь персонажей с предметами (экземпляры предметов в инвентаре)
-- ============================================================================
DROP TABLE IF EXISTS `inventory`;
CREATE TABLE `inventory` (
    `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT  COMMENT 'Уникальный идентификатор записи / Unique record ID',
    `character_id`     BIGINT UNSIGNED NOT NULL                 COMMENT 'ID персонажа (characters.id) / Character ID',
    `item_id`          BIGINT UNSIGNED NOT NULL                 COMMENT 'ID предмета (items.id) / Item ID',
    `quantity`         INT UNSIGNED    NOT NULL DEFAULT 1       COMMENT 'Количество предметов / Item quantity',
    `slot_position`    INT             NOT NULL DEFAULT 0       COMMENT 'Позиция в инвентаре / Slot position for sorting',
    `is_equipped`      BOOLEAN         NOT NULL DEFAULT FALSE   COMMENT 'Экипирован ли предмет / Is item equipped',
    `equipped_slot`    ENUM('weapon','armor','helmet','shield','accessory','ring','amulet') NULL
                                                                COMMENT 'Слот экипировки (если экипирован) / Equipped slot',
    `obtained_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP
                                                                COMMENT 'Дата получения предмета / When item was obtained',

    PRIMARY KEY (`id`),

    INDEX `idx_inventory_character_id`  (`character_id`),
    INDEX `idx_inventory_item_id`       (`item_id`),
    INDEX `idx_inventory_equipped`      (`is_equipped`),
    INDEX `idx_inventory_slot_position` (`slot_position`),

    UNIQUE KEY `uk_inventory_char_slot` (`character_id`, `slot_position`),

    CONSTRAINT `fk_inventory_character_id`
        FOREIGN KEY (`character_id`) REFERENCES `characters` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,

    CONSTRAINT `fk_inventory_item_id`
        FOREIGN KEY (`item_id`) REFERENCES `items` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,

    CONSTRAINT `chk_inventory_quantity` CHECK (`quantity` >= 1)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Инвентарь персонажей / Character inventory';


-- ============================================================================
-- Таблица: equipment_slots / Слоты экипировки
-- Предопределённые слоты экипировки для каждого персонажа
-- ============================================================================
DROP TABLE IF EXISTS `equipment_slots`;
CREATE TABLE `equipment_slots` (
    `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT  COMMENT 'Уникальный идентификатор / Unique ID',
    `character_id`    BIGINT UNSIGNED NOT NULL                 COMMENT 'ID персонажа (characters.id) / Character ID',
    `slot_type`       ENUM('weapon','armor','helmet','shield','accessory','ring1','ring2','amulet') NOT NULL
                                                                COMMENT 'Тип слота экипировки / Equipment slot type',
    `item_id`         BIGINT UNSIGNED NULL                     COMMENT 'ID предмета из инвентаря (inventory.id) / Equipped inventory item ID',

    PRIMARY KEY (`id`),

    UNIQUE KEY `uk_equipment_char_slot` (`character_id`, `slot_type`),

    INDEX `idx_equipment_character_id` (`character_id`),
    INDEX `idx_equipment_slot_type`    (`slot_type`),
    INDEX `idx_equipment_item_id`      (`item_id`),

    CONSTRAINT `fk_equipment_character_id`
        FOREIGN KEY (`character_id`) REFERENCES `characters` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,

    CONSTRAINT `fk_equipment_item_id`
        FOREIGN KEY (`item_id`) REFERENCES `inventory` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Слоты экипировки персонажей / Character equipment slots';


-- ============================================================================
-- Таблица: locations / Локации
-- Игровые локации (зоны, города, подземелья)
-- ============================================================================
DROP TABLE IF EXISTS `locations`;
CREATE TABLE `locations` (
    `id`                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Уникальный идентификатор локации / Unique location ID',
    `name`                VARCHAR(100)    NOT NULL               COMMENT 'Название локации / Location name',
    `description`         TEXT            NULL                   COMMENT 'Описание локации / Location description',
    `parent_location_id`  BIGINT UNSIGNED NULL                   COMMENT 'Родительская локация (для вложенных зон) / Parent location ID (nested zones)',
    `level_requirement`   INT UNSIGNED    NOT NULL DEFAULT 1     COMMENT 'Требуемый уровень для посещения / Required level to enter',
    `is_safe`             BOOLEAN         NOT NULL DEFAULT FALSE COMMENT 'Безопасная зона (без PvP) / Safe zone (no PvP)',
    `has_monsters`        BOOLEAN         NOT NULL DEFAULT FALSE COMMENT 'Есть ли монстры / Has monsters to fight',
    `has_shop`            BOOLEAN         NOT NULL DEFAULT FALSE COMMENT 'Есть ли магазин / Has a shop',
    `has_resource`        BOOLEAN         NOT NULL DEFAULT FALSE COMMENT 'Есть ли ресурсы для добычи / Has gatherable resources',
    `resource_type`       ENUM('fish','wood','ore','herb') NULL  COMMENT 'Тип ресурса / Resource type for gathering',
    `position_x`          INT             NULL                   COMMENT 'Координата X на карте / Map grid X coordinate',
    `position_y`          INT             NULL                   COMMENT 'Координата Y на карте / Map grid Y coordinate',
    `connections`         JSON            NULL                   COMMENT 'Связанные локации (JSON): [1,2,5] / Connected location IDs',
    `icon`                VARCHAR(100)    NULL                   COMMENT 'Иконка локации / Location icon path',
    `created_at`          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP
                                                                COMMENT 'Дата создания / Creation timestamp',

    PRIMARY KEY (`id`),

    INDEX `idx_locations_parent`      (`parent_location_id`),
    INDEX `idx_locations_level_req`   (`level_requirement`),
    INDEX `idx_locations_safe`        (`is_safe`),
    INDEX `idx_locations_monsters`    (`has_monsters`),
    INDEX `idx_locations_position`    (`position_x`, `position_y`),

    CONSTRAINT `fk_locations_parent_id`
        FOREIGN KEY (`parent_location_id`) REFERENCES `locations` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Локации / Game locations (zones, cities, dungeons)';


-- ============================================================================
-- Таблица: monsters / Монстры
-- Справочник монстров с параметрами боя и таблицей лута
-- ============================================================================
DROP TABLE IF EXISTS `monsters`;
CREATE TABLE `monsters` (
    `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT  COMMENT 'Уникальный идентификатор монстра / Unique monster ID',
    `name`              VARCHAR(100)    NOT NULL                 COMMENT 'Название монстра / Monster name',
    `level`             INT UNSIGNED    NOT NULL                 COMMENT 'Уровень монстра / Monster level',
    `hp`                INT UNSIGNED    NOT NULL                 COMMENT 'Здоровье монстра / Monster HP',
    `mana`              INT UNSIGNED    NOT NULL DEFAULT 0       COMMENT 'Мана монстра / Monster mana',
    `attack`            INT UNSIGNED    NOT NULL                 COMMENT 'Атака / Attack power',
    `defense`           INT UNSIGNED    NOT NULL                 COMMENT 'Защита / Defense',
    `agility`           INT UNSIGNED    NOT NULL DEFAULT 5       COMMENT 'Ловкость / Agility (affects dodge/initiative)',
    `experience_reward` INT UNSIGNED    NOT NULL DEFAULT 0       COMMENT 'Опыт за убийство / Experience reward',
    `gold_reward`       INT UNSIGNED    NOT NULL DEFAULT 0       COMMENT 'Золото за убийство / Gold reward',
    `loot_table`        JSON            NULL                     COMMENT 'Таблица лута (JSON): [{"item_id":1,"chance":0.1},{"item_id":5,"chance":0.5}] / Loot drop table',
    `location_id`       BIGINT UNSIGNED NULL                     COMMENT 'Локация обитания / Spawn location ID',
    `is_boss`           BOOLEAN         NOT NULL DEFAULT FALSE   COMMENT 'Является ли боссом / Is boss monster',
    `spawn_rate`        INT UNSIGNED    NOT NULL DEFAULT 100     COMMENT 'Шанс спавна (0-100%) / Spawn rate percentage',
    `special_abilities` JSON            NULL                     COMMENT 'Особые способности (JSON): [{"name":"Яд","damage":10,"chance":0.3}] / Special abilities',
    `icon`              VARCHAR(100)    NULL                     COMMENT 'Иконка монстра / Monster icon path',

    PRIMARY KEY (`id`),

    INDEX `idx_monsters_level`       (`level`),
    INDEX `idx_monsters_location_id` (`location_id`),
    INDEX `idx_monsters_boss`        (`is_boss`),

    CONSTRAINT `fk_monsters_location_id`
        FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,

    CONSTRAINT `chk_monsters_spawn_rate` CHECK (`spawn_rate` BETWEEN 0 AND 100)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Монстры / Monsters';


-- ============================================================================
-- Таблица: npcs / NPC (неигровые персонажи)
-- Торговцы, квестодатели, кузнецы, тренеры, целители
-- ============================================================================
DROP TABLE IF EXISTS `npcs`;
CREATE TABLE `npcs` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT  COMMENT 'Уникальный идентификатор NPC / Unique NPC ID',
    `name`          VARCHAR(100)    NOT NULL                 COMMENT 'Имя NPC / NPC name',
    `type`          ENUM('merchant','quest_giver','blacksmith','trainer','healer') NOT NULL
                                                                COMMENT 'Тип NPC / NPC type',
    `location_id`   BIGINT UNSIGNED NOT NULL                 COMMENT 'Локация NPC / Location ID',
    `dialogue`      JSON            NULL                     COMMENT 'Диалоги (JSON): [{"id":1,"text":"Привет!","next":2}] / Multi-step dialogue tree',
    `shop_items`    JSON            NULL                     COMMENT 'Товары торговца (JSON): [{"item_id":1,"price":100}] / Shop item list (merchants)',
    `quest_ids`     JSON            NULL                     COMMENT 'ID квестов (JSON): [1,2,3] / Quest IDs (quest givers)',
    `icon`          VARCHAR(100)    NULL                     COMMENT 'Иконка NPC / NPC icon path',

    PRIMARY KEY (`id`),

    INDEX `idx_npcs_type`        (`type`),
    INDEX `idx_npcs_location_id` (`location_id`),

    CONSTRAINT `fk_npcs_location_id`
        FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='NPC / Non-player characters';


-- ============================================================================
-- Таблица: battles / Бои
-- Записи о PvE и PvP боях
-- ============================================================================
DROP TABLE IF EXISTS `battles`;
CREATE TABLE `battles` (
    `id`                       BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Уникальный идентификатор боя / Unique battle ID',
    `type`                     ENUM('pve','pvp') NOT NULL               COMMENT 'Тип боя / Battle type',
    `status`                   ENUM('waiting','active','finished','interrupted') NOT NULL DEFAULT 'waiting'
                                                                COMMENT 'Статус боя / Battle status',
    `turn`                     INT UNSIGNED    NOT NULL DEFAULT 0     COMMENT 'Текущий ход / Current turn number',
    `max_turns`                INT UNSIGNED    NOT NULL DEFAULT 30    COMMENT 'Максимальное количество ходов / Maximum turns',
    `current_turn_character_id` BIGINT UNSIGNED NULL                  COMMENT 'Чей сейчас ход / Whose turn it is',
    `started_at`               DATETIME        NULL                   COMMENT 'Дата начала боя / Battle start timestamp',
    `finished_at`              DATETIME        NULL                   COMMENT 'Дата окончания боя / Battle end timestamp',
    `created_at`               DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP
                                                                COMMENT 'Дата создания записи / Record creation timestamp',

    PRIMARY KEY (`id`),

    INDEX `idx_battles_type`            (`type`),
    INDEX `idx_battles_status`          (`status`),
    INDEX `idx_battles_current_turn`    (`current_turn_character_id`),
    INDEX `idx_battles_started_at`      (`started_at`),

    CONSTRAINT `fk_battles_current_turn`
        FOREIGN KEY (`current_turn_character_id`) REFERENCES `characters` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,

    CONSTRAINT `chk_battles_turn`       CHECK (`turn` <= `max_turns`),
    CONSTRAINT `chk_battles_max_turns`  CHECK (`max_turns` >= 1)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Бои / Battles (PvE and PvP)';


-- ============================================================================
-- Таблица: battle_participants / Участники боя
-- Персонажи и монстры, участвующие в бою
-- ============================================================================
DROP TABLE IF EXISTS `battle_participants`;
CREATE TABLE `battle_participants` (
    `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT  COMMENT 'Уникальный идентификатор / Unique ID',
    `battle_id`       BIGINT UNSIGNED NOT NULL                 COMMENT 'ID боя / Battle ID',
    `character_id`    BIGINT UNSIGNED NULL                     COMMENT 'ID персонажа-участника / Character participant ID (NULL for monsters)',
    `monster_id`      BIGINT UNSIGNED NULL                     COMMENT 'ID монстра-участника / Monster participant ID (NULL for characters)',
    `team`            ENUM('left','right') NOT NULL            COMMENT 'Команда (левая/правая) / Team side',
    `current_hp`      INT             NOT NULL                 COMMENT 'Текущее здоровье в бою / Current HP in battle',
    `max_hp`          INT             NOT NULL                 COMMENT 'Максимальное здоровье / Max HP',
    `current_mana`    INT             NOT NULL DEFAULT 0       COMMENT 'Текущая мана в бою / Current mana in battle',
    `max_mana`        INT             NOT NULL DEFAULT 0       COMMENT 'Максимальная мана / Max mana',
    `is_alive`        BOOLEAN         NOT NULL DEFAULT TRUE    COMMENT 'Жив ли участник / Is participant alive',
    `initiative`      INT             NOT NULL DEFAULT 0       COMMENT 'Инициатива (определяет очерёдность) / Initiative (turn order)',
    `position`        INT UNSIGNED    NOT NULL DEFAULT 0       COMMENT 'Позиция в команде / Position within team',
    `is_ai`           BOOLEAN         NOT NULL DEFAULT FALSE   COMMENT 'Управляется ИИ / Is AI-controlled',

    PRIMARY KEY (`id`),

    INDEX `idx_bp_battle_id`       (`battle_id`),
    INDEX `idx_bp_character_id`    (`character_id`),
    INDEX `idx_bp_monster_id`      (`monster_id`),
    INDEX `idx_bp_team`            (`team`),
    INDEX `idx_bp_alive`           (`is_alive`),
    INDEX `idx_bp_initiative`      (`initiative`),

    CONSTRAINT `fk_bp_battle_id`
        FOREIGN KEY (`battle_id`) REFERENCES `battles` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,

    CONSTRAINT `fk_bp_character_id`
        FOREIGN KEY (`character_id`) REFERENCES `characters` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,

    CONSTRAINT `fk_bp_monster_id`
        FOREIGN KEY (`monster_id`) REFERENCES `monsters` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,

    CONSTRAINT `chk_bp_hp_range`    CHECK (`current_hp` >= 0 AND `current_hp` <= `max_hp`),
    CONSTRAINT `chk_bp_mana_range`  CHECK (`current_mana` >= 0 AND `current_mana` <= `max_mana`),
    CONSTRAINT `chk_bp_either_char_or_monster`
        CHECK (
            (`character_id` IS NOT NULL AND `monster_id` IS NULL)
            OR
            (`character_id` IS NULL AND `monster_id` IS NOT NULL)
        )

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Участники боя / Battle participants (characters and monsters)';


-- ============================================================================
-- Таблица: battle_logs / Лог боя
-- Подробная запись каждого действия в бою
-- ============================================================================
DROP TABLE IF EXISTS `battle_logs`;
CREATE TABLE `battle_logs` (
    `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT  COMMENT 'Уникальный идентификатор / Unique ID',
    `battle_id`       BIGINT UNSIGNED NOT NULL                 COMMENT 'ID боя / Battle ID',
    `turn`            INT UNSIGNED    NOT NULL                 COMMENT 'Номер хода / Turn number',
    `actor_type`      ENUM('character','monster','system') NOT NULL
                                                                COMMENT 'Тип действующего лица / Actor type',
    `actor_id`        BIGINT UNSIGNED NOT NULL                 COMMENT 'ID действующего лица / Actor ID (character or monster)',
    `action`          ENUM('attack','defend','skill','item','flee','heal') NOT NULL
                                                                COMMENT 'Тип действия / Action type',
    `target_type`     ENUM('character','monster','self') NOT NULL
                                                                COMMENT 'Тип цели / Target type',
    `target_id`       BIGINT UNSIGNED NULL                     COMMENT 'ID цели / Target ID',
    `damage`          INT             NULL                     COMMENT 'Нанесённый урон (NULL если нет) / Damage dealt',
    `is_critical`     BOOLEAN         NOT NULL DEFAULT FALSE   COMMENT 'Критический удар / Is critical hit',
    `is_dodge`        BOOLEAN         NOT NULL DEFAULT FALSE   COMMENT 'Уклонение / Is dodge',
    `description`     TEXT            NULL                     COMMENT 'Текстовое описание действия / Text description of action',
    `combat_data`     JSON            NULL                     COMMENT 'Дополнительные данные (JSON): эффекты, debuff-ы и т.д. / Extra combat data',
    `created_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP
                                                                COMMENT 'Дата записи / Log timestamp',

    PRIMARY KEY (`id`),

    INDEX `idx_bl_battle_id`   (`battle_id`),
    INDEX `idx_bl_turn`        (`turn`),
    INDEX `idx_bl_actor_type`  (`actor_type`),
    INDEX `idx_bl_action`      (`action`),
    INDEX `idx_bl_created_at`  (`created_at`),
    INDEX `idx_bl_battle_turn` (`battle_id`, `turn`),

    CONSTRAINT `fk_bl_battle_id`
        FOREIGN KEY (`battle_id`) REFERENCES `battles` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,

    CONSTRAINT `chk_bl_turn_positive` CHECK (`turn` >= 1)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Лог боя / Battle action logs';


-- ============================================================================
-- Таблица: chat_messages / Сообщения чата
-- Все сообщения игрового чата по каналам
-- ============================================================================
DROP TABLE IF EXISTS `chat_messages`;
CREATE TABLE `chat_messages` (
    `id`                    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Уникальный идентификатор / Unique ID',
    `character_id`          BIGINT UNSIGNED NOT NULL               COMMENT 'ID персонажа-отправителя / Sender character ID',
    `channel`               ENUM('global','local','guild','private','trade','battle') NOT NULL DEFAULT 'global'
                                                                COMMENT 'Канал чата / Chat channel',
    `target_character_id`   BIGINT UNSIGNED NULL                  COMMENT 'ID получателя (для личных сообщений) / Target character ID (private messages)',
    `message`               TEXT            NOT NULL               COMMENT 'Текст сообщения / Message text',
    `created_at`            DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP
                                                                COMMENT 'Дата отправки / Message timestamp',

    PRIMARY KEY (`id`),

    INDEX `idx_chat_channel`        (`channel`),
    INDEX `idx_chat_created_at`     (`created_at`),
    INDEX `idx_chat_character_id`   (`character_id`),
    INDEX `idx_chat_target_id`      (`target_character_id`),
    INDEX `idx_chat_channel_time`   (`channel`, `created_at`),

    CONSTRAINT `fk_chat_character_id`
        FOREIGN KEY (`character_id`) REFERENCES `characters` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,

    CONSTRAINT `fk_chat_target_character_id`
        FOREIGN KEY (`target_character_id`) REFERENCES `characters` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Сообщения чата / Chat messages';


-- ============================================================================
-- Таблица: guilds / Гильдии
-- Игровые гильдии (кланы)
-- ============================================================================
DROP TABLE IF EXISTS `guilds`;
CREATE TABLE `guilds` (
    `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT  COMMENT 'Уникальный идентификатор гильдии / Unique guild ID',
    `name`            VARCHAR(100)    NOT NULL                 COMMENT 'Название гильдии / Guild name',
    `leader_id`       BIGINT UNSIGNED NOT NULL                 COMMENT 'ID лидера (characters.id) / Leader character ID',
    `description`     TEXT            NULL                     COMMENT 'Описание гильдии / Guild description',
    `level`           INT UNSIGNED    NOT NULL DEFAULT 1       COMMENT 'Уровень гильдии / Guild level',
    `experience`      BIGINT          NOT NULL DEFAULT 0       COMMENT 'Опыт гильдии / Guild experience',
    `max_members`     INT UNSIGNED    NOT NULL DEFAULT 20      COMMENT 'Максимальное количество членов / Maximum member count',
    `created_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP
                                                                COMMENT 'Дата создания / Guild creation timestamp',

    PRIMARY KEY (`id`),

    UNIQUE KEY `uk_guilds_name` (`name`),

    INDEX `idx_guilds_leader_id` (`leader_id`),
    INDEX `idx_guilds_level`    (`level`),

    CONSTRAINT `fk_guilds_leader_id`
        FOREIGN KEY (`leader_id`) REFERENCES `characters` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,

    CONSTRAINT `chk_guilds_max_members` CHECK (`max_members` >= 1)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Гильдии / Guilds (clans)';


-- ============================================================================
-- Таблица: guild_members / Члены гильдий
-- Связь персонажей с гильдиями и их ранги
-- ============================================================================
DROP TABLE IF EXISTS `guild_members`;
CREATE TABLE `guild_members` (
    `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT  COMMENT 'Уникальный идентификатор / Unique ID',
    `guild_id`        BIGINT UNSIGNED NOT NULL                 COMMENT 'ID гильдии / Guild ID',
    `character_id`    BIGINT UNSIGNED NOT NULL                 COMMENT 'ID персонажа / Character ID',
    `rank`            ENUM('leader','officer','member') NOT NULL DEFAULT 'member'
                                                                COMMENT 'Ранг в гильдии / Guild rank',
    `joined_at`       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP
                                                                COMMENT 'Дата вступления / Join timestamp',

    PRIMARY KEY (`id`),

    UNIQUE KEY `uk_guild_members_guild_char` (`guild_id`, `character_id`),

    INDEX `idx_gm_guild_id`     (`guild_id`),
    INDEX `idx_gm_character_id` (`character_id`),
    INDEX `idx_gm_rank`         (`rank`),

    CONSTRAINT `fk_gm_guild_id`
        FOREIGN KEY (`guild_id`) REFERENCES `guilds` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,

    CONSTRAINT `fk_gm_character_id`
        FOREIGN KEY (`character_id`) REFERENCES `characters` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Члены гильдий / Guild members';


-- ============================================================================
-- Таблица: auctions / Аукцион
-- Лоты аукциона (продажа предметов между игроками)
-- ============================================================================
DROP TABLE IF EXISTS `auctions`;
CREATE TABLE `auctions` (
    `id`                            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Уникальный идентификатор лота / Unique auction ID',
    `seller_character_id`           BIGINT UNSIGNED NOT NULL               COMMENT 'ID продавца / Seller character ID',
    `item_id`                       BIGINT UNSIGNED NOT NULL               COMMENT 'ID предмета (items.id) / Item ID',
    `quantity`                      INT UNSIGNED    NOT NULL DEFAULT 1     COMMENT 'Количество предметов / Item quantity',
    `starting_price`                INT UNSIGNED    NOT NULL               COMMENT 'Стартовая цена / Starting price',
    `current_price`                 INT UNSIGNED    NOT NULL               COMMENT 'Текущая цена (последняя ставка) / Current bid price',
    `buyout_price`                  INT UNSIGNED    NULL                   COMMENT 'Цена выкупа (прямая покупка) / Buyout price (instant buy)',
    `highest_bidder_character_id`   BIGINT UNSIGNED NULL                   COMMENT 'ID последнего ставившего / Highest bidder character ID',
    `ends_at`                       DATETIME        NOT NULL               COMMENT 'Дата окончания аукциона / Auction end time',
    `status`                        ENUM('active','sold','expired','cancelled') NOT NULL DEFAULT 'active'
                                                                COMMENT 'Статус аукциона / Auction status',
    `created_at`                    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP
                                                                COMMENT 'Дата создания лота / Auction creation timestamp',

    PRIMARY KEY (`id`),

    INDEX `idx_auctions_seller`       (`seller_character_id`),
    INDEX `idx_auctions_item`         (`item_id`),
    INDEX `idx_auctions_status`       (`status`),
    INDEX `idx_auctions_ends_at`      (`ends_at`),
    INDEX `idx_auctions_bidder`       (`highest_bidder_character_id`),
    INDEX `idx_auctions_active_end`   (`status`, `ends_at`),

    CONSTRAINT `fk_auctions_seller`
        FOREIGN KEY (`seller_character_id`) REFERENCES `characters` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,

    CONSTRAINT `fk_auctions_item`
        FOREIGN KEY (`item_id`) REFERENCES `items` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,

    CONSTRAINT `fk_auctions_bidder`
        FOREIGN KEY (`highest_bidder_character_id`) REFERENCES `characters` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,

    CONSTRAINT `chk_auctions_prices`
        CHECK (`current_price` >= `starting_price`),
    CONSTRAINT `chk_auctions_buyout`
        CHECK (`buyout_price` IS NULL OR `buyout_price` >= `starting_price`),
    CONSTRAINT `chk_auctions_quantity`
        CHECK (`quantity` >= 1)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Аукцион / Auction house';


-- ============================================================================
-- Таблица: friends / Друзья
-- Список друзей и блокировок между персонажами
-- ============================================================================
DROP TABLE IF EXISTS `friends`;
CREATE TABLE `friends` (
    `id`                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Уникальный идентификатор / Unique ID',
    `character_id`        BIGINT UNSIGNED NOT NULL               COMMENT 'ID персонажа-инициатора / Initiator character ID',
    `friend_character_id` BIGINT UNSIGNED NOT NULL               COMMENT 'ID персонажа-друга / Friend character ID',
    `status`              ENUM('pending','accepted','blocked') NOT NULL DEFAULT 'pending'
                                                                COMMENT 'Статус дружбы / Friendship status',
    `created_at`          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP
                                                                COMMENT 'Дата создания записи / Record creation timestamp',

    PRIMARY KEY (`id`),

    UNIQUE KEY `uk_friends_pair` (`character_id`, `friend_character_id`),

    INDEX `idx_friends_character_id`        (`character_id`),
    INDEX `idx_friends_friend_character_id` (`friend_character_id`),
    INDEX `idx_friends_status`              (`status`),

    CONSTRAINT `fk_friends_character_id`
        FOREIGN KEY (`character_id`) REFERENCES `characters` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,

    CONSTRAINT `fk_friends_friend_character_id`
        FOREIGN KEY (`friend_character_id`) REFERENCES `characters` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,

    CONSTRAINT `chk_friends_not_self`
        CHECK (`character_id` != `friend_character_id`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Друзья / Friends list and blocks';


-- ============================================================================
-- Таблица: quests / Квесты
-- Справочник квестов (основные, побочные, ежедневные)
-- ============================================================================
DROP TABLE IF EXISTS `quests`;
CREATE TABLE `quests` (
    `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT  COMMENT 'Уникальный идентификатор квеста / Unique quest ID',
    `name`             VARCHAR(100)    NOT NULL                 COMMENT 'Название квеста / Quest name',
    `description`      TEXT            NULL                     COMMENT 'Описание квеста / Quest description',
    `type`             ENUM('main','side','daily','repeatable') NOT NULL DEFAULT 'side'
                                                                COMMENT 'Тип квеста / Quest type',
    `level_requirement` INT UNSIGNED   NOT NULL DEFAULT 1       COMMENT 'Требуемый уровень / Required level',
    `objectives`       JSON            NULL                     COMMENT 'Цели квеста (JSON): [{"type":"kill","target_id":5,"required":10,"current":0}] / Quest objectives',
    `rewards`          JSON            NULL                     COMMENT 'Награды (JSON): {"exp":500,"gold":100,"items":[{"item_id":1,"quantity":2}]} / Quest rewards',
    `next_quest_id`    BIGINT UNSIGNED NULL                     COMMENT 'ID следующего квеста (цепочка) / Next quest in chain',
    `created_at`       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP
                                                                COMMENT 'Дата создания / Creation timestamp',

    PRIMARY KEY (`id`),

    INDEX `idx_quests_type`           (`type`),
    INDEX `idx_quests_level_req`      (`level_requirement`),
    INDEX `idx_quests_next_quest`     (`next_quest_id`),

    CONSTRAINT `fk_quests_next_quest`
        FOREIGN KEY (`next_quest_id`) REFERENCES `quests` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Квесты / Quests (catalog)';


-- ============================================================================
-- Таблица: character_quests / Квесты персонажей
-- Прогресс выполнения квестов персонажами
-- ============================================================================
DROP TABLE IF EXISTS `character_quests`;
CREATE TABLE `character_quests` (
    `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT  COMMENT 'Уникальный идентификатор / Unique ID',
    `character_id`    BIGINT UNSIGNED NOT NULL                 COMMENT 'ID персонажа / Character ID',
    `quest_id`        BIGINT UNSIGNED NOT NULL                 COMMENT 'ID квеста / Quest ID',
    `status`          ENUM('active','completed','failed') NOT NULL DEFAULT 'active'
                                                                COMMENT 'Статус выполнения / Quest status',
    `progress`        JSON            NULL                     COMMENT 'Прогресс по целям (JSON): {"kills":7,"items_collected":2} / Quest progress data',
    `started_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP
                                                                COMMENT 'Дата начала / Quest start timestamp',
    `completed_at`    DATETIME        NULL                     COMMENT 'Дата завершения / Quest completion timestamp',

    PRIMARY KEY (`id`),

    UNIQUE KEY `uk_character_quests_char_quest` (`character_id`, `quest_id`),

    INDEX `idx_cq_character_id` (`character_id`),
    INDEX `idx_cq_quest_id`     (`quest_id`),
    INDEX `idx_cq_status`       (`status`),

    CONSTRAINT `fk_cq_character_id`
        FOREIGN KEY (`character_id`) REFERENCES `characters` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,

    CONSTRAINT `fk_cq_quest_id`
        FOREIGN KEY (`quest_id`) REFERENCES `quests` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Квесты персонажей / Character quest progress';


-- ============================================================================
-- Таблица: character_achievements / Достижения персонажей
-- Разблокированные достижения
-- ============================================================================
DROP TABLE IF EXISTS `character_achievements`;
CREATE TABLE `character_achievements` (
    `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT  COMMENT 'Уникальный идентификатор / Unique ID',
    `character_id`    BIGINT UNSIGNED NOT NULL                 COMMENT 'ID персонажа / Character ID',
    `achievement_id`  BIGINT UNSIGNED NOT NULL                 COMMENT 'ID достижения / Achievement ID (references a future achievements table)',
    `unlocked_at`     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP
                                                                COMMENT 'Дата разблокировки / Achievement unlock timestamp',

    PRIMARY KEY (`id`),

    UNIQUE KEY `uk_char_ach_char_ach` (`character_id`, `achievement_id`),

    INDEX `idx_ca_character_id`   (`character_id`),
    INDEX `idx_ca_achievement_id` (`achievement_id`),
    INDEX `idx_ca_unlocked_at`    (`unlocked_at`),

    CONSTRAINT `fk_ca_character_id`
        FOREIGN KEY (`character_id`) REFERENCES `characters` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Достижения персонажей / Character achievements';


-- ============================================================================
-- Таблица: sessions / Сессии пользователей
-- Активные сессии авторизации
-- ============================================================================
DROP TABLE IF EXISTS `sessions`;
CREATE TABLE `sessions` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT  COMMENT 'Уникальный идентификатор сессии / Unique session ID',
    `user_id`       BIGINT UNSIGNED NOT NULL                 COMMENT 'ID пользователя / User ID',
    `session_token` VARCHAR(128)    NOT NULL                 COMMENT 'Токен сессии / Session token (JWT/opaque)',
    `ip_address`    VARCHAR(45)     NULL                     COMMENT 'IP-адрес / IP address (IPv4/IPv6)',
    `user_agent`    TEXT            NULL                     COMMENT 'User-Agent браузера / Browser user agent',
    `expires_at`    DATETIME        NOT NULL                 COMMENT 'Дата истечения сессии / Session expiration time',
    `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP
                                                                COMMENT 'Дата создания сессии / Session creation timestamp',

    PRIMARY KEY (`id`),

    UNIQUE KEY `uk_sessions_token` (`session_token`),

    INDEX `idx_sessions_user_id`   (`user_id`),
    INDEX `idx_sessions_expires`   (`expires_at`),

    CONSTRAINT `fk_sessions_user_id`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Сессии пользователей / User sessions';


-- ============================================================================
-- Таблица: audit_logs / Аудит-лог
-- Лог действий для отслеживания и безопасности
-- ============================================================================
DROP TABLE IF EXISTS `audit_logs`;
CREATE TABLE `audit_logs` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT  COMMENT 'Уникальный идентификатор / Unique ID',
    `user_id`     BIGINT UNSIGNED NULL                     COMMENT 'ID пользователя (NULL для системных событий) / User ID (NULL for system events)',
    `action`      VARCHAR(100)    NOT NULL                 COMMENT 'Тип действия (например: login, purchase, trade) / Action type',
    `details`     JSON            NULL                     COMMENT 'Подробности (JSON): {"item":"Sword","price":100} / Action details',
    `ip_address`  VARCHAR(45)     NULL                     COMMENT 'IP-адрес / IP address',
    `created_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP
                                                                COMMENT 'Дата записи / Log timestamp',

    PRIMARY KEY (`id`),

    INDEX `idx_al_user_id`    (`user_id`),
    INDEX `idx_al_action`     (`action`),
    INDEX `idx_al_created_at` (`created_at`),
    INDEX `idx_al_user_action` (`user_id`, `action`),
    INDEX `idx_al_action_time` (`action`, `created_at`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Аудит-лог / Audit log for security and tracking';


-- ============================================================================
-- Восстановление проверок внешних ключей / Re-enable foreign key checks
-- ============================================================================
SET FOREIGN_KEY_CHECKS = 1;


-- ============================================================================
-- ============================================================================
--  INITIAL DATA / НАЧАЛЬНЫЕ ДАННЫЕ
--  Базовые расы, классы и стартовая локация
-- ============================================================================
-- ============================================================================

-- ----------------------------------------------------------------------------
-- Расы / Races
-- ----------------------------------------------------------------------------
INSERT INTO `races` (`name`, `description`, `base_strength`, `base_agility`, `base_endurance`, `base_intelligence`, `base_luck`, `hp_bonus`, `mana_bonus`, `icon`, `bonuses`) VALUES
('human', 'Люди — универсальная раса, приспособленная к любым условиям жизни. / Humans are a versatile race adaptable to any conditions.', 10, 10, 10, 10, 10, 0, 0, '/icons/races/human.png', '{"bonus_xp": 5, "description": "+5% к опыту"}'),
('elf', 'Эльфы — древняя раса, тесно связанная с магией и природой. / Elves are an ancient race closely tied to magic and nature.', 7, 14, 7, 14, 8, -10, 30, '/icons/races/elf.png', '{"resist_magic": 10, "description": "+10% сопротивление магии"}'),
('dwarf', 'Гномы — крепкие и выносливые, искусные мастера и рудокопы. / Dwarves are sturdy and resilient, skilled craftsmen and miners.', 14, 6, 16, 7, 7, 20, -10, '/icons/races/dwarf.png', '{"resist_physical": 10, "bonus_mining": 15, "description": "+10% сопротивление физ. урону, +15% к добыче руды"}'),
('orc', 'Орки — могучие воины, известные своей brute force и стойкостью. / Orcs are mighty warriors known for their brute force and resilience.', 16, 8, 14, 5, 7, 20, -20, '/icons/races/orc.png', '{"bonus_attack": 5, "description": "+5% к урону в ближнем бою"}'),
('undead', 'Нежить — загадочные существа, существующие между жизнью и смертью. / Undead are mysterious beings existing between life and death.', 8, 10, 12, 13, 7, 10, 10, '/icons/races/undead.png', '{"lifesteal": 3, "resist_poison": 25, "description": "+3% вампиризм, +25% сопротивление яду"}');

-- ----------------------------------------------------------------------------
-- Классы / Classes
-- ----------------------------------------------------------------------------
INSERT INTO `classes` (`name`, `description`, `hp_per_level`, `mana_per_level`, `strength_per_level`, `agility_per_level`, `endurance_per_level`, `intelligence_per_level`, `luck_per_level`, `icon`, `allowed_weapon_types`, `abilities`) VALUES
('warrior', 'Воин — мастер ближнего боя, облачённый в тяжёлую броню. / Warrior is a melee combat master clad in heavy armor.', 15, 2, 3, 1, 3, 0, 0, '/icons/classes/warrior.png', '["sword","axe","mace","shield"]', '[{"level":1,"name":"Удар мечом","damage":10,"type":"physical"},{"level":5,"name":"Мощный удар","damage":25,"type":"physical","cooldown":2},{"level":10,"name":"Боевой клич","buff":{"attack":10},"duration":3}]'),
('mage', 'Маг — повелитель тайных искусств, разрушающий врагов заклинаниями. / Mage is a master of arcane arts, destroying enemies with spells.', 8, 12, 0, 1, 1, 4, 0, '/icons/classes/mage.png', '["staff","wand"]', '[{"level":1,"name":"Огненный шар","damage":15,"mana_cost":10,"type":"fire"},{"level":5,"name":"Ледяная стрела","damage":20,"mana_cost":15,"type":"ice","slow":0.3},{"level":10,"name":"Метеоритный дождь","damage":50,"mana_cost":40,"type":"fire","aoe":true}]'),
('rogue', 'Разбойник — ловкий и скрытный убийца, предпочитающий молниеносные атаки. / Rogue is an agile and stealthy assassin favouring lightning strikes.', 10, 5, 1, 4, 1, 0, 2, '/icons/classes/rogue.png', '["dagger","short_sword","bow"]', '[{"level":1,"name":"Удар в спину","damage":12,"type":"physical","bonus":1.5},{"level":5,"name":"Отравленный клинок","damage":8,"type":"poison","dot":{"damage":5,"turns":3}},{"level":10,"name":"Теневой шаг","type":"utility","effect":"teleport"}]'),
('paladin', 'Паладин — святой воин, сочетающий боевую мощь и целительство. / Paladin is a holy warrior combining combat power and healing.', 13, 6, 2, 1, 2, 2, 0, '/icons/classes/paladin.png', '["sword","mace","shield"]', '[{"level":1,"name":"Священный удар","damage":12,"type":"holy"},{"level":5,"name":"Исцеление","heal":30,"mana_cost":15,"type":"holy"},{"level":10,"name":"Божественный щит","buff":{"defense":20},"duration":3,"mana_cost":25,"type":"holy"}]'),
('archer', 'Лучник — мастер дальнего боя, наносящий точные удары из лука. / Archer is a ranged combat master delivering precise shots with a bow.', 10, 4, 1, 3, 1, 0, 1, '/icons/classes/archer.png', '["bow","crossbow"]', '[{"level":1,"name":"Выстрел","damage":10,"type":"physical","range":5},{"level":5,"name":"Залп стрел","damage":8,"type":"physical","hits":3},{"level":10,"name":"Пронзающая стрела","damage":35,"type":"physical","armor_pen":50}]');

-- ----------------------------------------------------------------------------
-- Стартовая локация / Starting location
-- ----------------------------------------------------------------------------
INSERT INTO `locations` (`name`, `description`, `level_requirement`, `is_safe`, `has_monsters`, `has_shop`, `has_resource`, `position_x`, `position_y`, `connections`, `icon`) VALUES
('Начальная площадь', 'Центральная площадь города — безопасное место для новичков. Здесь можно найти первых NPC, магазин и выходы в другие локации. / Central city square — a safe zone for beginners. NPCs, a shop, and exits to other locations.', 1, TRUE, FALSE, TRUE, FALSE, 0, 0, '[]', '/icons/locations/town_square.png'),
('Лес теней', 'Тёмный лес на окраине города, где обитают волки и дикие существа. / A dark forest on the outskirts of the city, home to wolves and wild creatures.', 1, FALSE, TRUE, FALSE, TRUE, 1, 0, '[1]', '/icons/locations/dark_forest.png'),
('Горный перевал', 'Каменистый перевал с рудными залежами и горными троллями. / A rocky mountain pass with ore deposits and mountain trolls.', 5, FALSE, TRUE, FALSE, TRUE, 2, 1, '[2]', '/icons/locations/mountain_pass.png'),
('Рыбацкая деревня', 'Уютная деревня на берегу озера. Идеальное место для рыбалки. / A cozy village on the lake shore. Perfect for fishing.', 1, TRUE, FALSE, TRUE, TRUE, -1, 1, '[1]', '/icons/locations/fishing_village.png');


-- ============================================================================
-- ============================================================================
--  END OF SCHEMA / КОНЕЦ СХЕМЫ
-- ============================================================================
-- ============================================================================
--  Total tables: 24
--  Relations: Full relational integrity with foreign keys
--  Engine: InnoDB (ACID compliant)
--  Charset: utf8mb4 (full Unicode support including emoji)
-- ============================================================================
-- ============================================================================
