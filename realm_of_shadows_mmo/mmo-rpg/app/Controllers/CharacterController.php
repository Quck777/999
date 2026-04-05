<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\BaseController;
use App\Core\Session;

class CharacterController extends BaseController
{
    public function index(array $params): void
    {
        $userId = $this->requireAuth();

        $characters = $this->db->query(
            "SELECT c.*, cs.strength, cs.agility, cs.endurance, cs.intelligence, cs.luck, cs.stat_points,
                    l.name as location_name
             FROM characters c
             JOIN character_stats cs ON cs.character_id = c.id
             LEFT JOIN locations l ON l.id = c.location_id
             WHERE c.user_id = ?
             ORDER BY c.level DESC, c.created_at DESC",
            [$userId]
        );

        $this->jsonSuccess(['characters' => $characters]);
    }

    public function create(array $params): void
    {
        $this->requireAuth();
        $this->requireCsrf();

        $input = getInput();
        $errors = validateRequired($input, [
            'name'  => 'Имя персонажа',
            'race'  => 'Раса',
            'class' => 'Класс',
        ]);

        if (!empty($errors)) {
            $this->jsonError('Заполните все поля', 422, $errors);
        }

        $userId = Session::getUserId();
        $name = sanitize($input['name']);
        $race = $input['race'];
        $class = $input['class'];

        $validRaces = ['human', 'elf', 'dwarf', 'orc', 'undead'];
        $validClasses = ['warrior', 'mage', 'rogue', 'paladin', 'archer'];

        if (!in_array($race, $validRaces, true)) {
            $this->jsonError('Некорректная раса', 422, ['race' => 'Выберите допустимую расу']);
        }
        if (!in_array($class, $validClasses, true)) {
            $this->jsonError('Некорректный класс', 422, ['class' => 'Выберите допустимый класс']);
        }

        if (mb_strlen($name) < 2 || mb_strlen($name) > 30) {
            $this->jsonError('', 422, ['name' => 'Имя должно быть от 2 до 30 символов']);
        }

        // Check max characters per user (e.g., 3)
        $count = $this->db->queryOne(
            "SELECT COUNT(*) as cnt FROM characters WHERE user_id = ?",
            [$userId]
        );
        if ((int)$count['cnt'] >= 3) {
            $this->jsonError('Максимум 3 персонажа на аккаунте');
        }

        // Check name uniqueness
        $exists = $this->db->queryOne("SELECT id FROM characters WHERE name = ?", [$name]);
        if ($exists) {
            $this->jsonError('Это имя уже занято', 409, ['name' => 'Выберите другое имя']);
        }

        // Get race and class base stats
        $raceData = $this->db->queryOne("SELECT * FROM races WHERE name = ?", [$race]);
        $classData = $this->db->queryOne("SELECT * FROM classes WHERE name = ?", [$class]);

        if (!$raceData || !$classData) {
            $this->jsonError('Данные расы или класса не найдены');
        }

        $startingLocation = $this->db->queryOne(
            "SELECT id FROM locations WHERE level_requirement <= 1 AND is_safe = TRUE LIMIT 1"
        );

        try {
            $this->db->beginTransaction();

            $baseHp = 100 + (int)$raceData['hp_bonus'] + (int)($classData['hp_per_level'] ?? 0);
            $baseMana = 50 + (int)($classData['mana_per_level'] ?? 0) * 2;

            $charId = $this->db->insert('characters', [
                'user_id'       => $userId,
                'name'          => $name,
                'race'          => $race,
                'class'         => $class,
                'level'         => 1,
                'experience'    => 0,
                'current_hp'    => $baseHp,
                'max_hp'        => $baseHp,
                'current_mana'  => $baseMana,
                'max_mana'      => $baseMana,
                'current_energy'=> 100,
                'max_energy'    => 100,
                'gold'          => 100,
                'rating'        => 0,
                'location_id'   => (int)$startingLocation['id'],
                'is_alive'      => true,
                'created_at'    => date('Y-m-d H:i:s'),
                'updated_at'    => date('Y-m-d H:i:s'),
            ]);

            // Create character stats
            $this->db->insert('character_stats', [
                'character_id' => $charId,
                'strength'     => (int)$raceData['base_strength'],
                'agility'      => (int)$raceData['base_agility'],
                'endurance'    => (int)$raceData['base_endurance'],
                'intelligence' => (int)$raceData['base_intelligence'],
                'luck'         => (int)$raceData['base_luck'],
                'stat_points'  => 5, // 5 free stat points to distribute
            ]);

            // Give starter equipment
            $this->giveStarterEquipment($charId, $class);

            $this->db->commit();

            Session::setCharacterId($charId);
            $this->jsonSuccess(
                ['character_id' => $charId],
                'Персонаж «' . $name . '» создан!',
                201
            );
        } catch (\Throwable $e) {
            $this->db->rollback();
            $this->jsonError('Ошибка при создании персонажа', 500);
        }
    }

