<?php
declare(strict_types=1);

return [
    // Auth
    ['POST', '/api/auth/login', 'AuthController@login'],
    ['POST', '/api/auth/register', 'AuthController@register'],
    ['POST', '/api/auth/logout', 'AuthController@logout'],
    ['GET',  '/api/auth/me',     'AuthController@me'],

    // Character
    ['GET',    '/api/character',        'CharacterController@index'],
    ['POST',   '/api/character/create', 'CharacterController@create'],
    ['GET',    '/api/character/stats',  'CharacterController@stats'],
    ['POST',   '/api/character/stats/upgrade', 'CharacterController@upgradeStat'],
    ['GET',    '/api/character/{id}',   'CharacterController@view'],

    // Inventory
    ['GET',  '/api/inventory',          'InventoryController@index'],
    ['POST', '/api/inventory/equip',    'InventoryController@equip'],
    ['POST', '/api/inventory/unequip',  'InventoryController@unequip'],
    ['POST', '/api/inventory/use',      'InventoryController@useItem'],
    ['POST', '/api/inventory/drop',     'InventoryController@drop'],

    // Locations
    ['GET',  '/api/location/current',   'LocationController@current'],
    ['GET',  '/api/location/{id}',      'LocationController@view'],
    ['POST', '/api/location/travel',    'LocationController@travel'],

    // Combat
    ['POST', '/api/combat/start',       'CombatController@start'],
    ['POST', '/api/combat/action',      'CombatController@action'],
    ['GET',  '/api/combat/status',      'CombatController@status'],
    ['GET',  '/api/combat/log',         'CombatController@log'],
    ['POST', '/api/combat/flee',        'CombatController@flee'],

    // Chat
    ['GET',  '/api/chat/{channel}',     'ChatController@messages'],
    ['POST', '/api/chat/send',          'ChatController@send'],

    // Shop/Auction
    ['GET',  '/api/shop/list',          'ShopController@list'],
    ['POST', '/api/shop/buy',           'ShopController@buy'],
    ['POST', '/api/shop/sell',          'ShopController@sell'],
    ['GET',  '/api/auction/list',       'AuctionController@list'],
    ['POST', '/api/auction/create',     'AuctionController@create'],
    ['POST', '/api/auction/bid',        'AuctionController@bid'],

    // Guild
    ['GET',  '/api/guild/my',           'GuildController@my'],
    ['POST', '/api/guild/create',       'GuildController@create'],
    ['POST', '/api/guild/invite',       'GuildController@invite'],
    ['POST', '/api/guild/leave',        'GuildController@leave'],

    // Friends
    ['GET',  '/api/friends',            'FriendController@list'],
    ['POST', '/api/friends/request',    'FriendController@request'],
    ['POST', '/api/friends/accept',     'FriendController@accept'],
    ['POST', '/api/friends/block',      'FriendController@block'],

    // Quests
    ['GET',  '/api/quests',             'QuestController@list'],
    ['GET',  '/api/quests/active',      'QuestController@active'],
    ['POST', '/api/quests/accept',      'QuestController@accept'],
    ['POST', '/api/quests/complete',    'QuestController@complete'],

    // Resources (mining, fishing, etc.)
    ['POST', '/api/resource/gather',    'ResourceController@gather'],

    // Admin
    ['GET',  '/api/admin/users',        'AdminController@users'],
    ['GET',  '/api/admin/items',        'AdminController@items'],
    ['POST', '/api/admin/items/create', 'AdminController@createItem'],
    ['GET',  '/api/admin/logs',         'AdminController@logs'],
    ['POST', '/api/admin/ban',          'AdminController@ban'],

    // Static pages (SPA fallback)
    // Commented out: index.php fast path already handles non-API GET requests by serving index.html directly.
    // This route caused all non-API requests to go through PHP unnecessarily.
    // ['GET',  '/{path:.*}',              'PageController@index'],
];
