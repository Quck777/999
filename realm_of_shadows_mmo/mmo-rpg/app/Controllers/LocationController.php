<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\BaseController;
use App\Core\Session;

class LocationController extends BaseController
{
    /**
     * GET /api/location/current - Get current character location
     */
    public function current(array $params): void
    {
        $charId = $this->requireCharacter();

        $char = $this->db->queryOne(
            "SELECT c.location_id, c.in_combat
             FROM characters c WHERE c.id = ?",
            [$charId]
        );

        $location = $this->db->queryOne(
            "SELECT * FROM locations WHERE id = ?",
            [$char['location_id']]
        );

        $connected = [];
        if ($location['connections']) {
            $ids = json_decode($location['connections'], true);
            if (is_array($ids) && !empty($ids)) {
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $connected = $this->db->query(
                    "SELECT id, name, level_requirement, icon, is_safe
                     FROM locations WHERE id IN ({$placeholders})",
                    $ids
                );
            }
        }

        // Get monsters in this location
        $monsters = [];
        if ((bool)$location['has_monsters']) {
            $monsters = $this->db->query(
                "SELECT id, name, level, hp, icon FROM monsters WHERE location_id = ?",
                [$location['id']]
            );
        }

        // Get NPCs
        $npcs = $this->db->query(
            "SELECT id, name, type, icon FROM npcs WHERE location_id = ?",
            [$location['id']]
        );

        // Get other players in location
        $players = $this->db->query(
            "SELECT c.id, c.name, c.level, c.race, c.class, g.name as guild_name
             FROM characters c
             LEFT JOIN guild_members gm ON gm.character_id = c.id
             LEFT JOIN guilds g ON g.id = gm.guild_id
             WHERE c.location_id = ? AND c.id != ? AND c.is_alive = TRUE AND c.in_combat = FALSE
             ORDER BY c.level DESC",
            [$location['id'], $charId]
        );

        $this->jsonSuccess([
            'location'   => $location,
            'connected'  => $connected,
            'monsters'   => $monsters,
            'npcs'       => $npcs,
            'players'    => $players,
            'in_combat'  => (bool)$char['in_combat'],
        ]);
    }

    /**
     * GET /api/location/{id} - View a specific location
     */
    public function view(array $params): void
    {
        $id = (int)($params['id'] ?? 0);
        if ($id <= 0) {
            $this->jsonError('Некорректный ID локации');
        }

        $location = $this->db->queryOne("SELECT * FROM locations WHERE id = ?", [$id]);
        if (!$location) {
            $this->jsonError('Локация не найдена', 404);
        }

        $this->jsonSuccess(['location' => $location]);
    }

    /**
     * POST /api/location/travel - Travel to a connected location
     */
    public function travel(array $params): void
    {
        $charId = $this->requireCharacter();
        $this->requireCsrf();

        $input = getInput();
        $targetLocationId = (int)($input['location_id'] ?? 0);

        if ($targetLocationId <= 0) {
            $this->jsonError('Укажите локацию');
        }

        // Check character state
        $char = $this->db->queryOne(
            "SELECT location_id, in_combat, is_alive FROM characters WHERE id = ?",
            [$charId]
        );

        if (!$char['is_alive']) {
            $this->jsonError('Мёртвые не могут путешествовать');
        }
        if ($char['in_combat']) {
            $this->jsonError('Нельзя перемещаться во время боя');
        }

        // Check if target is connected
        $currentLocation = $this->db->queryOne(
            "SELECT connections FROM locations WHERE id = ?",
            [$char['location_id']]
        );

        $connections = json_decode($currentLocation['connections'] ?? '[]', true);
        if (!in_array($targetLocationId, $connections)) {
            $this->jsonError('Нельзя переместиться в эту локацию (нет пути)');
        }

        // Check level requirement
        $target = $this->db->queryOne("SELECT * FROM locations WHERE id = ?", [$targetLocationId]);
        if ((int)$target['level_requirement'] > (int)$this->getCharacterData($charId)['level']) {
            $this->jsonError("Требуется уровень {$target['level_requirement']}");
        }

        $this->db->update('characters', [
            'location_id' => $targetLocationId,
            'updated_at'  => date('Y-m-d H:i:s'),
        ], 'id = ?', [$charId]);

        $this->jsonSuccess([
            'new_location' => $target,
        ], "Вы переместились в «{$target['name']}»");
    }
}