    private function giveStarterEquipment(int $characterId, string $class): void
    {
        $starterItems = [
            'warrior' => ['Ржавый меч', 'Кожаная броня'],
            'mage'    => ['Посох новичка', 'Роба мага'],
            'rogue'   => ['Кинжал', 'Лёгкий плащ'],
            'paladin' => ['Деревянный молот', 'Кольчуга новичка'],
            'archer'  => ['Короткий лук', 'Лёгкая куртка'],
        ];

        $items = $starterItems[$class] ?? $starterItems['warrior'];

        foreach ($items as $itemName) {
            $item = $this->db->queryOne(
                "SELECT id FROM items WHERE name = ? LIMIT 1",
                [$itemName]
            );
            if ($item) {
                $this->db->insert('inventory', [
                    'character_id' => $characterId,
                    'item_id'      => (int)$item['id'],
                    'quantity'     => 1,
                    'is_equipped'  => false,
                    'obtained_at'  => date('Y-m-d H:i:s'),
                ]);
            }
        }
    }

    public function stats(array $params): void
    {
        $charId = $this->requireCharacter();
        $data = $this->getCharacterData($charId);
        $this->jsonSuccess(['character' => $data]);
    }

    public function upgradeStat(array $params): void
    {
        $this->requireAuth();
        $this->requireCsrf();

        $charId = $this->requireCharacter();
        $input = getInput();

        $allowedStats = ['strength', 'agility', 'endurance', 'intelligence', 'luck'];
        $stat = $input['stat'] ?? '';

        if (!in_array($stat, $allowedStats, true)) {
            $this->jsonError('Некорректная характеристика');
        }

        $char = $this->getCharacterData($charId);
        if (!$char) {
            $this->jsonError('Персонаж не найден');
        }
        if ((int)$char['stat_points'] <= 0) {
            $this->jsonError('Нет свободных очков характеристик');
        }

        try {
            $this->db->beginTransaction();

            $this->db->execute(
                "UPDATE character_stats SET `{$stat}` = `{$stat}` + 1, stat_points = stat_points - 1 WHERE character_id = ? AND stat_points > 0",
                [$charId]
            );

            // If endurance upgraded, increase max HP
            if ($stat === 'endurance') {
                $this->db->execute(
                    "UPDATE characters SET max_hp = max_hp + 5, current_hp = LEAST(current_hp + 5, max_hp + 5) WHERE id = ?",
                    [$charId]
                );
            }

            // If intelligence upgraded, increase max mana
            if ($stat === 'intelligence') {
                $this->db->execute(
                    "UPDATE characters SET max_mana = max_mana + 3, current_mana = LEAST(current_mana + 3, max_mana + 3) WHERE id = ?",
                    [$charId]
                );
            }

            $this->db->commit();

            $updated = $this->getCharacterData($charId);
            $this->jsonSuccess($updated, "Характеристика «{$stat}» улучшена!");
        } catch (\Throwable $e) {
            $this->db->rollback();
            $this->jsonError('Ошибка при улучшении');
        }
    }

    public function view(array $params): void
    {
        $id = (int)($params['id'] ?? 0);
        if ($id <= 0) {
            $this->jsonError('Некорректный ID персонажа');
        }

        $char = $this->db->queryOne(
            "SELECT c.id, c.name, c.race, c.class, c.level, c.rating, c.gold, c.kills, c.deaths,
                    cs.strength, cs.agility, cs.endurance, cs.intelligence, cs.luck,
                    g.name as guild_name
             FROM characters c
             JOIN character_stats cs ON cs.character_id = c.id
             LEFT JOIN guild_members gm ON gm.character_id = c.id
             LEFT JOIN guilds g ON g.id = gm.guild_id
             WHERE c.id = ?",
            [$id]
        );

        if (!$char) {
            $this->jsonError('Персонаж не найден', 404);
        }

        // Equipment
        $equipment = $this->db->query(
            "SELECT i.name, i.icon, i.rarity, i.stats, inv.equipped_slot
             FROM inventory inv
             JOIN items i ON i.id = inv.item_id
             WHERE inv.character_id = ? AND inv.is_equipped = TRUE",
            [$id]
        );

        $this->jsonSuccess(['character' => $char, 'equipment' => $equipment]);
    }
}
