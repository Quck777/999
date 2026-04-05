/**
 * API Module - Wrapper around Fetch API for server communication
 * Handles file:// protocol detection, CSRF tokens, error handling
 */
const API = (() => {
    const BASE_URL = '';
    let csrfToken = null;

    // Detect file:// protocol — no server available
    function isFileProtocol() {
        return window.location.protocol === 'file:';
    }

    // Update CSRF token from server responses
    function updateCsrf(token) {
        if (token) csrfToken = token;
    }

    // Core request method
    async function request(method, endpoint, data = null, options = {}) {
        // Immediately reject on file:// — no server to talk to
        if (isFileProtocol()) {
            const err = new Error('Файловый протокол — демо-режим');
            err.isFileProtocol = true;
            throw err;
        }

        const url = BASE_URL + endpoint;
        const headers = {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
        };

        if (csrfToken) {
            headers['X-CSRF-Token'] = csrfToken;
        }

        const config = {
            method,
            headers,
            credentials: 'same-origin',
        };

        if (data && method !== 'GET') {
            config.body = JSON.stringify(data);
            if (!data.csrf_token && csrfToken) {
                data.csrf_token = csrfToken;
                config.body = JSON.stringify(data);
            }
        }

        try {
            const response = await fetch(url, config);
            const result = await response.json();

            if (result.csrf) {
                updateCsrf(result.csrf);
            }

            if (!response.ok || !result.success) {
                const error = new Error(result.message || 'Произошла ошибка');
                error.status = response.status;
                error.data = result;
                throw error;
            }

            return result;
        } catch (err) {
            if (err.isFileProtocol) throw err;
            if (err.name === 'TypeError' || !err.status) {
                const wrapped = new Error('Ошибка соединения с сервером. Проверьте интернет.');
                wrapped.isFileProtocol = true;
                throw wrapped;
            }
            throw err;
        }
    }

    // Auth API
    const auth = {
        login(login, password) {
            return request('POST', '/api/auth/login', { login, password });
        },
        register(username, email, password) {
            return request('POST', '/api/auth/register', { username, email, password });
        },
        logout() {
            return request('POST', '/api/auth/logout');
        },
        me() {
            return request('GET', '/api/auth/me');
        },
    };

    // Character API
    const character = {
        list() {
            return request('GET', '/api/character');
        },
        create(name, race, className) {
            return request('POST', '/api/character/create', { name, race, class: className });
        },
        stats() {
            return request('GET', '/api/character/stats');
        },
        upgradeStat(stat) {
            return request('POST', '/api/character/stats/upgrade', { stat });
        },
        view(id) {
            return request('GET', `/api/character/${id}`);
        },
    };

    // Inventory API
    const inventory = {
        list(page = 1) {
            return request('GET', `/api/inventory?page=${page}`);
        },
        equip(inventoryId) {
            return request('POST', '/api/inventory/equip', { inventory_id: inventoryId });
        },
        unequip(inventoryId) {
            return request('POST', '/api/inventory/unequip', { inventory_id: inventoryId });
        },
        use(inventoryId) {
            return request('POST', '/api/inventory/use', { inventory_id: inventoryId });
        },
        drop(inventoryId) {
            return request('POST', '/api/inventory/drop', { inventory_id: inventoryId });
        },
    };

    // Location API
    const location = {
        current() {
            return request('GET', '/api/location/current');
        },
        travel(locationId) {
            return request('POST', '/api/location/travel', { location_id: locationId });
        },
    };

    // Combat API
    const combat = {
        start(monsterId) {
            return request('POST', '/api/combat/start', { monster_id: monsterId });
        },
        action(battleId, action, params = {}) {
            return request('POST', '/api/combat/action', { battle_id: battleId, action, params });
        },
        status(battleId = null) {
            const query = battleId ? `?battle_id=${battleId}` : '';
            return request('GET', `/api/combat/status${query}`);
        },
        log(battleId, sinceTurn = 0) {
            return request('GET', `/api/combat/log?battle_id=${battleId}&since_turn=${sinceTurn}`);
        },
        flee(battleId) {
            return request('POST', '/api/combat/flee', { battle_id: battleId });
        },
    };

    // Chat API
    const chat = {
        messages(channel, limit = 50, beforeId = 0) {
            let url = `/api/chat/${channel}?limit=${limit}`;
            if (beforeId > 0) url += `&before_id=${beforeId}`;
            return request('GET', url);
        },
        send(channel, message, targetCharacterId = null) {
            const data = { channel, message };
            if (targetCharacterId) data.target_character_id = targetCharacterId;
            return request('POST', '/api/chat/send', data);
        },
    };

    // Shop API
    const shop = {
        list(locationId = null) {
            const query = locationId ? `?location_id=${locationId}` : '';
            return request('GET', `/api/shop/list${query}`);
        },
        buy(itemId) {
            return request('POST', '/api/shop/buy', { item_id: itemId });
        },
        sell(inventoryId) {
            return request('POST', '/api/shop/sell', { inventory_id: inventoryId });
        },
    };

    // Quest API
    const quests = {
        list() {
            return request('GET', '/api/quests');
        },
        active() {
            return request('GET', '/api/quests/active');
        },
        accept(questId) {
            return request('POST', '/api/quests/accept', { quest_id: questId });
        },
        complete(questId) {
            return request('POST', '/api/quests/complete', { quest_id: questId });
        },
    };

    // Resource API
    const resource = {
        gather() {
            return request('POST', '/api/resource/gather');
        },
    };

    return {
        isFileProtocol,
        updateCsrf,
        request,
        auth,
        character,
        inventory,
        location,
        combat,
        chat,
        shop,
        quests,
        resource,
    };
})();
