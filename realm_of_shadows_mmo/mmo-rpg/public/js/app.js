/**
 * App Module - Главный модуль приложения
 * Поддерживает полноценный single-player демо-режим без сервера
 *
 * ФИКСЫ:
 * - file:// протокол определяется ДО любых API вызовов
 * - Chat.init() НЕ вызывается в демо-режиме (нет конфликта обработчиков)
 * - Все демо-функции экспортированы для ui.js
 * - Каналы чата работают в демо-режиме
 */
const App = (() => {
    let currentUser = null;
    let currentCharacter = null;
    let isDemo = false;

    // =============================================
    //  DEMO DATA
    // =============================================

    const DEMO_MONSTERS = [
        { id: 1, name: 'Волк-страж', icon: '🐺', level: 2, hp: 45, maxHp: 45, attack: 8, defense: 3, agility: 12, exp: 25, gold: 10,
          loot: [{name:'Шкура волка',rarity:'common',chance:80},{name:'Зуб волка',rarity:'common',chance:40}] },
        { id: 2, name: 'Скелет-воин', icon: '💀', level: 4, hp: 80, maxHp: 80, attack: 14, defense: 6, agility: 5, exp: 55, gold: 25,
          loot: [{name:'Ржавый меч',rarity:'common',chance:30},{name:'Кость скелета',rarity:'common',chance:60}] },
        { id: 3, name: 'Гоблин-разведчик', icon: '👺', level: 3, hp: 55, maxHp: 55, attack: 11, defense: 4, agility: 15, exp: 40, gold: 18,
          loot: [{name:'Кинжал гоблина',rarity:'uncommon',chance:20},{name:'Гоблинская монета',rarity:'common',chance:70}] },
        { id: 4, name: 'Тёмный маг', icon: '🧙', level: 6, hp: 65, maxHp: 65, attack: 22, defense: 4, agility: 8, exp: 90, gold: 45,
          loot: [{name:'Посох тьмы',rarity:'rare',chance:15},{name:'Свиток огня',rarity:'uncommon',chance:35}] },
        { id: 5, name: 'Каменный голем', icon: '🗿', level: 8, hp: 150, maxHp: 150, attack: 18, defense: 20, agility: 2, exp: 130, gold: 60,
          loot: [{name:'Камень силы',rarity:'rare',chance:20},{name:'Железная слитка',rarity:'common',chance:50}] },
        { id: 6, name: 'Дракончик', icon: '🐲', level: 10, hp: 200, maxHp: 200, attack: 28, defense: 15, agility: 10, exp: 200, gold: 100,
          loot: [{name:'Чешуя дракона',rarity:'epic',chance:10},{name:'Клык дракона',rarity:'rare',chance:25}] },
        { id: 7, name: 'Призрак ночи', icon: '👻', level: 7, hp: 90, maxHp: 90, attack: 25, defense: 5, agility: 18, exp: 110, gold: 55,
          loot: [{name:'Эктоплазма',rarity:'uncommon',chance:40},{name:'Призрачный плащ',rarity:'rare',chance:18}] },
        { id: 8, name: 'Огненный элементаль', icon: '🔥', level: 9, hp: 130, maxHp: 130, attack: 30, defense: 8, agility: 12, exp: 160, gold: 75,
          loot: [{name:'Огненное ядро',rarity:'rare',chance:22},{name:'Искра',rarity:'uncommon',chance:50}] },
        { id: 9, name: 'Ледяная ведьма', icon: '❄️', level: 11, hp: 110, maxHp: 110, attack: 32, defense: 10, agility: 14, exp: 180, gold: 85,
          loot: [{name:'Ледяной кристалл',rarity:'epic',chance:12},{name:'Мантия льда',rarity:'rare',chance:28}] },
        { id: 10, name: 'Король демонов', icon: '😈', level: 15, hp: 350, maxHp: 350, attack: 45, defense: 25, agility: 15, exp: 500, gold: 250,
          loot: [{name:'Сердце демона',rarity:'legendary',chance:5},{name:'Корона тьмы',rarity:'epic',chance:15},{name:'Демонический клинок',rarity:'legendary',chance:3}] },
        { id: 11, name: 'Некромант', icon: '💀', level: 12, hp: 140, maxHp: 140, attack: 35, defense: 12, agility: 10, exp: 220, gold: 110,
          loot: [{name:'Филактерия',rarity:'epic',chance:8},{name:'Книга мёртвых',rarity:'rare',chance:20}] },
        { id: 12, name: 'Вампир-граф', icon: '🧛', level: 13, hp: 180, maxHp: 180, attack: 38, defense: 18, agility: 20, exp: 280, gold: 140,
          loot: [{name:'Плащ вампира',rarity:'epic',chance:12},{name:'Клык вампира',rarity:'rare',chance:30}] },
        { id: 13, name: 'Медуза Горгона', icon: '🐍', level: 14, hp: 160, maxHp: 160, attack: 40, defense: 14, agility: 16, exp: 320, gold: 160,
          loot: [{name:'Глаз горгоны',rarity:'legendary',chance:6},{name:'Змеиная чешуя',rarity:'epic',chance:18}] },
        { id: 14, name: 'Титан бури', icon: '⚡', level: 16, hp: 400, maxHp: 400, attack: 50, defense: 30, agility: 18, exp: 600, gold: 300,
          loot: [{name:'Сердце бури',rarity:'legendary',chance:4},{name:'Молния Зевса',rarity:'legendary',chance:2}] },
    ];

    const DEMO_ITEMS = [
        { id: 1, name: 'Железный меч', type: 'weapon', icon: '⚔️', rarity: 'common', stats: {strength: 3}, equipped: false, quantity: 1 },
        { id: 2, name: 'Стальной щит', type: 'shield', icon: '🛡️', rarity: 'rare', stats: {endurance: 5, strength: 2}, equipped: true, quantity: 1 },
        { id: 3, name: 'Зелье здоровья', type: 'consumable', icon: '🧪', rarity: 'uncommon', stats: {hp_restore: 50}, quantity: 5, equipped: false },
        { id: 4, name: 'Зелье маны', type: 'consumable', icon: '💧', rarity: 'uncommon', stats: {mana_restore: 30}, quantity: 3, equipped: false },
        { id: 5, name: 'Кожаная броня', type: 'armor', icon: '🦺', rarity: 'common', stats: {endurance: 4}, equipped: true, quantity: 1 },
        { id: 6, name: 'Шлем воина', type: 'helmet', icon: '🪖', rarity: 'uncommon', stats: {endurance: 3, defense: 2}, equipped: false, quantity: 1 },
        { id: 7, name: 'Перчатки ловкости', type: 'gloves', icon: '🧤', rarity: 'rare', stats: {agility: 6, crit: 3}, equipped: false, quantity: 1 },
        { id: 8, name: 'Сапоги скорости', type: 'boots', icon: '👢', rarity: 'epic', stats: {agility: 8, dodge: 5}, equipped: false, quantity: 1 },
        { id: 9, name: 'Амулет силы', type: 'accessory', icon: '📿', rarity: 'legendary', stats: {strength: 10, hp: 50}, equipped: false, quantity: 1 },
        { id: 10, name: 'Кольцо удачи', type: 'accessory', icon: '💍', rarity: 'rare', stats: {luck: 8, gold_find: 15}, equipped: false, quantity: 1 },
        { id: 11, name: 'Большое зелье здоровья', type: 'consumable', icon: '🍷', rarity: 'rare', stats: {hp_restore: 150}, quantity: 2, equipped: false },
        { id: 12, name: 'Эликсир опыта', type: 'consumable', icon: '✨', rarity: 'epic', stats: {exp_bonus: 50}, quantity: 1, equipped: false },
        { id: 13, name: 'Меч вампира', type: 'weapon', icon: '🗡️', rarity: 'epic', stats: {strength: 12, life_steal: 5}, equipped: false, quantity: 1 },
        { id: 14, name: 'Посох некроманта', type: 'weapon', icon: '🦴', rarity: 'legendary', stats: {intelligence: 15, mana_regen: 8}, equipped: false, quantity: 1 },
        { id: 15, name: 'Доспех титана', type: 'armor', icon: '⚙️', rarity: 'legendary', stats: {strength: 20, endurance: 15, defense: 10}, equipped: false, quantity: 1 },
        { id: 16, name: 'Крылья горгоны', type: 'accessory', icon: '🪽', rarity: 'epic', stats: {agility: 12, dodge: 8}, equipped: false, quantity: 1 },
        { id: 17, name: 'Громовой молот', type: 'weapon', icon: '🔨', rarity: 'legendary', stats: {strength: 18, lightning_dmg: 25}, equipped: false, quantity: 1 },
        { id: 18, name: 'Эликсир бессмертия', type: 'consumable', icon: '💫', rarity: 'legendary', stats: {revive: 1}, quantity: 1, equipped: false },
    ];

    const DEMO_LOCATIONS = [
        { id: 1, name: 'Город Аркхейм', desc: 'Столица королевства. Здесь безопасно. Торговцы, таверна и гильдия.', safe: true, monsters: [], features: ['shop', 'bank', 'guild', 'tavern'] },
        { id: 2, name: 'Тёмный лес', desc: 'Мрачный лес полон волков и гоблинов. Будьте осторожны!', safe: false, monsters: [0, 2], features: ['gathering', 'hidden_chest'] },
        { id: 3, name: 'Горы гарпий', desc: 'Скалистые горы с опасными магами и големами.', safe: false, monsters: [3, 4, 7], features: ['gathering', 'cave'] },
        { id: 4, name: 'Забытая крепость', desc: 'Древняя крепость, населённая нежитью и каменными стражами.', safe: false, monsters: [1, 4, 6], features: ['dungeon', 'boss'] },
        { id: 5, name: 'Логово дракона', desc: 'Вулканическая пещера, где обитают сильнейшие монстры. Только для смелых!', safe: false, monsters: [5, 8], features: ['lava', 'treasure'] },
        { id: 6, name: 'Ледяная пустошь', desc: 'Замёрзшая земля, где правят ледяные ведьмы и элементали.', safe: false, monsters: [8, 9], features: ['ice', 'gathering'] },
        { id: 7, name: 'Бездна тьмы', desc: 'Самое опасное место в игре. Здесь обитает Король демонов!', safe: false, monsters: [10], features: ['raid', 'final_boss'] },
        { id: 8, name: 'Склеп некроманта', desc: 'Тёмный склеп, где некроманты поднимают мёртвых. Остерегайтесь вампиров!', safe: false, monsters: [11, 12], features: ['crypt', 'blood_altar'] },
        { id: 9, name: 'Храм Горгоны', desc: 'Древний храм, где Медуза обращает путников в камень.', safe: false, monsters: [13], features: ['temple', 'stone_statues'] },
        { id: 10, name: 'Небесная кузница', desc: 'Легендарная кузница титанов, где куют молнии и бури.', safe: false, monsters: [14], features: ['forge', 'lightning_anvil'] },
    ];

    const DEMO_CHAT_MESSAGES = {
        global: [
            { sender: 'Система', color: '#c8a84e', lvl: 0, text: 'Добро пожаловать в Realm of Shadows! (Демо-режим)' },
            { sender: 'РыцарьУбийца', color: '#e74c3c', lvl: 7, text: 'Кто на босса? Нужна группа!' },
            { sender: 'Эльфийка', color: '#9b59b6', lvl: 3, text: 'Я маг, могу хилить' },
            { sender: 'Леголас123', color: '#3498db', lvl: 5, text: 'Нашёл эпический лук в крепости!' },
            { sender: 'ПаладинСила', color: '#f39c12', lvl: 4, text: 'Продам зелья, дёшево!' },
        ],
        local: [
            { sender: 'Система', color: '#c8a84e', lvl: 0, text: 'Локальный чат: видно только в вашей локации' },
            { sender: 'Странник', color: '#2ecc71', lvl: 6, text: 'Кто хочет пойти в Тёмный лес?' },
            { sender: 'Тень', color: '#95a5a6', lvl: 8, text: 'Осторожно, тут сильные монстры' },
        ],
        trade: [
            { sender: 'Система', color: '#c8a84e', lvl: 0, text: 'Торговый чат' },
            { sender: 'Торговец', color: '#f1c40f', lvl: 12, text: 'Продам Редкий меч +7 силы, 200 золота' },
            { sender: 'Покупатель', color: '#3498db', lvl: 5, text: 'Куплю зелья здоровья, 15 золота за шт.' },
        ],
        guild: [
            { sender: 'Система', color: '#c8a84e', lvl: 0, text: 'Гильдийный чат: доступен только членам гильдии' },
            { sender: 'ЛидерГильдии', color: '#e74c3c', lvl: 15, text: 'Война гильдий завтра в 20:00! Все обязаны явиться!' },
        ],
    };

    const DEMO_BOTS = [
        { name: 'РыцарьУбийца', color: '#e74c3c', lvl: 7 },
        { name: 'Эльфийка', color: '#9b59b6', lvl: 3 },
        { name: 'Леголас123', color: '#3498db', lvl: 5 },
        { name: 'ПаладинСила', color: '#f39c12', lvl: 4 },
    ];

    const DEMO_BOT_REPLIES = [
        'Согласен!', 'Хорошая идея', 'Кто со мной?', 'GG!',
        'Интересно...', 'Лол', 'Ого!', 'На помощь!',
        'Я иду!', 'Удачи!', 'Го в группу!', 'Круто!',
        'Вперёд!', 'Ребята, на помощь!', 'Спасибо!',
    ];

    const DEMO_ACHIEVEMENTS = [
        { id: 1, name: 'Первая кровь', desc: 'Победить первого монстра', icon: '🩸', req: {type:'wins', count:1}, reward: {gold:50}, completed: false },
        { id: 2, name: 'Охотник', desc: 'Победить 10 монстров', icon: '🏹', req: {type:'wins', count:10}, reward: {gold:150, exp:100}, completed: false },
        { id: 3, name: 'Легенда', desc: 'Победить 50 монстров', icon: '👑', req: {type:'wins', count:50}, reward: {gold:500, exp:500}, completed: false },
        { id: 4, name: 'Богач', desc: 'Накопить 1000 золота', icon: '💰', req: {type:'gold', count:1000}, reward: {exp:200}, completed: false },
        { id: 5, name: 'Герой', desc: 'Достичь 10 уровня', icon: '⭐', req: {type:'level', count:10}, reward: {gold:300, item:'epic_potion'}, completed: false },
        { id: 6, name: 'Критический удар', desc: 'Нанести 5 критических ударов', icon: '💥', req: {type:'crits', count:5}, reward: {gold:100}, completed: false },
        { id: 7, name: 'Неуловимый', desc: 'Уклониться 10 раз', icon: '💨', req: {type:'dodges', count:10}, reward: {gold:120, exp:80}, completed: false },
        { id: 8, name: 'Коллекционер', desc: 'Собрать 20 предметов', icon: '🎒', req: {type:'items', count:20}, reward: {gold:200}, completed: false },
        { id: 9, name: 'Воин света', desc: 'Победить 100 монстров', icon: '⚡', req: {type:'wins', count:100}, reward: {gold:1000, exp:1000, item:'legendary_sword'}, completed: false },
        { id: 10, name: 'Маг огня', desc: 'Использовать 25 способностей', icon: '🔥', req: {type:'abilities', count:25}, reward: {gold:300, exp:250}, completed: false },
        { id: 11, name: 'Торговец', desc: 'Совершить 10 покупок', icon: '💎', req: {type:'purchases', count:10}, reward: {gold:500, discount:10}, completed: false },
        { id: 12, name: 'Первопроходец', desc: 'Исследовать все локации', icon: '🗺️', req: {type:'locations', count:10}, reward: {gold:400, exp:300}, completed: false },
        { id: 13, name: 'Выживший', desc: 'Провести 50 боёв без смерти', icon: '🛡️', req: {type:'win_streak', count:50}, reward: {gold:800, exp:600, title:'Бессмертный'}, completed: false },
        { id: 14, name: 'Меценат', desc: 'Пожертвовать 5000 золота', icon: '🎁', req: {type:'donations', count:5000}, reward: {gold:0, exp:1000, title:'Благодетель'}, completed: false },
        { id: 15, name: 'Легендарный герой', desc: 'Получить все достижения', icon: '🏆', req: {type:'all_achievements', count:14}, reward: {gold:5000, exp:5000, title:'Легенда', item:'god_armor'}, completed: false },
        { id: 16, name: 'Убийца некроманта', desc: 'Победить Некроманта', icon: '🪦', req: {type:'monster_kills', monster_id:11}, reward: {gold:250, exp:300, item:'Филактерия'}, completed: false },
        { id: 17, name: 'Охотник на вампиров', desc: 'Победить Вампира-графа', icon: '🧄', req: {type:'monster_kills', monster_id:12}, reward: {gold:300, exp:350, item:'Плащ вампира'}, completed: false },
        { id: 18, name: 'Победитель Горгоны', desc: 'Победить Медузу Горгону', icon: '🪨', req: {type:'monster_kills', monster_id:13}, reward: {gold:400, exp:400, item:'Глаз горгоны'}, completed: false },
        { id: 19, name: 'Громовержец', desc: 'Победить Титана бури', icon: '⛈️', req: {type:'monster_kills', monster_id:14}, reward: {gold:600, exp:700, item:'Сердце бури'}, completed: false },
        { id: 20, name: 'Мастер крафта', desc: 'Создать 15 предметов', icon: '🔨', req: {type:'crafting', count:15}, reward: {gold:500, exp:400}, completed: false },
    ];

    const DEMO_CRAFTING_RECIPES = [
        { id: 1, name: 'Зелье здоровья', icon: '🧪', result: {name:'Зелье здоровья',type:'consumable',icon:'🧪',rarity:'uncommon',stats:{hp_restore:50}}, materials: [{name:'Трава',qty:2},{name:'Вода',qty:1}] },
        { id: 2, name: 'Зелье маны', icon: '💧', result: {name:'Зелье маны',type:'consumable',icon:'💧',rarity:'uncommon',stats:{mana_restore:30}}, materials: [{name:'Кристалл',qty:2},{name:'Вода',qty:1}] },
        { id: 3, name: 'Железный меч', icon: '⚔️', result: {name:'Железный меч',type:'weapon',icon:'⚔️',rarity:'common',stats:{strength:3}}, materials: [{name:'Железо',qty:3},{name:'Дерево',qty:1}] },
        { id: 4, name: 'Стальной щит', icon: '🛡️', result: {name:'Стальной щит',type:'shield',icon:'🛡️',rarity:'rare',stats:{endurance:5,strength:2}}, materials: [{name:'Сталь',qty:4},{name:'Кожа',qty:2}] },
        { id: 5, name: 'Эликсир силы', icon: '💪', result: {name:'Эликсир силы',type:'consumable',icon:'💪',rarity:'rare',stats:{strength_temp:5}}, materials: [{name:'Трава',qty:3},{name:'Кристалл',qty:2}] },
        { id: 6, name: 'Большое зелье здоровья', icon: '🍷', result: {name:'Большое зелье здоровья',type:'consumable',icon:'🍷',rarity:'rare',stats:{hp_restore:150}}, materials: [{name:'Трава',qty:5},{name:'Кристалл',qty:3},{name:'Вода',qty:2}] },
        { id: 7, name: 'Шлем воина', icon: '🪖', result: {name:'Шлем воина',type:'helmet',icon:'🪖',rarity:'uncommon',stats:{endurance:3,defense:2}}, materials: [{name:'Сталь',qty:3},{name:'Кожа',qty:2}] },
        { id: 8, name: 'Перчатки ловкости', icon: '🧤', result: {name:'Перчатки ловкости',type:'gloves',icon:'🧤',rarity:'rare',stats:{agility:6,crit:3}}, materials: [{name:'Кожа',qty:4},{name:'Кристалл',qty:2}] },
        { id: 9, name: 'Сапоги скорости', icon: '👢', result: {name:'Сапоги скорости',type:'boots',icon:'👢',rarity:'epic',stats:{agility:8,dodge:5}}, materials: [{name:'Сталь',qty:5},{name:'Кожа',qty:4},{name:'Кристалл',qty:3}] },
        { id: 10, name: 'Амулет силы', icon: '📿', result: {name:'Амулет силы',type:'accessory',icon:'📿',rarity:'legendary',stats:{strength:10,hp:50}}, materials: [{name:'Золото',qty:10},{name:'Кристалл',qty:5},{name:'Камень силы',qty:1}] },
        { id: 11, name: 'Зелье невидимости', icon: '🌫️', result: {name:'Зелье невидимости',type:'consumable',icon:'🌫️',rarity:'epic',stats:{stealth:30}}, materials: [{name:'Трава',qty:4},{name:'Кристалл',qty:3},{name:'Вода',qty:2}] },
        { id: 12, name: 'Свиток телепортации', icon: '📜', result: {name:'Свиток телепортации',type:'consumable',icon:'📜',rarity:'rare',stats:{teleport:1}}, materials: [{name:'Бумага',qty:2},{name:'Кристалл',qty:2},{name:'Чернила',qty:1}] },
        { id: 13, name: 'Меч вампира', icon: '🗡️', result: {name:'Меч вампира',type:'weapon',icon:'🗡️',rarity:'epic',stats:{strength:12,life_steal:5}}, materials: [{name:'Сталь',qty:8},{name:'Клык вампира',qty:3},{name:'Кристалл',qty:5}] },
        { id: 14, name: 'Посох некроманта', icon: '🦴', result: {name:'Посох некроманта',type:'weapon',icon:'🦴',rarity:'legendary',stats:{intelligence:15,mana_regen:8}}, materials: [{name:'Кость',qty:10},{name:'Филактерия',qty:1},{name:'Кристалл',qty:8}] },
        { id: 15, name: 'Доспех титана', icon: '⚙️', result: {name:'Доспех титана',type:'armor',icon:'⚙️',rarity:'legendary',stats:{strength:20,endurance:15,defense:10}}, materials: [{name:'Сталь',qty:15},{name:'Сердце бури',qty:1},{name:'Золото',qty:20}] },
        { id: 16, name: 'Крылья горгоны', icon: '🪽', result: {name:'Крылья горгоны',type:'accessory',icon:'🪽',rarity:'epic',stats:{agility:12,dodge:8}}, materials: [{name:'Змеиная чешуя',qty:5},{name:'Камень',qty:8},{name:'Кристалл',qty:6}] },
        { id: 17, name: 'Громовой молот', icon: '🔨', result: {name:'Громовой молот',type:'weapon',icon:'🔨',rarity:'legendary',stats:{strength:18,lightning_dmg:25}}, materials: [{name:'Молния Зевса',qty:1},{name:'Сталь',qty:12},{name:'Сердце бури',qty:2}] },
        { id: 18, name: 'Эликсир бессмертия', icon: '💫', result: {name:'Эликсир бессмертия',type:'consumable',icon:'💫',rarity:'legendary',stats:{revive:1}}, materials: [{name:'Глаз горгоны',qty:2},{name:'Филактерия',qty:1},{name:'Кристалл',qty:10}] },
    ];

    const DEMO_RESOURCES = ['Трава', 'Вода', 'Кристалл', 'Железо', 'Дерево', 'Сталь', 'Кожа', 'Камень', 'Золото', 'Бумага', 'Чернила', 'Камень силы', 'Кость', 'Клык вампира', 'Змеиная чешуя', 'Филактерия', 'Сердце бури', 'Молния Зевса', 'Глаз горгоны'];

    let demoAchievements = [];
    let demoResources = {};
    let demoStats = { wins:0, losses:0, crits:0, dodges:0, itemsCollected:0, monstersKilled:0 };
    let dailyRewardClaimed = false;

    // =============================================
    //  INITIALIZATION
    // =============================================

    async function init() {
        // Step 1: Set up UI and Combat (no API calls)
        UI.initTabs();
        UI.initCardSelectors();
        Combat.init();

        // Step 2: Detect file:// protocol → IMMEDIATE demo mode, NO API calls at all
        if (API.isFileProtocol()) {
            isDemo = true;
            setupDemoFormHandlers();
            UI.showToast('Демо-режим: Single-player без сервера', 'info', 5000);
            showDemoMode();
            return;
        }

        // Step 3: Server mode — set up Chat and auth forms, then check session
        Chat.init();
        setupServerFormHandlers();
        initTradeTabs();
        await checkSession();
    }

    function setupDemoFormHandlers() {
        // Auth forms — just show/hide for visual consistency
        document.getElementById('show-register').addEventListener('click', (e) => {
            e.preventDefault();
            document.getElementById('login-form').classList.add('hidden');
            document.getElementById('register-form').classList.remove('hidden');
        });
        document.getElementById('show-login').addEventListener('click', (e) => {
            e.preventDefault();
            document.getElementById('register-form').classList.add('hidden');
            document.getElementById('login-form').classList.remove('hidden');
        });

        // Login form in demo mode → go straight to demo
        document.getElementById('login-form').addEventListener('submit', (e) => {
            e.preventDefault();
            showDemoMode();
        });

        // Register form in demo mode → go straight to demo
        document.getElementById('register-form').addEventListener('submit', (e) => {
            e.preventDefault();
            showDemoMode();
        });
    }

    function setupServerFormHandlers() {
        document.getElementById('login-form').addEventListener('submit', handleLogin);
        document.getElementById('register-form').addEventListener('submit', handleRegister);
        document.getElementById('show-register').addEventListener('click', (e) => {
            e.preventDefault();
            document.getElementById('login-form').classList.add('hidden');
            document.getElementById('register-form').classList.remove('hidden');
        });
        document.getElementById('show-login').addEventListener('click', (e) => {
            e.preventDefault();
            document.getElementById('register-form').classList.add('hidden');
            document.getElementById('login-form').classList.remove('hidden');
        });
        document.getElementById('logout-btn').addEventListener('click', handleLogout);
        document.getElementById('char-create-form').addEventListener('submit', handleCharCreate);
        document.getElementById('close-char-create').addEventListener('click', () => UI.hideModal('char-create-modal'));
        document.querySelector('#char-create-modal .modal-overlay')?.addEventListener('click', () => UI.hideModal('char-create-modal'));
        initTradeTabs();
    }

    function initTradeTabs() {
        document.querySelectorAll('.btn-trade-tab').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('.btn-trade-tab').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                const tab = btn.dataset.tradeTab;
                document.querySelectorAll('.trade-content').forEach(tc => tc.classList.add('hidden'));
                const target = document.getElementById(`trade-${tab}`);
                if (target) target.classList.remove('hidden');
            });
        });
    }

    async function checkSession() {
        try {
            const data = await API.auth.me();
            if (data.data?.user) {
                currentUser = data.data.user;
                currentCharacter = data.data.character;
                isDemo = false;

                if (currentCharacter) {
                    await enterGame();
                } else {
                    UI.showScreen('game-screen');
                    UI.showModal('char-create-modal');
                }
                return;
            }
        } catch (err) {
            if (err.isFileProtocol || err.message?.includes('Failed to fetch') || !err.status) {
                isDemo = true;
                UI.showToast('Сервер недоступен. Переходим в демо-режим...', 'info', 4000);
                showDemoMode();
                return;
            }
        }
        UI.showScreen('auth-screen');
    }

    // =============================================
    //  DEMO MODE — Полноценный single-player
    // =============================================

    function showDemoMode() {
        UI.showScreen('game-screen');

        const demoChar = {
            name: 'Герой-Демо', level: 5, experience: 280, expToLevel: 506,
            current_hp: 85, max_hp: 120,
            current_mana: 40, max_mana: 60,
            current_energy: 80, max_energy: 100,
            gold: 250,
            strength: 12, agility: 10, endurance: 8,
            intelligence: 14, luck: 6, stat_points: 2,
            race: 'human', class: 'warrior'
        };
        currentCharacter = demoChar;
        demoInventory = JSON.parse(JSON.stringify(DEMO_ITEMS));
        demoLocation = 0;
        demoBattleLog = [];
        demoWinCount = 0;
        demoLoseCount = 0;
        demoAchievements = JSON.parse(JSON.stringify(DEMO_ACHIEVEMENTS));
        demoResources = { 'Трава': 5, 'Вода': 3, 'Кристалл': 2, 'Железо': 4, 'Дерево': 3, 'Сталь': 1, 'Кожа': 2, 'Камень': 2 };
        demoStats = { wins:0, losses:0, crits:0, dodges:0, itemsCollected:demoInventory.length, monstersKilled:0 };
        dailyRewardClaimed = false;

        // Populate all tabs
        updateDemoUI();
        loadDemoProfile();
        loadDemoLocation();
        loadDemoInventory();
        loadDemoCombatList();
        loadDemoChat();
        loadDemoQuests();
        loadDemoShop();
        loadDemoAchievements();
        loadDemoCrafting();
        loadDemoDailyReward();

        // Energy regeneration
        startEnergyRegen();

        // Demo logout handler
        document.getElementById('logout-btn').onclick = () => {
            stopEnergyRegen();
            isDemo = false;
            UI.showScreen('auth-screen');
            UI.showToast('Вы вышли из демо-режима', 'info');
        };
    }

    function startEnergyRegen() {
        stopEnergyRegen();
        energyRegenTimer = setInterval(() => {
            if (currentCharacter && currentCharacter.current_energy < currentCharacter.max_energy) {
                currentCharacter.current_energy = Math.min(currentCharacter.max_energy, currentCharacter.current_energy + 1);
                updateDemoUI();
            }
        }, 5000);
    }

    function stopEnergyRegen() {
        if (energyRegenTimer) {
            clearInterval(energyRegenTimer);
            energyRegenTimer = null;
        }
    }

    function updateDemoUI() {
        if (!currentCharacter) return;
        UI.updateHeaderStats(currentCharacter);
        document.getElementById('char-name').textContent = currentCharacter.name;
        document.getElementById('char-level').textContent = `Ур. ${currentCharacter.level}`;
        document.getElementById('char-avatar').textContent = UI.getClassIcon(currentCharacter.class) || '⚔️';
    }

    // ----- Profile -----

    function loadDemoProfile() {
        if (!currentCharacter) return;
        const c = currentCharacter;
        const expPct = c.experience > 0 ? Math.min(100, (c.experience / c.expToLevel * 100)).toFixed(0) : 0;
        document.getElementById('profile-stats').innerHTML = UI.renderProfileStats(c) +
            `<div class="stat-row"><span class="stat-label">Опыт</span><span class="stat-value">${c.experience}/${c.expToLevel}</span></div>
             <div class="stat-row"><div class="stat-bar stat-xp" style="width:100%;height:8px"><div class="stat-bar-fill" style="width:${expPct}%;background:var(--xp-color)"></div></div></div>`;

        // Equipment
        const equipped = demoInventory.filter(i => i.equipped);
        document.getElementById('profile-equipment').innerHTML = equipped.length > 0
            ? equipped.map(i => `<div class="item-card ${UI.getRarityClass(i.rarity)} equipped"><div class="item-icon">${i.icon}</div><div class="item-info"><div class="item-name">${i.name}</div><div class="item-rarity">${UI.getRarityName(i.rarity)}</div></div></div>`).join('')
            : '<p class="text-muted">Ничего не экипировано</p>';

        document.getElementById('profile-achievements').innerHTML =
            '<div style="padding:0.5rem 0"><span style="color:var(--accent)">Побед:</span> <strong>' + demoWinCount + '</strong></div>' +
            '<div style="padding:0.5rem 0"><span style="color:var(--hp-color)">Поражений:</span> <strong>' + demoLoseCount + '</strong></div>' +
            '<div style="padding:0.5rem 0"><span style="color:var(--mana-color)">Уровень:</span> <strong>' + c.level + '</strong></div>' +
            '<div style="padding:0.5rem 0"><span style="color:var(--energy-color)">Золото:</span> <strong>' + c.gold + '</strong></div>';

        // Stat upgrade buttons
        if (c.stat_points > 0) {
            document.querySelectorAll('.stat-upgrade').forEach(btn => {
                // Remove old listener by cloning
                const newBtn = btn.cloneNode(true);
                btn.parentNode.replaceChild(newBtn, btn);
                newBtn.addEventListener('click', () => {
                    const stat = newBtn.dataset.stat;
                    if (!stat || !currentCharacter) return;
                    currentCharacter[stat]++;
                    currentCharacter.stat_points--;
                    if (stat === 'endurance') {
                        currentCharacter.max_hp += 5;
                        currentCharacter.current_hp = Math.min(currentCharacter.current_hp + 5, currentCharacter.max_hp);
                    }
                    if (stat === 'intelligence') {
                        currentCharacter.max_mana += 3;
                        currentCharacter.current_mana = Math.min(currentCharacter.current_mana + 3, currentCharacter.max_mana);
                    }
                    UI.showToast(`${stat} улучшен! +1`, 'success');
                    updateDemoUI();
                    loadDemoProfile();
                });
            });
        }
    }

    // ----- Inventory -----

    function loadDemoInventory() {
        const container = document.getElementById('inventory-content');
        if (demoInventory.length === 0) {
            container.innerHTML = '<p class="text-muted">Инвентарь пуст</p>';
            return;
        }
        container.innerHTML = demoInventory.map((item, idx) => {
            const actions = [];
            if (!item.equipped && ['weapon', 'armor', 'shield', 'helmet', 'accessory'].includes(item.type)) {
                actions.push({ action: 'equip', label: 'Надеть', class: 'btn-primary' });
            }
            if (item.equipped) {
                actions.push({ action: 'unequip', label: 'Снять', class: 'btn-danger' });
            }
            if (item.type === 'consumable') {
                actions.push({ action: 'use', label: 'Исп.', class: 'btn-primary' });
            }
            actions.push({ action: 'drop', label: 'X', class: 'btn-danger' });
            return `<div class="item-card-wrapper" data-item-idx="${idx}">` + UI.renderItemCard(item, actions) + '</div>';
        }).join('');

        // Bind action handlers
        container.querySelectorAll('[data-action="equip"]').forEach(btn => {
            btn.addEventListener('click', () => {
                const idx = parseInt(btn.closest('[data-item-idx]').dataset.itemIdx);
                if (idx >= 0 && idx < demoInventory.length) {
                    demoInventory[idx].equipped = true;
                    UI.showToast('Предмет экипирован!', 'success');
                    loadDemoInventory(); loadDemoProfile(); updateDemoUI();
                }
            });
        });
        container.querySelectorAll('[data-action="unequip"]').forEach(btn => {
            btn.addEventListener('click', () => {
                const idx = parseInt(btn.closest('[data-item-idx]').dataset.itemIdx);
                if (idx >= 0 && idx < demoInventory.length) {
                    demoInventory[idx].equipped = false;
                    UI.showToast('Предмет снят', 'info');
                    loadDemoInventory(); loadDemoProfile(); updateDemoUI();
                }
            });
        });
        container.querySelectorAll('[data-action="use"]').forEach(btn => {
            btn.addEventListener('click', () => {
                const idx = parseInt(btn.closest('[data-item-idx]').dataset.itemIdx);
                if (idx >= 0 && idx < demoInventory.length) useDemoItem(idx);
            });
        });
        container.querySelectorAll('[data-action="drop"]').forEach(btn => {
            btn.addEventListener('click', () => {
                const idx = parseInt(btn.closest('[data-item-idx]').dataset.itemIdx);
                if (idx >= 0 && idx < demoInventory.length && confirm('Выбросить этот предмет?')) {
                    demoInventory.splice(idx, 1);
                    UI.showToast('Предмет выброшен', 'info');
                    loadDemoInventory();
                }
            });
        });
    }

    function useDemoItem(idx) {
        const item = demoInventory[idx];
        if (!item || item.type !== 'consumable') return false;
        const c = currentCharacter;
        if (!c) return false;
        const stats = item.stats || {};
        let used = false;

        if (stats.hp_restore && c.current_hp < c.max_hp) {
            c.current_hp = Math.min(c.max_hp, c.current_hp + stats.hp_restore);
            UI.showToast(`+${stats.hp_restore} HP!`, 'success');
            used = true;
        } else if (stats.mana_restore && c.current_mana < c.max_mana) {
            c.current_mana = Math.min(c.max_mana, c.current_mana + stats.mana_restore);
            UI.showToast(`+${stats.mana_restore} MP!`, 'success');
            used = true;
        }

        if (used) {
            item.quantity = (item.quantity || 1) - 1;
            if (item.quantity <= 0) demoInventory.splice(idx, 1);
            updateDemoUI();
            loadDemoInventory();
        } else {
            UI.showToast('HP и MP уже на максимуме', 'warning');
        }
        return used;
    }

    // ----- Location / Map -----

    function loadDemoLocation() {
        const loc = DEMO_LOCATIONS[demoLocation];
        if (!loc) return;
        document.getElementById('location-name').textContent = loc.name;
        document.getElementById('location-desc').textContent = loc.desc;

        // Travel buttons
        let html = '';
        DEMO_LOCATIONS.forEach((l, i) => {
            if (i !== demoLocation) {
                html += `<button class="btn btn-secondary travel-btn demo-travel" data-loc="${i}">${l.name}${l.safe ? ' [SAFE]' : ''}</button>`;
            }
        });
        document.getElementById('location-connections').innerHTML = html || '<p class="text-muted">Нет переходов</p>';

        document.querySelectorAll('.demo-travel').forEach(btn => {
            btn.addEventListener('click', () => {
                demoLocation = parseInt(btn.dataset.loc);
                loadDemoLocation();
                loadDemoCombatList();
                UI.showToast(`Вы переместились в ${DEMO_LOCATIONS[demoLocation].name}`, 'info');
            });
        });

        // Monsters
        const locMonsters = loc.monsters.map(i => DEMO_MONSTERS[i]).filter(Boolean);
        document.getElementById('location-monsters').innerHTML = locMonsters.length > 0
            ? locMonsters.map(m => `<div class="monster-card compact"><span>${m.icon} ${m.name} <small>Ур.${m.level}</small></span></div>`).join('')
            : '<p class="text-muted">Монстров нет (безопасная зона)</p>';

        // NPCs
        document.getElementById('location-npcs').innerHTML =
            loc.safe ? '<div class="npc-card"><span>Старый маг — Торговец</span></div><div class="npc-card"><span>Стражник</span></div><div class="npc-card"><span>Дающий квесты</span></div>'
                      : '<div class="npc-card"><span>Странствующий торговец</span></div>';

        // Players nearby
        const names = ['РыцарьУбийца', 'Эльфийка', 'Леголас123', 'ПаладинСила'];
        const count = Math.floor(Math.random() * 3) + 1;
        document.getElementById('location-players').innerHTML = names.slice(0, count).map(n =>
            `<div class="player-mini"><span>${n} <small>Ур.${Math.floor(Math.random() * 8) + 1}</small></span></div>`).join('');
    }

    // ----- Combat List -----

    function loadDemoCombatList() {
        const loc = DEMO_LOCATIONS[demoLocation];
        if (!loc) return;
        const locMonsters = loc.monsters.map(i => DEMO_MONSTERS[i]).filter(Boolean);
        const container = document.getElementById('combat-monster-list');
        if (locMonsters.length === 0) {
            container.innerHTML = '<p class="text-muted">Здесь безопасно. Монстров нет.</p>';
            return;
        }
        container.innerHTML = locMonsters.map(m => `
            <div class="monster-card">
                <div class="monster-icon">${m.icon}</div>
                <div class="monster-info">
                    <div class="monster-name">${m.name}</div>
                    <div class="monster-level">Уровень ${m.level}</div>
                    <div class="monster-hp">HP ${m.hp} | ATK ${m.attack} | DEF ${m.defense}</div>
                    <div style="font-size:0.75rem;color:var(--text-muted)">EXP: ${m.exp} | Золото: ${m.gold}</div>
                </div>
                <button class="btn btn-primary btn-sm combat-start-btn" data-monster-idx="${DEMO_MONSTERS.indexOf(m)}">
                    Атаковать
                </button>
            </div>
        `).join('');

        container.querySelectorAll('.combat-start-btn').forEach(btn => {
            btn.addEventListener('click', () => startDemoBattle(parseInt(btn.dataset.monsterIdx)));
        });
    }

    function startDemoBattle(monsterIdx) {
        const c = currentCharacter;
        if (!c) return;
        if (c.current_energy < 10) { UI.showToast('Недостаточно энергии! (нужно 10)', 'error'); return; }
        c.current_energy -= 10;
        updateDemoUI();

        const monster = JSON.parse(JSON.stringify(DEMO_MONSTERS[monsterIdx]));
        Combat.startDemoBattle(c, monster);
    }

    // ----- Chat (Demo) -----

    function loadDemoChat() {
        loadDemoChatMessages(demoChatChannel);
        setupDemoChatHandlers();
    }

    function loadDemoChatMessages(channel) {
        const container = document.getElementById('chat-messages');
        const messages = DEMO_CHAT_MESSAGES[channel] || DEMO_CHAT_MESSAGES.global;
        container.innerHTML = messages.map(msg => {
            const time = '12:' + String(Math.floor(Math.random() * 60)).padStart(2, '0');
            return `<div class="chat-msg"><span class="chat-time">[${time}]</span><span class="chat-sender" style="color:${msg.color}">${msg.sender}</span>${msg.lvl > 0 ? `<span class="chat-level">[${msg.lvl}]</span>` : ''}<span class="chat-text">${msg.text}</span></div>`;
        }).join('');
    }

    function setupDemoChatHandlers() {
        // Override channel buttons — remove any existing handlers by cloning
        document.querySelectorAll('.btn-chat-channel').forEach(btn => {
            const newBtn = btn.cloneNode(true);
            btn.parentNode.replaceChild(newBtn, btn);
            newBtn.addEventListener('click', () => {
                document.querySelectorAll('.btn-chat-channel').forEach(b => b.classList.remove('active'));
                newBtn.classList.add('active');
                demoChatChannel = newBtn.dataset.channel;
                loadDemoChatMessages(demoChatChannel);
            });
        });

        // Override chat form — remove any existing handlers by cloning
        const form = document.getElementById('chat-form');
        const newForm = form.cloneNode(true);
        form.parentNode.replaceChild(newForm, form);

        newForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const input = document.getElementById('chat-input');
            const msg = input.value.trim();
            if (!msg) return;

            const container = document.getElementById('chat-messages');
            const time = new Date().toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' });
            const charName = currentCharacter ? currentCharacter.name : 'Герой';
            const charLevel = currentCharacter ? currentCharacter.level : 1;

            container.innerHTML += `<div class="chat-msg"><span class="chat-time">[${time}]</span><span class="chat-sender" style="color:#c8a84e">${charName}</span><span class="chat-level">[${charLevel}]</span><span class="chat-text">${msg.replace(/</g, '&lt;')}</span></div>`;
            input.value = '';
            container.scrollTop = container.scrollHeight;

            // Bot replies after 1-3 seconds
            setTimeout(() => {
                const bot = DEMO_BOTS[Math.floor(Math.random() * DEMO_BOTS.length)];
                const reply = DEMO_BOT_REPLIES[Math.floor(Math.random() * DEMO_BOT_REPLIES.length)];
                const t2 = new Date().toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' });
                container.innerHTML += `<div class="chat-msg"><span class="chat-time">[${t2}]</span><span class="chat-sender" style="color:${bot.color}">${bot.name}</span><span class="chat-level">[${bot.lvl}]</span><span class="chat-text">${reply}</span></div>`;
                container.scrollTop = container.scrollHeight;
            }, 1000 + Math.random() * 2000);
        });
    }

    // ----- Quests (Demo) -----

    function loadDemoQuests() {
        document.getElementById('active-quests').innerHTML =
            `<div style="padding:1rem;background:var(--bg-card);border:1px solid var(--border-color);border-radius:8px;margin-bottom:0.5rem"><strong>Найди 5 шкур волков</strong><br><span class="text-muted">Прогресс: 0/5 — Тёмный лес</span><br><span style="color:var(--accent)">Награда: 50 опыта, 20 золота</span></div>` +
            `<div style="padding:1rem;background:var(--bg-card);border:1px solid var(--border-color);border-radius:8px;margin-bottom:0.5rem"><strong>Победи Скелета-воина</strong><br><span class="text-muted">Прогресс: 0/1 — Тёмный лес</span><br><span style="color:var(--accent)">Награда: 80 опыта, Зелье здоровья</span></div>`;

        document.getElementById('available-quests').innerHTML =
            `<div style="padding:1rem;background:var(--bg-card);border:1px solid var(--border-color);border-radius:8px;margin-bottom:0.5rem"><strong>Спаси торговца из пещеры</strong><br><span class="text-muted">Уровень: 5+ — Забытая крепость</span><br><span style="color:var(--rarity-rare)">Награда: 150 опыта, Редкий предмет</span><br><button class="btn btn-sm btn-primary" style="margin-top:0.5rem" onclick="this.textContent='Принято!';this.disabled=true;App.showToast('Квест принят!','success')">Принять</button></div>` +
            `<div style="padding:1rem;background:var(--bg-card);border:1px solid var(--border-color);border-radius:8px;margin-bottom:0.5rem"><strong>Собери 10 железных слитков</strong><br><span class="text-muted">Уровень: 3+ — Горы гарпий</span><br><span style="color:var(--rarity-uncommon)">Награда: 100 опыта, 50 золота</span><br><button class="btn btn-sm btn-primary" style="margin-top:0.5rem" onclick="this.textContent='Принято!';this.disabled=true;App.showToast('Квест принят!','success')">Принять</button></div>`;
    }

    // ----- Shop (Demo) -----

    function loadDemoShop() {
        const shopContainer = document.getElementById('shop-items');
        const auctionContainer = document.getElementById('auction-items');

        const shopItems = [
            { name: 'Зелье здоровья (большое)', icon: '🧪', rarity: 'uncommon', price: 30, desc: 'Восстанавливает 100 HP', stats: { hp_restore: 100 }, type: 'consumable' },
            { name: 'Зелье маны (большое)', icon: '💧', rarity: 'uncommon', price: 25, desc: 'Восстанавливает 60 MP', stats: { mana_restore: 60 }, type: 'consumable' },
            { name: 'Железный шлем', icon: '⛑️', rarity: 'common', price: 80, desc: 'Защита +3', stats: { endurance: 3 }, type: 'helmet' },
            { name: 'Магический амулет', icon: '📿', rarity: 'rare', price: 200, desc: 'Интеллект +5, Удача +3', stats: { intelligence: 5, luck: 3 }, type: 'accessory' },
            { name: 'Стальной меч', icon: '⚔️', rarity: 'uncommon', price: 120, desc: 'Сила +5', stats: { strength: 5 }, type: 'weapon' },
            { name: 'Кольчуга', icon: '🦺', rarity: 'rare', price: 180, desc: 'Выносливость +7', stats: { endurance: 7 }, type: 'armor' },
        ];
        shopContainer.innerHTML = shopItems.map((item, i) => `
            <div class="item-card ${UI.getRarityClass(item.rarity)}">
                <div class="item-icon">${item.icon}</div>
                <div class="item-info">
                    <div class="item-name">${item.name}</div>
                    <div class="item-rarity">${UI.getRarityName(item.rarity)}</div>
                    <div class="item-stats" style="color:var(--accent)">${item.price} золота</div>
                    <div style="font-size:0.75rem;color:var(--text-muted)">${item.desc}</div>
                </div>
                <div class="item-actions">
                    <button class="btn btn-xs btn-primary demo-buy" data-shop-idx="${i}">Купить</button>
                </div>
            </div>
        `).join('');

        shopContainer.querySelectorAll('.demo-buy').forEach(btn => {
            btn.addEventListener('click', () => {
                const idx = parseInt(btn.dataset.shopIdx);
                const item = shopItems[idx];
                if (!currentCharacter) return;
                if (currentCharacter.gold >= item.price) {
                    currentCharacter.gold -= item.price;
                    demoInventory.push({
                        id: Date.now() + Math.random(), name: item.name, type: item.type || 'consumable', icon: item.icon,
                        rarity: item.rarity, stats: item.stats || {}, quantity: 1, equipped: false
                    });
                    UI.showToast(`Куплено: ${item.name}!`, 'success');
                    updateDemoUI();
                    loadDemoInventory();
                } else {
                    UI.showToast('Недостаточно золота!', 'error');
                }
            });
        });

        // Auction
        auctionContainer.innerHTML = `
            <div class="item-card rarity-rare">
                <div class="item-icon">🏹</div>
                <div class="item-info">
                    <div class="item-name">Эльфийский длинный лук</div>
                    <div class="item-rarity">Редкий</div>
                    <div class="item-stats" style="color:var(--accent)">Ставка: 350 золота</div>
                    <div style="font-size:0.75rem;color:var(--text-muted)">Ловкость +6, Сила +3 | Продавец: Эльфийка</div>
                </div>
                <div class="item-actions">
                    <button class="btn btn-xs btn-primary" onclick="App.showToast('Ставка сделана! (демо)','success')">Поставить 360</button>
                </div>
            </div>
            <div class="item-card rarity-epic">
                <div class="item-icon">🔮</div>
                <div class="item-info">
                    <div class="item-name">Сфера разрушения</div>
                    <div class="item-rarity">Эпический</div>
                    <div class="item-stats" style="color:var(--accent)">Выкуп: 800 золота</div>
                    <div style="font-size:0.75rem;color:var(--text-muted)">Интеллект +10, Удача +5 | Продавец: ТёмныйЛорд99</div>
                </div>
                <div class="item-actions">
                    <button class="btn btn-xs btn-primary" onclick="App.showToast('Куплено! (демо)','success')">Купить за 800</button>
                </div>
            </div>
            <div class="item-card rarity-uncommon">
                <div class="item-icon">🗡️</div>
                <div class="item-info">
                    <div class="item-name">Ядовитый кинжал</div>
                    <div class="item-rarity">Необычный</div>
                    <div class="item-stats" style="color:var(--accent)">Ставка: 45 золота</div>
                    <div style="font-size:0.75rem;color:var(--text-muted)">Ловкость +4, Удача +2 | Продавец: Разбойник</div>
                </div>
                <div class="item-actions">
                    <button class="btn btn-xs btn-primary" onclick="App.showToast('Ставка сделана! (демо)','success')">Поставить 50</button>
                </div>
            </div>
        `;
    }

    // ----- Achievements (Demo) -----

    function loadDemoAchievements() {
        const container = document.getElementById('achievements-list');
        if (!container) return;

        const completedCount = demoAchievements.filter(a => a.completed).length;
        container.innerHTML = `
            <div style="padding:1rem;margin-bottom:1rem;background:linear-gradient(135deg,var(--bg-secondary),var(--bg-card));border-radius:8px;border:1px solid var(--border-color)">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.5rem">
                    <span style="font-size:1.1rem;font-weight:bold;color:var(--accent)">🏆 Достижения</span>
                    <span style="color:var(--text-muted)">${completedCount}/${demoAchievements.length}</span>
                </div>
                <div style="width:100%;height:10px;background:var(--bg-tertiary);border-radius:5px;overflow:hidden">
                    <div style="width:${(completedCount/demoAchievements.length)*100}%;height:100%;background:linear-gradient(90deg,var(--accent),var(--xp-color));transition:width 0.3s"></div>
                </div>
            </div>
            ${demoAchievements.map(ach => `
                <div class="achievement-card ${ach.completed ? 'completed' : ''}" style="padding:1rem;margin-bottom:0.75rem;background:var(--bg-card);border:1px solid ${ach.completed ? 'var(--accent)' : 'var(--border-color)'};border-radius:8px;display:flex;gap:1rem;align-items:center">
                    <div style="font-size:2.5rem">${ach.icon}</div>
                    <div style="flex:1">
                        <div style="font-weight:bold;color:${ach.completed ? 'var(--accent)' : 'var(--text)'}">${ach.name}</div>
                        <div style="font-size:0.85rem;color:var(--text-muted);margin:0.25rem 0">${ach.desc}</div>
                        <div style="font-size:0.75rem;color:var(--gold)">Награда: ${ach.reward.gold ? ach.reward.gold + ' золота' : ''} ${ach.reward.exp ? ach.reward.exp + ' опыта' : ''}</div>
                    </div>
                    <div style="font-size:1.5rem">${ach.completed ? '✅' : '🔒'}</div>
                </div>
            `).join('')}
        `;

        checkAchievements();
    }

    function checkAchievements() {
        let changed = false;
        demoAchievements.forEach(ach => {
            if (ach.completed) return;
            let completed = false;
            if (ach.req.type === 'wins' && demoStats.wins >= ach.req.count) completed = true;
            if (ach.req.type === 'gold' && currentCharacter && currentCharacter.gold >= ach.req.count) completed = true;
            if (ach.req.type === 'level' && currentCharacter && currentCharacter.level >= ach.req.count) completed = true;
            if (ach.req.type === 'crits' && demoStats.crits >= ach.req.count) completed = true;
            if (ach.req.type === 'dodges' && demoStats.dodges >= ach.req.count) completed = true;
            if (ach.req.type === 'items' && demoStats.itemsCollected >= ach.req.count) completed = true;

            if (completed) {
                ach.completed = true;
                changed = true;
                if (ach.reward.gold && currentCharacter) currentCharacter.gold += ach.reward.gold;
                if (ach.reward.exp && currentCharacter) {
                    currentCharacter.experience += ach.reward.exp;
                    checkLevelUp();
                }
                UI.showToast(`🏆 Достижение: ${ach.name}!`, 'success', 3000);
            }
        });
        if (changed) {
            updateDemoUI();
            loadDemoAchievements();
        }
    }

    // ----- Crafting (Demo) -----

    function loadDemoCrafting() {
        const container = document.getElementById('crafting-list');
        if (!container) return;

        container.innerHTML = `
            <div style="padding:1rem;margin-bottom:1rem;background:linear-gradient(135deg,var(--bg-secondary),var(--bg-card));border-radius:8px;border:1px solid var(--border-color)">
                <div style="font-size:1.1rem;font-weight:bold;color:var(--accent);margin-bottom:0.5rem">⚒️ Крафт</div>
                <div style="font-size:0.85rem;color:var(--text-muted)">Создавайте предметы из собранных ресурсов</div>
            </div>
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:1rem">
                ${DEMO_CRAFTING_RECIPES.map(recipe => {
                    const canCraft = recipe.materials.every(m => (demoResources[m.name] || 0) >= m.qty);
                    return `
                        <div class="item-card" style="border:1px solid ${canCraft ? 'var(--accent)' : 'var(--border-color)'};background:var(--bg-card)">
                            <div style="display:flex;gap:0.75rem;padding:0.75rem">
                                <div style="font-size:2rem">${recipe.icon}</div>
                                <div style="flex:1">
                                    <div style="font-weight:bold">${recipe.name}</div>
                                    <div style="font-size:0.75rem;color:var(--text-muted);margin:0.25rem 0">Материалы:</div>
                                    <div style="font-size:0.7rem">
                                        ${recipe.materials.map(m => `
                                            <span style="${(demoResources[m.name]||0) >= m.qty ? 'color:var(--success)' : 'color:var(--hp-color)'}">
                                                ${m.name}: ${demoResources[m.name]||0}/${m.qty}
                                            </span>
                                        `).join(', ')}
                                    </div>
                                </div>
                            </div>
                            <div style="padding:0.75rem;border-top:1px solid var(--border-color)">
                                <button class="btn btn-sm ${canCraft ? 'btn-primary' : 'btn-secondary'}" ${!canCraft ? 'disabled' : ''} onclick="App.craftItem(${recipe.id})">
                                    ${canCraft ? 'Скрафтить' : 'Недостаточно ресурсов'}
                                </button>
                            </div>
                        </div>
                    `;
                }).join('')}
            </div>
            <div style="margin-top:1.5rem;padding:1rem;background:var(--bg-card);border-radius:8px">
                <div style="font-weight:bold;margin-bottom:0.5rem;color:var(--accent)">🎒 Ваши ресурсы:</div>
                <div style="display:flex;flex-wrap:wrap;gap:0.5rem">
                    ${Object.entries(demoResources).map(([name, qty]) => `
                        <span style="padding:0.35rem 0.75rem;background:var(--bg-tertiary);border-radius:15px;font-size:0.85rem">
                            ${qty}x ${name}
                        </span>
                    `).join('')}
                </div>
            </div>
        `;
    }

    function craftItem(recipeId) {
        const recipe = DEMO_CRAFTING_RECIPES.find(r => r.id === recipeId);
        if (!recipe) return;

        // Check resources
        if (!recipe.materials.every(m => (demoResources[m.name] || 0) >= m.qty)) {
            UI.showToast('Недостаточно ресурсов!', 'error');
            return;
        }

        // Consume materials
        recipe.materials.forEach(m => { demoResources[m.name] -= m.qty; });

        // Add crafted item
        const newItem = {
            id: Date.now(),
            name: recipe.result.name,
            type: recipe.result.type,
            icon: recipe.result.icon,
            rarity: recipe.result.rarity,
            stats: recipe.result.stats,
            quantity: 1,
            equipped: false
        };
        demoInventory.push(newItem);
        demoStats.itemsCollected++;

        UI.showToast(`Скрафчено: ${recipe.name}!`, 'success');
        updateDemoUI();
        loadDemoInventory();
        loadDemoCrafting();
        checkAchievements();
    }

    // ----- Daily Reward (Demo) -----

    function loadDemoDailyReward() {
        const container = document.getElementById('daily-reward-container');
        if (!container) return;

        if (dailyRewardClaimed) {
            container.innerHTML = `
                <div style="padding:2rem;text-align:center;background:var(--bg-card);border-radius:8px;border:1px solid var(--border-color)">
                    <div style="font-size:3rem;margin-bottom:1rem">✅</div>
                    <div style="font-size:1.2rem;font-weight:bold;color:var(--accent)">Награда получена!</div>
                    <div style="color:var(--text-muted);margin-top:0.5rem">Приходите завтра за новой наградой</div>
                </div>
            `;
            return;
        }

        const rewards = [
            { icon: '💰', text: '100 золота', gold: 100 },
            { icon: '🧪', text: 'Зелье здоровья', item: 'health_potion' },
            { icon: '⭐', text: '50 опыта', exp: 50 },
            { icon: '💎', text: 'Кристалл удачи', luck: 1 },
        ];
        const todayReward = rewards[new Date().getDate() % rewards.length];

        container.innerHTML = `
            <div style="padding:2rem;text-align:center;background:linear-gradient(135deg,var(--bg-secondary),var(--bg-card));border-radius:8px;border:1px solid var(--accent)">
                <div style="font-size:3rem;margin-bottom:1rem">🎁</div>
                <div style="font-size:1.2rem;font-weight:bold;color:var(--accent);margin-bottom:0.5rem">Ежедневная награда</div>
                <div style="font-size:2.5rem;margin:1rem 0">${todayReward.icon}</div>
                <div style="color:var(--text);margin-bottom:1.5rem">${todayReward.text}</div>
                <button class="btn btn-primary" onclick="App.claimDailyReward()">Забрать награду</button>
            </div>
        `;
    }

    function claimDailyReward() {
        if (dailyRewardClaimed || !currentCharacter) return;

        const rewards = [
            { icon: '💰', text: '100 золота', gold: 100 },
            { icon: '🧪', text: 'Зелье здоровья', item: 'health_potion' },
            { icon: '⭐', text: '50 опыта', exp: 50 },
            { icon: '💎', text: 'Кристалл удачи', luck: 1 },
        ];
        const todayReward = rewards[new Date().getDate() % rewards.length];

        if (todayReward.gold) currentCharacter.gold += todayReward.gold;
        if (todayReward.exp) {
            currentCharacter.experience += todayReward.exp;
            checkLevelUp();
        }
        if (todayReward.item === 'health_potion') {
            demoInventory.push({ id: Date.now(), name: 'Зелье здоровья', type: 'consumable', icon: '🧪', rarity: 'uncommon', stats: { hp_restore: 50 }, quantity: 1, equipped: false });
        }
        if (todayReward.luck) currentCharacter.luck += todayReward.luck;

        dailyRewardClaimed = true;
        UI.showToast(`Получено: ${todayReward.text}!`, 'success', 3000);
        updateDemoUI();
        loadDemoDailyReward();
    }

    // =============================================
    //  SERVER MODE (API)
    // =============================================

    async function handleLogin(e) {
        e.preventDefault();
        const login = document.getElementById('login-input').value.trim();
        const password = document.getElementById('password-input').value;
        try {
            const data = await API.auth.login(login, password);
            UI.showToast(data.message, 'success');
            currentUser = { id: data.data.user_id, username: data.data.username };
            currentCharacter = data.data.character ? { id: data.data.character } : null;
            if (currentCharacter) await enterGame();
            else { UI.showScreen('game-screen'); UI.showModal('char-create-modal'); }
        } catch (err) { UI.showToast(err.message, 'error'); }
    }

    async function handleRegister(e) {
        e.preventDefault();
        const username = document.getElementById('reg-username').value.trim();
        const email = document.getElementById('reg-email').value.trim();
        const password = document.getElementById('reg-password').value;
        try {
            const data = await API.auth.register(username, email, password);
            UI.showToast(data.message, 'success');
            currentUser = { id: data.data.user_id, username: data.data.username };
            UI.showScreen('game-screen');
            UI.showModal('char-create-modal');
        } catch (err) {
            const errors = err.data?.errors;
            if (errors) Object.values(errors).forEach(msg => UI.showToast(msg, 'error'));
            else UI.showToast(err.message, 'error');
        }
    }

    async function handleCharCreate(e) {
        e.preventDefault();
        const name = document.getElementById('char-name-input')?.value?.trim() || document.getElementById('char-name')?.textContent || '';
        const race = UI.getSelectedCard('race-selector');
        const charClass = UI.getSelectedCard('class-selector');
        if (!name) { UI.showToast('Введите имя', 'warning'); return; }
        if (!race) { UI.showToast('Выберите расу', 'warning'); return; }
        if (!charClass) { UI.showToast('Выберите класс', 'warning'); return; }
        try {
            const data = await API.character.create(name, race, charClass);
            UI.showToast(data.message, 'success');
            UI.hideModal('char-create-modal');
            currentCharacter = { id: data.data.character_id };
            await enterGame();
        } catch (err) {
            const errors = err.data?.errors;
            if (errors) Object.values(errors).forEach(msg => UI.showToast(msg, 'error'));
            else UI.showToast(err.message, 'error');
        }
    }

    async function handleLogout() {
        try { await API.auth.logout(); } catch (e) {}
        Chat.stopPolling();
        stopEnergyRegen();
        currentUser = null; currentCharacter = null;
        UI.showScreen('auth-screen');
        UI.showToast('Вы вышли из аккаунта', 'info');
    }

    async function enterGame() {
        UI.showScreen('game-screen');
        try {
            const statsData = await API.character.stats();
            currentCharacter = statsData.data?.character;
            UI.updateHeaderStats(currentCharacter);
            loadProfile();
            Chat.loadChat();
        } catch (err) {
            console.error('Ошибка загрузки данных:', err);
            showDemoMode();
        }
    }

    async function loadProfile() {
        if (!currentCharacter) return;
        try {
            const data = await API.character.stats();
            const char = data.data?.character;
            if (char) {
                currentCharacter = char;
                UI.updateHeaderStats(char);
                document.getElementById('profile-stats').innerHTML = UI.renderProfileStats(char);
                document.querySelectorAll('.stat-upgrade').forEach(btn => {
                    btn.addEventListener('click', async () => {
                        try { await API.character.upgradeStat(btn.dataset.stat); loadProfile(); }
                        catch (err) { UI.showToast(err.message, 'error'); }
                    });
                });
            }
        } catch (err) { UI.showToast(err.message, 'error'); }
    }

    async function loadInventory() {
        UI.showLoading('inventory-content');
        try {
            const data = await API.inventory.list();
            const items = data.data?.items || [];
            const container = document.getElementById('inventory-content');
            if (items.length === 0) { container.innerHTML = '<p class="text-muted">Инвентарь пуст</p>'; return; }
            container.innerHTML = items.map(item => {
                const actions = [];
                if (!item.is_equipped && ['weapon', 'armor', 'helmet', 'shield', 'accessory'].includes(item.type))
                    actions.push({ action: 'equip', label: 'Надеть', class: 'btn-primary' });
                if (item.is_equipped) actions.push({ action: 'unequip', label: 'Снять', class: 'btn-danger' });
                if (item.type === 'consumable') actions.push({ action: 'use', label: 'Исп.', class: 'btn-primary' });
                actions.push({ action: 'drop', label: 'X', class: 'btn-danger' });
                return UI.renderItemCard(item, actions);
            }).join('');
            ['equip', 'unequip', 'use', 'drop'].forEach(action => {
                container.querySelectorAll(`[data-action="${action}"]`).forEach(btn => {
                    btn.addEventListener('click', async () => {
                        try {
                            const r = await API.inventory[action](parseInt(btn.dataset.inventoryId));
                            UI.showToast(r.message, 'success'); loadInventory();
                        } catch (err) { UI.showToast(err.message, 'error'); }
                    });
                });
            });
        } catch (err) {
            document.getElementById('inventory-content').innerHTML = `<p class="text-error">${err.message}</p>`;
        }
    }

    async function loadLocation() {
        try {
            const data = await API.location.current();
            const loc = data.data;
            document.getElementById('location-name').textContent = loc.location?.name || 'Неизвестно';
            document.getElementById('location-desc').textContent = loc.location?.description || '';
            document.getElementById('location-connections').innerHTML = (loc.connected || []).map(c =>
                `<button class="btn btn-secondary travel-btn" data-location-id="${c.id}">${c.icon || '🗺️'} ${c.name}</button>`
            ).join('') || '<p class="text-muted">Нет переходов</p>';
            document.querySelectorAll('.travel-btn').forEach(btn => {
                btn.addEventListener('click', async () => {
                    try { await API.location.travel(parseInt(btn.dataset.locationId)); loadLocation(); }
                    catch (err) { UI.showToast(err.message, 'error'); }
                });
            });
            document.getElementById('location-monsters').innerHTML = (loc.monsters || []).map(m =>
                `<div class="monster-card compact"><span>${m.icon || '👹'} ${m.name} <small>Ур.${m.level}</small></span></div>`
            ).join('') || '<p class="text-muted">Монстров нет</p>';
            document.getElementById('location-npcs').innerHTML = (loc.npcs || []).map(n =>
                `<div class="npc-card"><span>${n.icon || '👤'} ${n.name} <small>${n.type}</small></span></div>`
            ).join('') || '<p class="text-muted">NPC нет</p>';
            document.getElementById('location-players').innerHTML = (loc.players || []).map(p =>
                `<div class="player-mini"><span>${UI.getClassIcon(p.class)} ${p.name} <small>Ур.${p.level}</small></span>${p.guild_name ? `<span class="guild-tag">${p.guild_name}</span>` : ''}</div>`
            ).join('') || '<p class="text-muted">Других игроков нет</p>';
        } catch (err) { UI.showToast(err.message, 'error'); }
    }

    async function loadQuests() {
        try {
            const [activeData, availData] = await Promise.all([
                API.quests.active(), API.quests.list()
            ]);
            const active = activeData.data?.quests || [];
            const available = availData.data?.quests || [];
            document.getElementById('active-quests').innerHTML = active.length > 0
                ? active.map(q => `<div style="padding:1rem;background:var(--bg-card);border:1px solid var(--border-color);border-radius:8px;margin-bottom:0.5rem"><strong>${q.title}</strong><br><span class="text-muted">${q.description}</span><br><span style="color:var(--accent)">${q.reward_text}</span></div>`).join('')
                : '<p class="text-muted">Нет активных заданий</p>';
            document.getElementById('available-quests').innerHTML = available.length > 0
                ? available.map(q => `<div style="padding:1rem;background:var(--bg-card);border:1px solid var(--border-color);border-radius:8px;margin-bottom:0.5rem"><strong>${q.title}</strong><br><span class="text-muted">${q.description}</span><br><span style="color:var(--accent)">${q.reward_text}</span><br><button class="btn btn-sm btn-primary" style="margin-top:0.5rem" data-quest-id="${q.id}">Принять</button></div>`).join('')
                : '<p class="text-muted">Нет доступных заданий</p>';
        } catch (err) {
            document.getElementById('active-quests').innerHTML = '<p class="text-muted">Нет активных заданий</p>';
            document.getElementById('available-quests').innerHTML = '<p class="text-muted">Нет доступных заданий</p>';
        }
    }

    // =============================================
    //  PUBLIC API
    // =============================================

    return {
        init,
        isDemo: () => isDemo,
        loadProfile,
        loadInventory,
        loadLocation,
        loadQuests,
        showDemoMode,
        updateDemoUI,
        loadDemoCombatList,
        loadDemoInventory,
        loadDemoLocation,
        loadDemoProfile,
        loadDemoChat,
        loadDemoQuests,
        useDemoItem,
        loadDemoAchievements,
        loadDemoCrafting,
        loadDemoDailyReward,
        craftItem,
        claimDailyReward,
        checkAchievements,
        currentCharacter: () => currentCharacter,
        demoInventory: () => demoInventory,
        demoStats: () => demoStats,
        addDemoItem: (item) => { demoInventory.push(item); loadDemoInventory(); },
        addDemoWin: () => { demoWinCount++; },
        addDemoLoss: () => { demoLoseCount++; },
        setDemoLocation: (loc) => { demoLocation = loc; loadDemoLocation(); loadDemoCombatList(); },
        getDemoLocation: () => demoLocation,
        showToast: (...args) => UI.showToast(...args),
    };
})();

// Launch
document.addEventListener('DOMContentLoaded', App.init);
