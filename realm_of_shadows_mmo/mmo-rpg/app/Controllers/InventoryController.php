<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\BaseController;
use App\Core\Session;

class InventoryController extends BaseController
{
    /**
     * GET /api/inventory - List character inventory
     */
    public function index(array $params): void
    {
        $charId = $this->requireCharacter();

        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = min(50, (int)($_GET['per_page'] ?? 20));
        $offset = ($page - 1) * $perPage;

        $items = $this->db->query(
            "SELECT inv.id as inventory_id, inv.item_id, inv.quantity, inv.is_equipped, inv.equipped_slot,
                    i.name, i.type, i.rarity, i.icon, i.stats, i.level_requirement, i.price, i.sell_price,
                    i.description
             FROM inventory inv
             JOIN items i ON i.id = inv.item_id
             WHERE inv.character_id = ?
             ORDER BY inv.is_equipped DESC, inv.equipped_slot ASC, inv.obtained_at DESC
             LIMIT ? OFFSET ?",
            [$charId, $perPage, $offset]
        );

        $total = $this->db->queryOne(
            "SELECT COUNT(*) as cnt FROM inventory WHERE character_id = ?",
            [$charId]
        );

        $this->jsonSuccess([
            'items'     => $items,
            'total'     => (int)$total['cnt'],
            'page'      => $page,
            'per_page'  => $perPage,
            'last_page' => (int)ceil((int)$total['cnt'] / $perPage),
        ]);
    }

    /**
     * POST /api/inventory/equip - Equip an item
     */
    public function equip(array $params): void
    {
        $charId = $this->requireCharacter();
        $this->requireCsrf();

        $input = getInput();
        $inventoryId = (int)($input['inventory_id'] ?? 0);

        if ($inventoryId <= 0) {
            $this->jsonError('Укажите предмет');
        }

        // Get item
        $invItem = $this->db->queryOne(
            "SELECT inv.*, i.type, i.slot, i.rarity, i.level_requirement, i.stats
             FROM inventory inv JOIN items i ON i.id = inv.item_id
             WHERE inv.id = ? AND inv.character_id = ?",
            [$inventoryId, $charId]
        );

        if (!$invItem) {
            $this->jsonError('Предмет не найден');
        }

        if ($invItem['type'] === 'consumable' || $invItem['type'] === 'material') {
            $this->jsonError('Этот предмет нельзя экипировать');
        }

        // Check level requirement
        $char = $this->getCharacterData($charId);
        if ((int)$invItem['level_requirement'] > (int)$char['level']) {
            $this->jsonError("Требуется уровень {$invItem['level_requirement']}");
        }

        $slot = $invItem['slot'] ?? $invItem['type'];
        if (!$slot) {
            $this->jsonError('У предмета нет слота экипировки');
        }

        try {
            $this->db->beginTransaction();

            // Unequip current item in this slot
            $this->db->execute(
                "UPDATE inventory SET is_equipped = FALSE, equipped_slot = NULL
                 WHERE character_id = ? AND equipped_slot = ? AND is_equipped = TRUE",
                [$charId, $slot]
            );

            // Equip new item
            $this->db->update('inventory', [
                'is_equipped'   => true,
                'equipped_slot' => $slot,
            ], 'id = ?', [$inventoryId]);

            // Recalculate character stats
            $this->recalculateCharacterStats($charId);

            $this->db->commit();

            $this->jsonSuccess([], "Предмет «{$invItem['name']}» экипирован!");
        } catch (\Throwable $e) {
            $this->db->rollback();
            $this->jsonError('Ошибка при экипировке');
        }
    }

    /**
     * POST /api/inventory/unequip - Unequip an item
     */
    public function unequip(array $params): void
    {
        $charId = $this->requireCharacter();
        $this->requireCsrf();

        $input = getInput();
        $inventoryId = (int)($input['inventory_id'] ?? 0);

        if ($inventoryId <= 0) {
            $this->jsonError('Укажите предмет');
        }

        $invItem = $this->db->queryOne(
            "SELECT inv.*, i.name FROM inventory inv JOIN items i ON i.id = inv.item_id
             WHERE inv.id = ? AND inv.character_id = ? AND inv.is_equipped = TRUE",
            [$inventoryId, $charId]
        );

        if (!$invItem) {
            $this->jsonError('Предмет не найден или не экипирован');
        }

        try {
            $this->db->beginTransaction();

            $this->db->update('inventory', [
                'is_equipped'   => false,
                'equipped_slot' => null,
            ], 'id = ?', [$inventoryId]);

            $this->recalculateCharacterStats($charId);

            $this->db->commit();

            $this->jsonSuccess([], "Предмет «{$invItem['name']}» снят");
        } catch (\Throwable $e) {
            $this->db->rollback();
            $this->jsonError('Ошибка');
        }
    }

