<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class CombatModel
{
    private Database $db;

    // Combat formulas
    private const BASE_DAMAGE = 5;
    private const STRENGTH_DAMAGE_MULTIPLIER = 2.0;
    private const DEFENSE_REDUCTION = 0.5;
    private const CRIT_MULTIPLIER = 1.5;
    private const CRIT_CHANCE_PER_LUCK = 0.02; // 2% per luck point
    private const DODGE_CHANCE_PER_AGILITY = 0.015; // 1.5% per agility point
    private const MAX_DODGE_CHANCE = 0.40; // 40% cap
    private const MAX_CRIT_CHANCE = 0.50; // 50% cap
    private const LEVEL_DAMAGE_BONUS = 1.5;
    private const CLASS_ATTACK_MODIFIERS = [
        'warrior' => ['physical' => 1.2, 'magical' => 0.5],
        'mage'    => ['physical' => 0.4, 'magical' => 1.4],
        'rogue'   => ['physical' => 1.0, 'magical' => 0.7],
        'paladin' => ['physical' => 1.1, 'magical' => 0.9],
        'archer'  => ['physical' => 1.15, 'magical' => 0.6],
    ];

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Initialize a PvE battle against a monster
     */
    public function initPvEBattle(int $characterId, int $monsterId): array
    {
        $character = $this->getCharacterWithStats($characterId);
        $monster = $this->db->queryOne("SELECT * FROM monsters WHERE id = ?", [$monsterId]);

        if (!$character) {
            throw new \RuntimeException('Персонаж не найден');
        }
        if (!$monster) {
            throw new \RuntimeException('Монстр не найден');
        }
        if (!$character['is_alive']) {
            throw new \RuntimeException('Персонаж мёртв. Воскресите сначала.');
        }
        if ($character['current_energy'] < 10) {
            throw new \RuntimeException('Недостаточно энергии для боя (нужно 10)');
        }

        // Check if character is already in battle
        $activeBattle = $this->db->queryOne(
            "SELECT b.id FROM battles b
             JOIN battle_participants bp ON bp.battle_id = b.id AND bp.character_id = ?
             WHERE b.status = 'active'",
            [$characterId]
        );
        if ($activeBattle) {
            throw new \RuntimeException('Вы уже в бою!');
        }

        try {
            $this->db->beginTransaction();

            // Create battle
            $battleId = $this->db->insert('battles', [
                'type'      => 'pve',
                'status'    => 'active',
                'turn'      => 0,
                'max_turns' => 30,
                'started_at'=> date('Y-m-d H:i:s'),
                'created_at'=> date('Y-m-d H:i:s'),
            ]);

            // Add character participant
            $charInitiative = $this->calculateInitiative($character, true);
            $this->db->insert('battle_participants', [
                'battle_id'      => $battleId,
                'character_id'   => $characterId,
                'team'           => 'left',
                'current_hp'     => $character['current_hp'],
                'max_hp'         => $character['max_hp'],
                'current_mana'   => $character['current_mana'],
                'max_mana'       => $character['max_mana'],
                'is_alive'       => true,
                'initiative'     => $charInitiative,
                'position'       => 1,
                'is_ai'          => false,
            ]);

            // Add monster participant
            $monsterInitiative = $this->calculateInitiative($monster, false);
            $this->db->insert('battle_participants', [
                'battle_id'      => $battleId,
                'monster_id'     => $monsterId,
                'team'           => 'right',
                'current_hp'     => $monster['hp'],
                'max_hp'         => $monster['hp'],
                'current_mana'   => $monster['mana'] ?? 0,
                'max_mana'       => $monster['mana'] ?? 0,
                'is_alive'       => true,
                'initiative'     => $monsterInitiative,
                'position'       => 1,
                'is_ai'          => true,
            ]);

            // Deduct energy
            $this->db->execute(
                "UPDATE characters SET current_energy = current_energy - 10 WHERE id = ? AND current_energy >= 10",
                [$characterId]
            );

            // Set combat status
            $this->db->execute(
                "UPDATE characters SET in_combat = TRUE WHERE id = ?",
                [$characterId]
            );

            $this->db->commit();

            return [
                'battle_id'    => $battleId,
                'character'    => [
                    'name'       => $character['name'],
                    'hp'         => $character['current_hp'],
                    'max_hp'     => $character['max_hp'],
                    'mana'       => $character['current_mana'],
                    'max_mana'   => $character['max_mana'],
                    'initiative' => $charInitiative,
                ],
                'monster'      => [
                    'name'       => $monster['name'],
                    'hp'         => $monster['hp'],
                    'max_hp'     => $monster['hp'],
                    'level'      => $monster['level'],
                    'icon'       => $monster['icon'] ?? 'default_monster',
                    'initiative' => $monsterInitiative,
                ],
                'first_turn'   => $charInitiative >= $monsterInitiative ? 'player' : 'monster',
                'message'      => "Бой начался! {$character['name']} против {$monster['name']}!",
            ];
        } catch (\Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /**
     * Calculate initiative (determines turn order)
     */
    public function calculateInitiative(array $entity, bool $isCharacter): int
    {
        if ($isCharacter) {
            $agility = $entity['agility'] ?? 10;
            $level = $entity['level'] ?? 1;
            return (int)($agility * 2 + $level * 3 + mt_rand(1, 20));
        } else {
            $agility = $entity['agility'] ?? 5;
            $level = $entity['level'] ?? 1;
            return (int)($agility * 2 + $level * 3 + mt_rand(1, 20));
        }
    }

    /**
     * Process a combat action (attack, defend, skill, item, flee)
     */
    public function processAction(int $battleId, int $participantId, string $action, array $params = []): array
    {
        $battle = $this->db->queryOne("SELECT * FROM battles WHERE id = ? AND status = 'active'", [$battleId]);
        if (!$battle) {
            throw new \RuntimeException('Бой не найден или завершён');
        }

        $attacker = $this->db->queryOne(
            "SELECT * FROM battle_participants WHERE id = ? AND battle_id = ? AND is_alive = TRUE",
            [$participantId, $battleId]
        );
        if (!$attacker) {
            throw new \RuntimeException('Участник не найден или мёртв');
        }

        $turn = (int)$battle['turn'] + 1;

        try {
            $this->db->beginTransaction();

            $logEntries = [];
            $result = [];

            switch ($action) {
                case 'attack':
                    $result = $this->performAttack($battleId, $turn, $attacker, $params);
                    break;
                case 'defend':
                    $result = $this->performDefend($battleId, $turn, $attacker);
                    break;
                case 'skill':
                    $result = $this->performSkill($battleId, $turn, $attacker, $params);
                    break;
                case 'item':
                    $result = $this->performItemUse($battleId, $turn, $attacker, $params);
                    break;
                case 'flee':
                    $result = $this->performFlee($battleId, $turn, $attacker);
                    break;
                default:
                    throw new \RuntimeException('Неизвестное действие: ' . $action);
            }

            $logEntries[] = $result['log'];

            // Process monster turn if still alive and battle continues
            if ($battle['type'] === 'pve' && $result['battle_continues']) {
                $monster = $this->db->queryOne(
                    "SELECT * FROM battle_participants WHERE battle_id = ? AND team = 'right' AND is_alive = TRUE",
                    [$battleId]
                );
                if ($monster) {
                    $monsterAction = $this->chooseMonsterAction($monster);
                    $monsterResult = match ($monsterAction) {
                        'attack' => $this->performAttack($battleId, $turn, $monster, ['target_team' => 'left']),
                        'skill'  => $this->performMonsterSkill($battleId, $turn, $monster),
                        default  => $this->performAttack($battleId, $turn, $monster, ['target_team' => 'left']),
                    };
                    $logEntries[] = $monsterResult['log'];

                    if (!$monsterResult['battle_continues']) {
                        $result['battle_continues'] = false;
                        $result['monster_died'] = true;
                    }
                }
            }

            // Update battle turn
            $this->db->update('battles', [
                'turn' => $turn,
                'updated_at' => date('Y-m-d H:i:s'),
            ], 'id = ?', [$battleId]);

            // Check battle end conditions
            $battleStatus = $this->checkBattleEnd($battleId);

            if ($battleStatus['finished']) {
                $this->endBattle($battleId, $battleStatus);
                $result['battle_finished'] = true;
                $result['rewards'] = $battleStatus['rewards'] ?? null;
            }

            $this->db->commit();

            // Get updated participants state
            $participants = $this->db->query(
                "SELECT * FROM battle_participants WHERE battle_id = ? ORDER BY team, position",
                [$battleId]
            );

            return [
                'success'         => true,
                'battle_id'       => $battleId,
                'turn'            => $turn,
                'action_result'   => $result,
                'log'             => $logEntries,
                'participants'    => $participants,
                'battle_finished' => $battleStatus['finished'] ?? false,
                'rewards'         => $battleStatus['rewards'] ?? null,
                'message'         => $result['message'] ?? '',
            ];

        } catch (\Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /**
     * Perform a basic attack
     */
    private function performAttack(int $battleId, int $turn, array $attacker, array $params): array
    {
        // Find target
        $targetTeam = $params['target_team'] ?? ($attacker['team'] === 'left' ? 'right' : 'left');
        $target = $this->findTarget($battleId, $targetTeam);

        if (!$target) {
            return $this->createLogEntry($battleId, $turn, $attacker, 'attack', null, 0, false, false,
                'Нет целей для атаки!', [], true);
        }

        $isCharacter = $attacker['character_id'] !== null;
        $attackerStats = $isCharacter
            ? $this->getCharacterCombatStats((int)$attacker['character_id'])
            : $this->getMonsterCombatStats((int)$attacker['monster_id'], $attacker);

        $defenderStats = $target['character_id'] !== null
            ? $this->getCharacterCombatStats((int)$target['character_id'])
            : $this->getMonsterCombatStats((int)$target['monster_id'], $target);

        // Check dodge
        $dodgeChance = min($defenderStats['agility'] * self::DODGE_CHANCE_PER_AGILITY, self::MAX_DODGE_CHANCE);
        $isDodge = mt_rand(1, 100) / 100 <= $dodgeChance;

        if ($isDodge) {
            $attackerName = $attackerStats['name'];
            $targetName = $defenderStats['name'];
            return $this->createLogEntry(
                $battleId, $turn, $attacker, 'attack', $target, 0, false, true,
                "{$targetName} уклонился от атаки {$attackerName}!", [], true
            );
        }

        // Calculate damage
        $damage = $this->calculateDamage($attackerStats, $defenderStats, $isCharacter);

        // Check critical hit
        $critChance = min($attackerStats['luck'] * self::CRIT_CHANCE_PER_LUCK, self::MAX_CRIT_CHANCE);
        $isCritical = mt_rand(1, 100) / 100 <= $critChance;

        if ($isCritical) {
            $damage = (int)($damage * self::CRIT_MULTIPLIER);
        }

        // Apply minimum damage
        $damage = max(1, $damage);

        // Apply damage to target
        $newHp = max(0, (int)$target['current_hp'] - $damage);
        $this->db->update('battle_participants', [
            'current_hp' => $newHp,
            'is_alive'   => $newHp > 0,
        ], 'id = ?', [$target['id']]);

        $attackerName = $attackerStats['name'];
        $targetName = $defenderStats['name'];
        $critText = $isCritical ? ' <strong style="color:#ff4444">КРИТИЧЕСКИЙ УДАР!</strong>' : '';

        $message = "{$attackerName} наносит удар по {$targetName} на <strong style=\"color:#ff6600\">{$damage}</strong> урона.{$critText}";
        if ($newHp <= 0) {
            $message .= " <strong style=\"color:#ff0000\">{$targetName} повержен!</strong>";
        }

        $battleContinues = $newHp > 0;

        return $this->createLogEntry(
            $battleId, $turn, $attacker, 'attack', $target, $damage, $isCritical, false,
            $message, ['raw_damage' => $damage, 'crit' => $isCritical, 'dodge' => false],
            $battleContinues
        );
    }

    /**
     * Perform defend action (reduce incoming damage next turn)
     */
    private function performDefend(int $battleId, int $turn, array $attacker): array
    {
        $isCharacter = $attacker['character_id'] !== null;
        $stats = $isCharacter
            ? $this->getCharacterCombatStats((int)$attacker['character_id'])
            : ['name' => 'Монстр'];

        $healAmount = (int)($stats['endurance'] * 0.5 + mt_rand(1, 5));
        $newHp = min((int)$attacker['max_hp'], (int)$attacker['current_hp'] + $healAmount);

        $this->db->update('battle_participants', [
            'current_hp' => $newHp,
        ], 'id = ?', [$attacker['id']]);

        $message = "{$stats['name']} переходит в защиту и восстанавливает <strong style=\"color:#00cc00\">{$healAmount}</strong> HP.";

        return $this->createLogEntry(
            $battleId, $turn, $attacker, 'defend', $attacker, -$healAmount, false, false,
            $message, ['healed' => $healAmount], true
        );
    }

    /**
     * Perform a skill attack
     */
    private function performSkill(int $battleId, int $turn, array $attacker, array $params): array
    {
        $skillId = $params['skill_id'] ?? null;
        if (!$skillId) {
            throw new \RuntimeException('Не указан навык');
        }

        $charId = $attacker['character_id'];
        if (!$charId) {
            throw new \RuntimeException('Только персонажи могут использовать навыки');
        }

        $skill = $this->db->queryOne("SELECT * FROM skills WHERE id = ?", [$skillId]);
        if (!$skill) {
            throw new \RuntimeException('Навык не найден');
        }

        // Check mana cost
        $manaCost = (int)$skill['mana_cost'];
        if ((int)$attacker['current_mana'] < $manaCost) {
            throw new \RuntimeException("Недостаточно маны (нужно {$manaCost})");
        }

        // Deduct mana
        $this->db->update('battle_participants', [
            'current_mana' => (int)$attacker['current_mana'] - $manaCost,
        ], 'id = ?', [$attacker['id']]);

        // Skills use magical damage primarily
        $targetTeam = $attacker['team'] === 'left' ? 'right' : 'left';
        $target = $this->findTarget($battleId, $targetTeam);

        if (!$target) {
            return $this->createLogEntry($battleId, $turn, $attacker, 'skill', null, 0, false, false,
                'Нет целей для навыка!', [], true);
        }

        $stats = $this->getCharacterCombatStats($charId);
        $baseDamage = (int)$skill['base_damage'] + (int)($stats['intelligence'] * 1.5);
        $damage = max(1, $baseDamage + mt_rand(-5, 10));

        $newHp = max(0, (int)$target['current_hp'] - $damage);
        $this->db->update('battle_participants', [
            'current_hp' => $newHp,
            'is_alive'   => $newHp > 0,
        ], 'id = ?', [$target['id']]);

        $message = "{$stats['name']} использует <em style=\"color:#aa66ff\">{$skill['name']}</em>! Наносит <strong style=\"color:#ff6600\">{$damage}</strong> магического урона.";
        if ($newHp <= 0) {
            $targetName = $target['character_id'] ? $this->getCharacterCombatStats((int)$target['character_id'])['name'] : 'Монстр';
            $message .= " <strong style=\"color:#ff0000\">Цель повержена!</strong>";
        }

        // Apply status effect if skill has one
        if (!empty($skill['effect_type'])) {
            // Would apply status effect here
            $message .= " Наложен эффект: {$skill['effect_type']}.";
        }

        return $this->createLogEntry(
            $battleId, $turn, $attacker, 'skill', $target, $damage, false, false,
            $message, ['skill' => $skill['name'], 'mana_cost' => $manaCost],
            $newHp > 0
        );
    }

    /**
     * Perform item use in combat
     */
    private function performItemUse(int $battleId, int $turn, array $attacker, array $params): array
    {
        $inventoryId = $params['inventory_id'] ?? null;
        if (!$inventoryId || !$attacker['character_id']) {
            throw new \RuntimeException('Не указан предмет');
        }

        $invItem = $this->db->queryOne(
            "SELECT inv.*, i.name, i.type, i.stats
             FROM inventory inv JOIN items i ON i.id = inv.item_id
             WHERE inv.id = ? AND inv.character_id = ?",
            [$inventoryId, (int)$attacker['character_id']]
        );

        if (!$invItem) {
            throw new \RuntimeException('Предмет не найден в инвентаре');
        }
        if ($invItem['type'] !== 'consumable') {
            throw new \RuntimeException('В бою можно использовать только расходуемые предметы');
        }

        $stats = $invItem['stats'] ? json_decode($invItem['stats'], true) : [];
        $healHp = (int)($stats['hp_restore'] ?? 0);
        $healMana = (int)($stats['mana_restore'] ?? 0);

        $newHp = min((int)$attacker['max_hp'], (int)$attacker['current_hp'] + $healHp);
        $newMana = min((int)$attacker['max_mana'], (int)$attacker['current_mana'] + $healMana);

        $this->db->update('battle_participants', [
            'current_hp'   => $newHp,
            'current_mana' => $newMana,
        ], 'id = ?', [$attacker['id']]);

        // Consume item (reduce quantity or delete)
        if ((int)$invItem['quantity'] > 1) {
            $this->db->execute(
                "UPDATE inventory SET quantity = quantity - 1 WHERE id = ?",
                [$inventoryId]
            );
        } else {
            $this->db->delete('inventory', 'id = ?', [$inventoryId]);
        }

        $charStats = $this->getCharacterCombatStats((int)$attacker['character_id']);
        $message = "{$charStats['name']} использует <em style=\"color:#66ccff\">{$invItem['name']}</em>.";

        $parts = [];
        if ($healHp > 0) $parts[] = "восстановлено <strong style=\"color:#00cc00\">{$healHp} HP</strong>";
        if ($healMana > 0) $parts[] = "восстановлено <strong style=\"color:#3366ff\">{$healMana} маны</strong>";

        if ($parts) $message .= ' ' . implode(', ', $parts) . '.';

        return $this->createLogEntry(
            $battleId, $turn, $attacker, 'item', $attacker, 0, false, false,
            $message, ['item' => $invItem['name']], true
        );
    }

    /**
     * Attempt to flee from battle
     */
    private function performFlee(int $battleId, int $turn, array $attacker): array
    {
        if ($attacker['character_id'] === null) {
            throw new \RuntimeException('Монстр не может убежать');
        }

        $stats = $this->getCharacterCombatStats((int)$attacker['character_id']);
        $fleeChance = 0.3 + ($stats['agility'] * 0.01);
        $fleeChance = min($fleeChance, 0.8);

        $success = mt_rand(1, 100) / 100 <= $fleeChance;

        if ($success) {
            $message = "{$stats['name']} успешно сбегает из боя!";
            return $this->createLogEntry(
                $battleId, $turn, $attacker, 'flee', null, 0, false, false,
                $message, ['fled' => true], false
            );
        }

        $message = "{$stats['name']} пытается сбежать, но не удаётся!";
        return $this->createLogEntry(
            $battleId, $turn, $attacker, 'flee', null, 0, false, false,
            $message, ['fled' => false], true
        );
    }

    /**
     * AI: Choose monster action
     */
    private function chooseMonsterAction(array $monster): string
    {
        $hpPercent = (int)$monster['current_hp'] / (int)$monster['max_hp'];

        // Low HP - might use special or defend
        if ($hpPercent < 0.3 && mt_rand(1, 100) <= 30) {
            return 'skill';
        }

        // 80% attack, 20% skill
        return mt_rand(1, 100) <= 80 ? 'attack' : 'skill';
    }

    /**
     * Perform monster special skill
     */
    private function performMonsterSkill(int $battleId, int $turn, array $monster): array
    {
        $monsterData = $this->db->queryOne("SELECT * FROM monsters WHERE id = ?", [(int)$monster['monster_id']]);
        $abilities = $monsterData['special_abilities'] ? json_decode($monsterData['special_abilities'], true) : [];

        if (empty($abilities)) {
            return $this->performAttack($battleId, $turn, $monster, ['target_team' => 'left']);
        }

        $ability = $abilities[array_rand($abilities)];
        $damage = (int)($ability['damage'] ?? $monsterData['attack'] * 1.5);
        $damage = max(1, $damage + mt_rand(-3, 5));

        $target = $this->findTarget($battleId, 'left');
        if (!$target) {
            return $this->createLogEntry($battleId, $turn, $monster, 'skill', null, 0, false, false,
                'Нет целей!', [], true);
        }

        $newHp = max(0, (int)$target['current_hp'] - $damage);
        $this->db->update('battle_participants', [
            'current_hp' => $newHp,
            'is_alive'   => $newHp > 0,
        ], 'id = ?', [$target['id']]);

        $targetName = $target['character_id']
            ? $this->getCharacterCombatStats((int)$target['character_id'])['name']
            : 'Цель';

        $message = "{$monsterData['name']} использует <em style=\"color:#ff4444\">{$ability['name']}</em>! Наносит <strong style=\"color:#ff6600\">{$damage}</strong> урона {$targetName}.";

        return $this->createLogEntry(
            $battleId, $turn, $monster, 'skill', $target, $damage, false, false,
            $message, ['ability' => $ability['name'] ?? 'special'], $newHp > 0
        );
    }

    /**
     * Calculate damage based on attacker and defender stats
     */
    private function calculateDamage(array $attacker, array $defender, bool $isMagical = false): int
    {
        $baseDamage = self::BASE_DAMAGE;

        if ($isMagical) {
            $baseDamage += $attacker['intelligence'] * 2;
            $defense = $defender['intelligence'] * self::DEFENSE_REDUCTION;
        } else {
            $baseDamage += $attacker['strength'] * self::STRENGTH_DAMAGE_MULTIPLIER;
            $defense = $defender['endurance'] * self::DEFENSE_REDUCTION;
        }

        // Class modifier
        $class = $attacker['class'] ?? 'warrior';
        $type = $isMagical ? 'magical' : 'physical';
        $classMod = self::CLASS_ATTACK_MODIFIERS[$class][$type] ?? 1.0;
        $baseDamage *= $classMod;

        // Level bonus
        $levelDiff = ($attacker['level'] ?? 1) - ($defender['level'] ?? 1);
        $baseDamage += $levelDiff * self::LEVEL_DAMAGE_BONUS;

        // Random variance (±15%)
        $variance = 0.85 + (mt_rand(0, 30) / 100);
        $baseDamage *= $variance;

        // Apply defense
        $damage = max(1, (int)($baseDamage - $defense));

        return $damage;
    }

    /**
     * Find a random alive target in the given team
     */
    private function findTarget(int $battleId, string $team): ?array
    {
        $targets = $this->db->query(
            "SELECT * FROM battle_participants WHERE battle_id = ? AND team = ? AND is_alive = TRUE ORDER BY RAND() LIMIT 1",
            [$battleId, $team]
        );
        return $targets[0] ?? null;
    }

    /**
     * Check if battle has ended
     */
    private function checkBattleEnd(int $battleId): array
    {
        $participants = $this->db->query(
            "SELECT * FROM battle_participants WHERE battle_id = ?",
            [$battleId]
        );

        $leftAlive = false;
        $rightAlive = false;

        foreach ($participants as $p) {
            if ($p['team'] === 'left') {
                $leftAlive = $leftAlive || (bool)$p['is_alive'];
            } else {
                $rightAlive = $rightAlive || (bool)$p['is_alive'];
            }
        }

        if (!$leftAlive) {
            // Player team lost
            return ['finished' => true, 'winner' => 'right', 'rewards' => null];
        }

        if (!$rightAlive) {
            // Player team won - calculate rewards
            $monster = $this->db->queryOne(
                "SELECT m.* FROM battle_participants bp JOIN monsters m ON m.id = bp.monster_id WHERE bp.battle_id = ? AND bp.team = 'right'",
                [$battleId]
            );

            $rewards = [
                'experience' => $monster ? (int)$monster['experience_reward'] : 0,
                'gold'       => $monster ? (int)$monster['gold_reward'] : 0,
                'loot'       => [],
            ];

            // Generate loot
            if ($monster && $monster['loot_table']) {
                $lootTable = json_decode($monster['loot_table'], true);
                foreach ($lootTable as $loot) {
                    if (mt_rand(1, 100) <= (float)$loot['chance']) {
                        $item = $this->db->queryOne("SELECT * FROM items WHERE id = ?", [(int)$loot['item_id']]);
                        if ($item) {
                            $rewards['loot'][] = [
                                'item_id'   => (int)$item['id'],
                                'name'      => $item['name'],
                                'rarity'    => $item['rarity'],
                                'quantity'  => (int)($loot['quantity'] ?? 1),
                            ];
                        }
                    }
                }
            }

            return ['finished' => true, 'winner' => 'left', 'rewards' => $rewards];
        }

        // Check max turns
        $battle = $this->db->queryOne("SELECT turn, max_turns FROM battles WHERE id = ?", [$battleId]);
        if ((int)$battle['turn'] >= (int)$battle['max_turns']) {
            return ['finished' => true, 'winner' => 'draw', 'rewards' => null];
        }

        return ['finished' => false];
    }

    /**
     * End battle and distribute rewards
     */
    private function endBattle(int $battleId, array $result): void
    {
        $status = $result['winner'] === 'draw' ? 'interrupted' : 'finished';

        $this->db->update('battles', [
            'status'      => $status,
            'finished_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$battleId]);

        // Remove combat status from characters
        $this->db->execute(
            "UPDATE characters c
             JOIN battle_participants bp ON bp.character_id = c.id AND bp.battle_id = ?
             SET c.in_combat = FALSE",
            [$battleId]
        );

        if ($result['winner'] === 'left' && $result['rewards']) {
            $participant = $this->db->queryOne(
                "SELECT character_id FROM battle_participants WHERE battle_id = ? AND team = 'left'",
                [$battleId]
            );
            $characterId = $participant ? (int)$participant['character_id'] : null;

            if ($characterId) {
                $char = $this->db->queryOne("SELECT * FROM characters WHERE id = ?", [$characterId]);

                // Award experience
                $this->db->execute(
                    "UPDATE characters SET experience = experience + ?, gold = gold + ? WHERE id = ?",
                    [$result['rewards']['experience'], $result['rewards']['gold'], $characterId]
                );

                // Update HP/MP from battle state
                $participant = $this->db->queryOne(
                    "SELECT * FROM battle_participants WHERE battle_id = ? AND character_id = ?",
                    [$battleId, $characterId]
                );
                if ($participant) {
                    $this->db->update('characters', [
                        'current_hp'   => (int)$participant['current_hp'],
                        'current_mana' => (int)$participant['current_mana'],
                        'kills'        => (int)$char['kills'] + 1,
                        'updated_at'   => date('Y-m-d H:i:s'),
                    ], 'id = ?', [$characterId]);
                }

                // Add loot to inventory
                foreach ($result['rewards']['loot'] as $lootItem) {
                    $this->db->insert('inventory', [
                        'character_id' => $characterId,
                        'item_id'      => $lootItem['item_id'],
                        'quantity'     => $lootItem['quantity'],
                        'obtained_at'  => date('Y-m-d H:i:s'),
                    ]);
                }

                // Check for level up
                $this->checkLevelUp($characterId);
            }
        } elseif ($result['winner'] === 'right') {
            // Character died
            $participant = $this->db->queryOne(
                "SELECT character_id FROM battle_participants WHERE battle_id = ? AND team = 'left'",
                [$battleId]
            );
            $characterId = $participant ? (int)$participant['character_id'] : null;

            if ($characterId) {
                $this->db->update('characters', [
                    'is_alive'  => false,
                    'deaths'    => $this->db->queryOne("SELECT deaths FROM characters WHERE id = ?", [$characterId])['deaths'] + 1,
                    'updated_at'=> date('Y-m-d H:i:s'),
                ], 'id = ?', [$characterId]);
            }
        }
    }

    /**
     * Check if character should level up
     */
    private function checkLevelUp(int $characterId): void
    {
        while (true) {
            $char = $this->db->queryOne(
                "SELECT c.*, cl.* FROM characters c JOIN classes cl ON cl.name = c.class WHERE c.id = ?",
                [$characterId]
            );

            $requiredExp = $this->getExpForLevel((int)$char['level'] + 1);

            if ((int)$char['experience'] >= $requiredExp) {
                $newLevel = (int)$char['level'] + 1;
                $hpGain = (int)$char['hp_per_level'];
                $manaGain = (int)$char['mana_per_level'];

                $this->db->execute(
                    "UPDATE characters SET level = ?,
                     max_hp = max_hp + ?, max_mana = max_mana + ?,
                     current_hp = max_hp, current_mana = max_mana,
                     updated_at = ?
                     WHERE id = ?",
                    [$newLevel, $hpGain, $manaGain, date('Y-m-d H:i:s'), $characterId]
                );

                // Add stat points
                $this->db->execute(
                    "UPDATE character_stats SET stat_points = stat_points + 3 WHERE character_id = ?",
                    [$characterId]
                );
            } else {
                break;
            }
        }
    }

    /**
     * Get experience needed for a level
     */
    private function getExpForLevel(int $level): int
    {
        return (int)(100 * pow(1.5, $level - 1));
    }

    /**
     * Get character stats for combat calculations
     */
    private function getCharacterCombatStats(int $characterId): array
    {
        $char = $this->db->queryOne(
            "SELECT c.name, c.level, c.class, c.race,
                    cs.strength, cs.agility, cs.endurance, cs.intelligence, cs.luck
             FROM characters c JOIN character_stats cs ON cs.character_id = c.id
             WHERE c.id = ?",
            [$characterId]
        );

        if (!$char) return [];

        // Add equipment bonuses
        $equipBonuses = $this->db->query(
            "SELECT i.stats FROM inventory inv JOIN items i ON i.id = inv.item_id
             WHERE inv.character_id = ? AND inv.is_equipped = TRUE",
            [$characterId]
        );

        foreach ($equipBonuses as $eq) {
            $stats = json_decode($eq['stats'], true);
            if (is_array($stats)) {
                foreach (['strength', 'agility', 'endurance', 'intelligence', 'luck'] as $stat) {
                    if (isset($stats[$stat])) {
                        $char[$stat] = (int)$char[$stat] + (int)$stats[$stat];
                    }
                }
            }
        }

        return $char;
    }

    /**
     * Get monster stats for combat
     */
    private function getMonsterCombatStats(int $monsterId, array $participant = []): array
    {
        $monster = $this->db->queryOne("SELECT * FROM monsters WHERE id = ?", [$monsterId]);
        return [
            'name'        => $monster['name'],
            'level'       => $monster['level'],
            'strength'    => $monster['attack'],
            'agility'     => $monster['agility'],
            'endurance'   => $monster['defense'],
            'intelligence'=> 0,
            'luck'        => 5,
            'class'       => 'warrior',
        ];
    }

    /**
     * Create battle log entry
     */
    private function createLogEntry(
        int $battleId,
        int $turn,
        array $attacker,
        string $action,
        ?array $target,
        int $damage,
        bool $isCritical,
        bool $isDodge,
        string $description,
        array $combatData,
        bool $battleContinues
    ): array {
        $this->db->insert('battle_logs', [
            'battle_id'    => $battleId,
            'turn'         => $turn,
            'actor_type'   => $attacker['character_id'] ? 'character' : 'monster',
            'actor_id'     => $attacker['character_id'] ?? $attacker['monster_id'],
            'action'       => $action,
            'target_type'  => $target ? ($target['character_id'] ? 'character' : 'monster') : null,
            'target_id'    => $target ? ($target['character_id'] ?? $target['monster_id']) : null,
            'damage'       => $damage,
            'is_critical'  => $isCritical,
            'is_dodge'     => $isDodge,
            'description'  => $description,
            'combat_data'  => json_encode($combatData),
            'created_at'   => date('Y-m-d H:i:s'),
        ]);

        return [
            'action'           => $action,
            'damage'           => $damage,
            'is_critical'      => $isCritical,
            'is_dodge'         => $isDodge,
            'message'          => $description,
            'battle_continues' => $battleContinues,
            'log'              => [
                'action'      => $action,
                'damage'      => $damage,
                'is_critical' => $isCritical,
                'is_dodge'    => $isDodge,
                'description' => $description,
            ],
        ];
    }

    /**
     * Get character data with stats
     */
    private function getCharacterWithStats(int $characterId): ?array
    {
        return $this->db->queryOne(
            "SELECT c.*, cs.* FROM characters c JOIN character_stats cs ON cs.character_id = c.id WHERE c.id = ?",
            [$characterId]
        );
    }

    /**
     * Get battle status for polling
     */
    public function getBattleStatus(int $battleId): ?array
    {
        $battle = $this->db->queryOne("SELECT * FROM battles WHERE id = ? AND status = 'active'", [$battleId]);
        if (!$battle) return null;

        $participants = $this->db->query(
            "SELECT bp.*,
                    COALESCE(c.name, m.name) as name,
                    COALESCE(c.icon, m.icon) as icon
             FROM battle_participants bp
             LEFT JOIN characters c ON c.id = bp.character_id
             LEFT JOIN monsters m ON m.id = bp.monster_id
             WHERE bp.battle_id = ?
             ORDER BY bp.team, bp.position",
            [$battleId]
        );

        return [
            'battle'       => $battle,
            'participants' => $participants,
        ];
    }

    /**
     * Get battle log entries
     */
    public function getBattleLog(int $battleId, int $sinceTurn = 0): array
    {
        return $this->db->query(
            "SELECT * FROM battle_logs WHERE battle_id = ? AND turn > ? ORDER BY turn ASC, id ASC",
            [$battleId, $sinceTurn]
        );
    }

    /**
     * Interrupt battle (timeout, disconnect)
     */
    public function interruptBattle(int $battleId): void
    {
        $this->db->update('battles', [
            'status'      => 'interrupted',
            'finished_at' => date('Y-m-d H:i:s'),
        ], 'id = ? AND status = ?', [$battleId, 'active']);

        $this->db->execute(
            "UPDATE characters c
             JOIN battle_participants bp ON bp.character_id = c.id AND bp.battle_id = ?
             SET c.in_combat = FALSE, c.current_hp = bp.current_hp, c.current_mana = bp.current_mana",
            [$battleId]
        );
    }
}
