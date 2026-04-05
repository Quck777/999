/**
 * UI Module - Handles rendering, toasts, tabs, modals
 * ФИКС: При переключении вкладок проверяет демо-режим и вызывает демо-функции
 */
const UI = (() => {
    // Toast notification system
    function showToast(message, type = 'info', duration = 3000) {
        const container = document.getElementById('toast-container');
        if (!container) return;
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;

        const icons = { success: '✅', error: '❌', warning: '⚠️', info: 'ℹ️' };
        toast.innerHTML = `<span class="toast-icon">${icons[type] || icons.info}</span><span class="toast-message">${message}</span>`;

        container.appendChild(toast);
        requestAnimationFrame(() => toast.classList.add('show'));

        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 300);
        }, duration);
    }

    // Tab switching — routes to demo functions when in demo mode
    function initTabs() {
        const tabs = document.querySelectorAll('.nav-tab');
        tabs.forEach(tab => {
            tab.addEventListener('click', () => {
                const targetTab = tab.dataset.tab;

                // Update active tab
                tabs.forEach(t => t.classList.remove('active'));
                tab.classList.add('active');

                // Update active content
                document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
                const content = document.getElementById(`tab-${targetTab}`);
                if (content) content.classList.add('active');

                // Trigger tab-specific loading — check demo mode first!
                try {
                    const demo = typeof App !== 'undefined' && App.isDemo && App.isDemo();
                    switch (targetTab) {
                        case 'profile':
                            if (demo) App.loadDemoProfile();
                            else if (typeof App !== 'undefined') App.loadProfile();
                            break;
                        case 'inventory':
                            if (demo) App.loadDemoInventory();
                            else if (typeof App !== 'undefined') App.loadInventory();
                            break;
                        case 'map':
                            if (demo) App.loadDemoLocation();
                            else if (typeof App !== 'undefined') App.loadLocation();
                            break;
                        case 'combat':
                            if (demo) App.loadDemoCombatList();
                            else if (typeof Combat !== 'undefined') Combat.loadCombat();
                            break;
                        case 'chat':
                            if (demo) App.loadDemoChat();
                            else if (typeof Chat !== 'undefined') Chat.loadChat();
                            break;
                        case 'trade':
                            // Trade tabs are CSS-only toggles, no API needed
                            // But reload shop items on tab switch
                            if (demo) App.loadDemoShop();
                            break;
                        case 'quests':
                            if (demo) App.loadDemoQuests();
                            else if (typeof App !== 'undefined') App.loadQuests();
                            break;
                    }
                } catch (err) {
                    console.warn('Tab loader error:', err.message);
                }
            });
        });
    }

    // Modal management
    function showModal(id) {
        document.getElementById(id)?.classList.remove('hidden');
    }
    function hideModal(id) {
        document.getElementById(id)?.classList.add('hidden');
    }

    // Show/hide screens
    function showScreen(id) {
        document.querySelectorAll('.screen').forEach(s => s.classList.remove('active'));
        document.getElementById(id)?.classList.add('active');
    }

    // Update header stats
    function updateHeaderStats(char) {
        if (!char) return;

        const hpPct = char.max_hp > 0 ? (char.current_hp / char.max_hp * 100).toFixed(0) : 0;
        const manaPct = char.max_mana > 0 ? (char.current_mana / char.max_mana * 100).toFixed(0) : 0;
        const energyPct = char.max_energy > 0 ? (char.current_energy / char.max_energy * 100).toFixed(0) : 0;

        const hpBar = document.getElementById('header-hp-bar');
        const hpText = document.getElementById('header-hp-text');
        if (hpBar) hpBar.style.width = hpPct + '%';
        if (hpText) hpText.textContent = `${char.current_hp}/${char.max_hp} HP`;

        const manaBar = document.getElementById('header-mana-bar');
        const manaText = document.getElementById('header-mana-text');
        if (manaBar) manaBar.style.width = manaPct + '%';
        if (manaText) manaText.textContent = `${char.current_mana}/${char.max_mana} MP`;

        const energyBar = document.getElementById('header-energy-bar');
        const energyText = document.getElementById('header-energy-text');
        if (energyBar) energyBar.style.width = energyPct + '%';
        if (energyText) energyText.textContent = `${char.current_energy}/${char.max_energy} EP`;

        const goldEl = document.getElementById('header-gold');
        if (goldEl) goldEl.textContent = `💰 ${char.gold}`;

        const nameEl = document.getElementById('char-name');
        if (nameEl) nameEl.textContent = char.name;

        const levelEl = document.getElementById('char-level');
        if (levelEl) levelEl.textContent = `Ур. ${char.level}`;
    }

    // Get class icon
    function getClassIcon(cls) {
        const icons = { warrior: '⚔️', mage: '🔮', rogue: '🗡️', paladin: '🛡️', archer: '🏹' };
        return icons[cls] || '👤';
    }

    // Get race icon
    function getRaceIcon(race) {
        const icons = { human: '🧑', elf: '🧝', dwarf: '⛏️', orc: '👹', undead: '💀' };
        return icons[race] || '👤';
    }

    // Get rarity class
    function getRarityClass(rarity) {
        return `rarity-${rarity || 'common'}`;
    }

    // Format rarity name
    function getRarityName(rarity) {
        const names = { common: 'Обычный', uncommon: 'Необычный', rare: 'Редкий', epic: 'Эпический', legendary: 'Легендарный' };
        return names[rarity] || 'Обычный';
    }

    // Stat name translations
    const STAT_NAMES = {
        strength: '💪 Сила',
        agility: '🏃 Ловкость',
        endurance: '🛡️ Выносливость',
        intelligence: '🧠 Интеллект',
        luck: '🍀 Удача',
    };

    // Render profile stats
    function renderProfileStats(stats) {
        if (!stats) return '';
        return `
            <div class="stats-grid">
                ${Object.entries(STAT_NAMES).map(([key, label]) => `
                    <div class="stat-row">
                        <span class="stat-label">${label}</span>
                        <span class="stat-value">${stats[key] || 0}</span>
                        <button class="btn btn-xs btn-secondary stat-upgrade" data-stat="${key}" title="Улучшить (+1)">
                            ▲
                        </button>
                    </div>
                `).join('')}
                <div class="stat-row">
                    <span class="stat-label">✨ Очки характеристик</span>
                    <span class="stat-value stat-points">${stats.stat_points || 0}</span>
                </div>
            </div>
        `;
    }

    // Render an item card
    function renderItemCard(item, actions = []) {
        const stats = item.stats ? (typeof item.stats === 'string' ? JSON.parse(item.stats) : item.stats) : {};
        const statText = Object.entries(stats || {})
            .filter(([k]) => k !== 'hp' && k !== 'mana' && k !== 'hp_restore' && k !== 'mana_restore')
            .map(([k, v]) => {
                const names = { strength: 'Сила', agility: 'Ловкость', endurance: 'Вын.', intelligence: 'Инт.', luck: 'Удача' };
                return `${names[k] || k} +${v}`;
            }).join(', ');

        const actionButtons = actions.map(a =>
            `<button class="btn btn-xs ${a.class || 'btn-secondary'}" data-action="${a.action}" data-inventory-id="${item.inventory_id || item.id || ''}">${a.label}</button>`
        ).join('');

        const eqBadge = (item.is_equipped || item.equipped) ? '<div class="item-equipped-badge">✅ Экипировано</div>' : '';
        const qtyHtml = item.quantity > 1 ? `<div class="item-quantity">x${item.quantity}</div>` : '';

        return `
            <div class="item-card ${getRarityClass(item.rarity)} ${(item.is_equipped || item.equipped) ? 'equipped' : ''}">
                <div class="item-icon">${item.icon || '📦'}</div>
                <div class="item-info">
                    <div class="item-name">${item.name}</div>
                    <div class="item-rarity">${getRarityName(item.rarity)}</div>
                    ${statText ? `<div class="item-stats">${statText}</div>` : ''}
                    ${qtyHtml}
                    ${eqBadge}
                </div>
                <div class="item-actions">${actionButtons}</div>
            </div>
        `;
    }

    // Loading spinner
    function showLoading(containerId) {
        const el = document.getElementById(containerId);
        if (el) el.innerHTML = '<div class="loading-spinner">⏳ Загрузка...</div>';
    }

    function hideLoading(containerId) {
        const el = document.getElementById(containerId);
        if (el) {
            const spinner = el.querySelector('.loading-spinner');
            if (spinner) spinner.remove();
        }
    }

    // Character creation card selection
    function initCardSelectors() {
        document.querySelectorAll('.card-grid').forEach(grid => {
            grid.querySelectorAll('.select-card').forEach(card => {
                card.addEventListener('click', () => {
                    grid.querySelectorAll('.select-card').forEach(c => c.classList.remove('selected'));
                    card.classList.add('selected');
                    card.dataset.selected = 'true';
                });
            });
        });
    }

    // Get selected value from card grid
    function getSelectedCard(gridId) {
        const grid = document.getElementById(gridId);
        const selected = grid?.querySelector('.select-card.selected');
        return selected?.dataset.value || null;
    }

    return {
        showToast,
        initTabs,
        showModal,
        hideModal,
        showScreen,
        updateHeaderStats,
        getClassIcon,
        getRaceIcon,
        getRarityClass,
        getRarityName,
        STAT_NAMES,
        renderProfileStats,
        renderItemCard,
        showLoading,
        hideLoading,
        initCardSelectors,
        getSelectedCard,
    };
})();