    /**
     * POST /api/inventory/use - Use a consumable item
     */
    public function useItem(array $params): void
    {
        $charId = $this->requireCharacter();
        $this->requireCsrf();

        $input = getInput();
        $inventoryId = (int)($input['inventory_id'] ?? 0);

        $invItem = $this->db->queryOne(
            "SELECT inv.*, i.name, i.type, i.stats FROM inventory inv JOIN items i ON i.id = inv.item_id
             WHERE inv.id = ? AND inv.character_id = ?",
            [$inventoryId, $charId]
        );

        if (!$invItem) {
            $this->jsonError('Предмет не найден');
        }

        if ($invItem['type'] !== 'consumable') {
            $this->jsonError('Можно использовать только расходуемые предметы');
        }

        $stats = $invItem['stats'] ? json_decode($invItem['stats'], true) : [];
        $hpRestore = (int)($stats['hp_restore'] ?? 0);
        $manaRestore = (int)($stats['mana_restore'] ?? 0);

        if ($hpRestore === 0 && $manaRestore === 0) {
            $this->jsonError('Предмет не имеет эффекта');
        }

        try {
            $this->db->beginTransaction();

            $sets = [];
            $setParams = [];

            if ($hpRestore > 0) {
                $sets[] = "current_hp = LEAST(max_hp, current_hp + ?)";
                $setParams[] = $hpRestore;
            }
            if ($manaRestore > 0) {
                $sets[] = "current_mana = LEAST(max_mana, current_mana + ?)";
                $setParams[] = $manaRestore;
            }

            $sets[] = "updated_at = ?";
            $setParams[] = date('Y-m-d H:i:s');

            if ($sets) {
                $sql = "UPDATE characters SET " . implode(', ', $sets) . " WHERE id = ?";
                $setParams[] = $charId;
                $this->db->execute($sql, $setParams);
            }

            // Consume item
            if ((int)$invItem['quantity'] > 1) {
                $this->db->execute("UPDATE inventory SET quantity = quantity - 1 WHERE id = ?", [$inventoryId]);
            } else {
                $this->db->delete('inventory', 'id = ?', [$inventoryId]);
            }

            $this->db->commit();
            $this->jsonSuccess([], "Использован «{$invItem['name']}»");
        } catch (\Throwable $e) {
            $this->db->rollback();
            $this->jsonError('Ошибка использования');
        }
    }

    /**
     * POST /api/inventory/drop - Drop an item
     */
    public function drop(array $params): void
    {
        $charId = $this->requireCharacter();
        $this->requireCsrf();

        $input = getInput();
        $inventoryId = (int)($input['inventory_id'] ?? 0);

        $invItem = $this->db->queryOne(
            "SELECT inv.*, i.name FROM inventory inv JOIN items i ON i.id = inv.item_id
             WHERE inv.id = ? AND inv.character_id = ? AND inv.is_equipped = FALSE",
            [$inventoryId, $charId]
        );

        if (!$invItem) {
            $this->jsonError('Предмет не найден или экипирован (сначала снимите)');
        }

        $this->db->delete('inventory', 'id = ? AND character_id = ?', [$inventoryId, $charId]);
        $this->jsonSuccess([], "Предмет «{$invItem['name']}» выброшен");
    }

    /**
     * Recalculate character derived stats from base + equipment
     */
    private function recalculateCharacterStats(int $charId): void
    {
        // Get base stats
        $base = $this->db->queryOne(
            "SELECT * FROM character_stats WHERE character_id = ?",
            [$charId]
        );

        // Get equipment bonuses
        $equipped = $this->db->query(
            "SELECT i.stats FROM inventory inv JOIN items i ON i.id = inv.item_id
             WHERE inv.character_id = ? AND inv.is_equipped = TRUE",
            [$charId]
        );

        $bonusHp = 0;
        $bonusMana = 0;

        foreach ($equipped as $eq) {
            $stats = json_decode($eq['stats'], true);
            if (is_array($stats)) {
                $bonusHp += (int)($stats['hp'] ?? $stats['max_hp'] ?? 0);
                $bonusMana += (int)($stats['mana'] ?? $stats['max_mana'] ?? 0);
            }
        }

        // Note: We don't modify base HP/mana from equipment, just track bonuses
        // In a full implementation, we'd have a separate calculated stats cache
    }
}
