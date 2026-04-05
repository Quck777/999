/**
 * Combat Module — Управление боевой системой
 * Поддерживает демо-бои (single-player) и серверные бои
 */
const Combat = (() => {
    let isPlayerTurn = false;
    let demoMonster = null;
    let demoPlayer = null;
    let demoTurn = 0;
    let isDefending = false;

    function init() {
        document.querySelectorAll('.btn-combat').forEach(btn => {
            btn.addEventListener('click', () => {
                const action = btn.dataset.action;
                if (action && isPlayerTurn) performDemoAction(action);
            });
        });
        document.getElementById('combat-continue')?.addEventListener('click', exitCombat);
    }

    // =============================================
    //  DEMO COMBAT — Полноценный single-player бой
    // =============================================

    function startDemoBattle(player, monster) {
        demoPlayer = {...player};
        demoMonster = {...monster, current_hp: monster.hp, current_mana: 0, max_hp: monster.hp};
        demoTurn = 0;
        isPlayerTurn = true;
        isDefending = false;

        document.getElementById('combat-idle').classList.add('hidden');
        document.getElementById('combat-active').classList.remove('hidden');
        document.getElementById('combat-rewards').classList.add('hidden');
        document.getElementById('combat-actions').classList.remove('hidden');

        updateDemoCombatUI();
        addDemoLog(`Бой начался! ${demoPlayer.name} против ${demoMonster.name}!`);

        // Монстр ходит первым если выше ловкость
        if (demoMonster.agility >= (demoPlayer.agility || 10)) {
            isPlayerTurn = false;
            setActionsEnabled(false);
            addDemoLog(`${demoMonster.name} быстрее и атакует первым!`);
            setTimeout(() => { monsterDemoTurn(); }, 1000);
        }
    }

    function performDemoAction(action) {
        if (!isPlayerTurn || !demoPlayer || !demoMonster) return;
        demoTurn++;
        isPlayerTurn = false;
        setActionsEnabled(false);
        isDefending = false;

        const p = demoPlayer;
        const m = demoMonster;

        switch (action) {
            case 'attack': {
                // Проверка уклонения
                const dodgeChance = Math.min(m.agility * 0.015, 0.4);
                if (Math.random() < dodgeChance) {
                    addDemoLog(`${m.name} уклонился от атаки!`);
                    break;
                }
                const baseDmg = 5 + p.strength * 2 + p.level * 1.5;
                const defense = m.defense * 0.5;
                const variance = 0.85 + Math.random() * 0.3;
                let dmg = Math.max(1, Math.floor((baseDmg - defense) * variance));

                // Критический удар
                const critChance = Math.min(p.luck * 0.02, 0.5);
                const isCrit = Math.random() < critChance;
                if (isCrit) dmg = Math.floor(dmg * 1.5);

                m.current_hp = Math.max(0, m.current_hp - dmg);
                const critTxt = isCrit ? ' <strong style="color:#ff4444">КРИТ!</strong>' : '';
                addDemoLog(`${p.name} атакует ${m.name} на <strong style="color:#ff6600">${dmg}</strong> урона.${critTxt}`);
                break;
            }
            case 'defend': {
                isDefending = true;
                const heal = Math.floor(p.endurance * 0.5 + Math.random() * 5 + 1);
                p.current_hp = Math.min(p.max_hp, p.current_hp + heal);
                addDemoLog(`${p.name} защищается и восстанавливает <strong style="color:#00cc00">${heal}</strong> HP`);
                break;
            }
            case 'skill': {
                if (p.current_mana < 15) {
                    addDemoLog(`Недостаточно маны для навыка! (нужно 15)`);
                    isPlayerTurn = true;
                    setActionsEnabled(true);
                    return;
                }
                p.current_mana -= 15;
                const skillDmg = Math.max(1, Math.floor(p.intelligence * 1.5 + 10 + Math.random() * 10));
                m.current_hp = Math.max(0, m.current_hp - skillDmg);
                addDemoLog(`${p.name} использует <em style="color:#aa66ff">Магический удар</em>! Наносит <strong style="color:#ff6600">${skillDmg}</strong> урона.`);
                break;
            }
            case 'item': {
                const potions = App.demoInventory().filter(i => i.type === 'consumable');
                if (potions.length === 0) {
                    addDemoLog(`Нет зелий в инвентаре!`);
                    isPlayerTurn = true;
                    setActionsEnabled(true);
                    return;
                }
                const potion = potions[0];
                const idx = App.demoInventory().indexOf(potion);
                const stats = potion.stats || {};
                let used = false;

                if (stats.hp_restore) {
                    p.current_hp = Math.min(p.max_hp, p.current_hp + stats.hp_restore);
                    addDemoLog(`${p.name} использует ${potion.name}: +${stats.hp_restore} HP`);
                    used = true;
                }
                if (stats.mana_restore) {
                    p.current_mana = Math.min(p.max_mana, p.current_mana + stats.mana_restore);
                    addDemoLog(`${p.name} использует ${potion.name}: +${stats.mana_restore} MP`);
                    used = true;
                }

                if (used && idx >= 0) {
                    // Расходуем зелье
                    const inv = App.demoInventory();
                    potion.quantity = (potion.quantity || 1) - 1;
                    if (potion.quantity <= 0) {
                        inv.splice(idx, 1);
                    }
                    App.updateDemoUI();
                }
                break;
            }
            case 'flee': {
                const fleeChance = 0.3 + p.agility * 0.01;
                if (Math.random() < Math.min(fleeChance, 0.8)) {
                    addDemoLog(`${p.name} успешно сбегает!`);
                    exitCombat();
                    return;
                }
                addDemoLog(`${p.name} пытается сбежать, но не получается!`);
                break;
            }
        }

        updateDemoCombatUI();

        // Проверка смерти монстра
        if (m.current_hp <= 0) {
            addDemoLog(`${m.name} повержен!`);
            App.addDemoWin && App.addDemoWin();
            setTimeout(() => showDemoRewards(), 800);
            return;
        }

        // Ход монстра с задержкой
        setTimeout(() => monsterDemoTurn(), 800 + Math.random() * 400);
    }

    function monsterDemoTurn() {
        if (!demoMonster || !demoPlayer || demoMonster.current_hp <= 0) return;

        const m = demoMonster;
        const p = demoPlayer;

        // ИИ монстра: умное поведение в зависимости от HP
        let monsterAction = 'attack';
        const monsterHpPct = m.current_hp / m.hp;

        // Мало HP: чаще использует спец.атаку
        if (monsterHpPct < 0.3 && m.attack > 15 && Math.random() < 0.5) {
            monsterAction = 'skill';
        }
        // Игрок защищается: шанс тяжёлой атаки
        else if (isDefending && Math.random() < 0.3) {
            monsterAction = 'skill';
        }
        // Обычный: 80% атака, 20% навык
        else if (Math.random() < 0.2 && m.attack > 15) {
            monsterAction = 'skill';
        }

        let dmg;

        if (monsterAction === 'skill') {
            dmg = Math.max(1, Math.floor(m.attack * 1.5 + Math.random() * 8));
            const skillName = m.name === 'Тёмный маг' ? 'Тёмная магия' : m.name === 'Дракончик' ? 'Огненное дыхание' : 'Особый удар';
            addDemoLog(`${m.name} использует <em style="color:#ff4444">${skillName}</em>! Наносит <strong style="color:#ff6600">${dmg}</strong> урона.`);
        } else {
            // Проверка уклонения игрока
            const dodgeChance = Math.min(p.agility * 0.015, 0.4);
            if (Math.random() < dodgeChance) {
                addDemoLog(`${p.name} уклонился от атаки ${m.name}!`);
                isPlayerTurn = true;
                setActionsEnabled(true);
                return;
            }
            dmg = Math.max(1, Math.floor(m.attack * (0.8 + Math.random() * 0.4) - p.endurance * 0.3));
            // Урон уменьшается при защите
            if (isDefending) {
                dmg = Math.max(1, Math.floor(dmg * 0.5));
                addDemoLog(`${p.name} блокирует часть урона!`);
            }
            addDemoLog(`${m.name} атакует ${p.name} на <strong style="color:#ff6600">${dmg}</strong> урона.`);
        }

        p.current_hp = Math.max(0, p.current_hp - dmg);
        updateDemoCombatUI();

        // Проверка смерти игрока
        if (p.current_hp <= 0) {
            addDemoLog(`${p.name} погиб! Бой проигран...`);
            App.addDemoLoss && App.addDemoLoss();
            // Воскрешение через 2 секунды
            setTimeout(() => {
                p.current_hp = Math.floor(p.max_hp * 0.3);
                p.current_mana = Math.floor(p.max_mana * 0.3);
                addDemoLog(`${p.name} воскрешён в городе с 30% HP/MP`);
                App.setDemoLocation && App.setDemoLocation(0); // Возврат в безопасную зону
                exitCombat();
                App.updateDemoUI && App.updateDemoUI();
                App.loadDemoProfile && App.loadDemoProfile();
            }, 2000);
            return;
        }

        isPlayerTurn = true;
        setActionsEnabled(true);
    }

    function showDemoRewards() {
        const m = demoMonster;
        const rewards = { exp: m.exp, gold: m.gold, loot: [] };

        // Выброс лута
        (m.loot || []).forEach(item => {
            if (Math.random() * 100 < item.chance) {
                rewards.loot.push({ name: item.name, rarity: item.rarity });
            }
        });

        let html = '';
        html += `<div class="reward-item">Опыт: +${rewards.exp}</div>`;
        html += `<div class="reward-item">Золото: +${rewards.gold}</div>`;
        if (rewards.loot.length > 0) {
            html += '<div class="reward-item" style="margin-top:0.5rem">Добыча:</div>';
            rewards.loot.forEach(item => {
                html += `<div class="reward-loot ${UI.getRarityClass(item.rarity)}">${item.name}</div>`;
            });
        } else {
            html += '<div class="reward-item" style="color:var(--text-muted)">Добыча: ничего</div>';
        }

        document.getElementById('combat-actions').classList.add('hidden');
        document.getElementById('combat-rewards').classList.remove('hidden');
        document.getElementById('combat-rewards-content').innerHTML = html;

        // Применение наград к персонажу
        const p = App.currentCharacter();
        if (p) {
            p.experience += rewards.exp;
            p.gold += rewards.gold;

            // Проверка повышения уровня
            let leveled = false;
            while (p.experience >= p.expToLevel) {
                p.experience -= p.expToLevel;
                p.level++;
                p.expToLevel = Math.floor(100 * Math.pow(1.5, p.level - 1));
                p.max_hp += 12;
                p.max_mana += 8;
                p.max_energy += 5;
                p.current_hp = p.max_hp;
                p.current_mana = p.max_mana;
                p.stat_points += 3;
                addDemoLog(`УРОВЕНЬ ${p.level}! +3 очка характеристик!`);
                UI.showToast(`Уровень ${p.level}! +3 очка характеристик!`, 'success', 5000);
                leveled = true;
            }

            // Добавление лута в инвентарь
            rewards.loot.forEach(item => {
                App.addDemoItem({
                    id: Date.now() + Math.random(), name: item.name, type: 'material',
                    icon: '📦', rarity: item.rarity, stats: {}, quantity: 1, equipped: false
                });
            });

            App.updateDemoUI && App.updateDemoUI();
            App.loadDemoInventory && App.loadDemoInventory();
            if (leveled) App.loadDemoProfile && App.loadDemoProfile();
        }

        document.getElementById('combat-continue').onclick = () => {
            exitCombat();
        };
    }

    function updateDemoCombatUI() {
        if (!demoPlayer || !demoMonster) return;
        const p = demoPlayer;
        const m = demoMonster;

        document.getElementById('combat-player-name').textContent = p.name;
        const pHpPct = Math.max(0, (p.current_hp / p.max_hp * 100)).toFixed(0);
        document.getElementById('combat-player-hp-bar').style.width = pHpPct + '%';
        document.getElementById('combat-player-hp-text').textContent = `${p.current_hp}/${p.max_hp} HP`;
        const pManaPct = Math.max(0, (p.current_mana / p.max_mana * 100)).toFixed(0);
        document.getElementById('combat-player-mana-bar').style.width = pManaPct + '%';
        document.getElementById('combat-player-mana-text').textContent = `${p.current_mana}/${p.max_mana} MP`;

        document.getElementById('combat-enemy-name').textContent = m.icon + ' ' + m.name;
        const mHpPct = Math.max(0, (m.current_hp / m.hp * 100)).toFixed(0);
        document.getElementById('combat-enemy-hp-bar').style.width = mHpPct + '%';
        document.getElementById('combat-enemy-hp-text').textContent = `${m.current_hp}/${m.hp} HP`;
        document.getElementById('combat-enemy-mana-bar').style.width = '0%';
        document.getElementById('combat-enemy-mana-text').textContent = '';
    }

    function addDemoLog(msg) {
        const log = document.getElementById('combat-log');
        const entry = document.createElement('div');
        entry.className = 'log-entry';
        entry.innerHTML = msg;
        log.appendChild(entry);
        log.scrollTop = log.scrollHeight;
    }

    function setActionsEnabled(enabled) {
        document.querySelectorAll('.btn-combat').forEach(btn => {
            btn.disabled = !enabled;
            btn.classList.toggle('disabled', !enabled);
        });
    }

    function exitCombat() {
        demoMonster = null;
        demoPlayer = null;
        isPlayerTurn = false;
        isDefending = false;
        setActionsEnabled(true);

        document.getElementById('combat-idle').classList.remove('hidden');
        document.getElementById('combat-active').classList.add('hidden');
        document.getElementById('combat-log').innerHTML = '';

        if (App.isDemo()) App.loadDemoCombatList();
    }

    // =============================================
    //  SERVER COMBAT (API mode)
    // =============================================

    async function loadCombat() {
        try {
            const locData = await API.location.current();
            if (locData.data?.monsters?.length > 0) {
                const container = document.getElementById('combat-monster-list');
                container.innerHTML = locData.data.monsters.map(m => `
                    <div class="monster-card"><div class="monster-icon">${m.icon||'👹'}</div><div class="monster-info">
                    <div class="monster-name">${m.name}</div><div class="monster-level">Уровень ${m.level}</div>
                    <div class="monster-hp">HP ${m.hp}</div></div>
                    <button class="btn btn-primary btn-sm combat-start-btn" data-monster-id="${m.id}">Атаковать</button></div>
                `).join('');
                container.querySelectorAll('.combat-start-btn').forEach(btn => {
                    btn.addEventListener('click', async () => {
                        try { const r = await API.combat.start(parseInt(btn.dataset.monsterId)); UI.showToast(r.message,'success'); }
                        catch(err){ UI.showToast(err.message,'error'); }
                    });
                });
            }
            const statusData = await API.combat.status();
            if (statusData.data?.in_battle) enterBattle(statusData.data.battle);
        } catch (err) { UI.showToast(err.message, 'error'); }
    }

    function enterBattle(battleData) {
        document.getElementById('combat-idle').classList.add('hidden');
        document.getElementById('combat-active').classList.remove('hidden');
        document.getElementById('combat-rewards').classList.add('hidden');
        document.getElementById('combat-actions').classList.remove('hidden');
        addDemoLog('Бой начался!');
        isPlayerTurn = true;
    }

    return { init, loadCombat, startDemoBattle, exitCombat, performDemoAction, isPlayerTurn: () => isPlayerTurn };
})();
