<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\BaseController;
use App\Core\Session;

class ShopController extends BaseController
{
    /**
     * GET /api/shop/list - Get items from shops in current location
     */
    public function list(array $params): void
    {
        $charId = $this->requireCharacter();

        $char = $this->db->queryOne(
            "SELECT location_id FROM characters WHERE id = ?",
            [$charId]
        );

        if (!$char) {
            $this->jsonError('Персонаж не найден', 404);
        }

        // Find shop NPCs at current location
        $shopNpcs = $this->db->query(
            "SELECT n.id as npc_id, n.name as npc_name, si.item_id, si.price,
                    i.name, i.type, i.rarity, i.icon, i.stats, i.level_requirement,
                    i.description
             FROM npcs n
             JOIN shop_items si ON si.npc_id = n.id
             JOIN items i ON i.id = si.item_id
             WHERE n.location_id = ? AND n.type = 'shop'
             ORDER BY n.name, i.rarity DESC, i.name",
            [$char['location_id']]
        );

        // Group items by NPC
        $shops = [];
        foreach ($shopNpcs as $row) {
            $npcId = (int)$row['npc_id'];
            if (!isset($shops[$npcId])) {
                $shops[$npcId] = [
                    'npc_id'   => $npcId,
                    'npc_name' => $row['npc_name'],
                    'items'    => [],
                ];
            }
            $shops[$npcId]['items'][] = $row;
        }

        $this->jsonSuccess([
            'shops' => array_values($shops),
        ]);
    }

    /**
     * POST /api/shop/buy - Buy item from shop
     */
    public function buy(array $params): void
    {
        $charId = $this->requireCharacter();
        $this->requireCsrf();

        $input = getInput();
        $npcId = (int)($input['npc_id'] ?? 0);
        $itemId = (int)($input['item_id'] ?? 0);
        $quantity = max(1, (int)($input['quantity'] ?? 1));

        if ($npcId <= 0 || $itemId <= 0) {
            $this->jsonError('Укажите продавца и предмет');
        }

        // Verify NPC is a shop at character's location
        $char = $this->db->queryOne(
            "SELECT c.location_id, c.gold FROM characters c WHERE c.id = ?",
            [$charId]
        );

        $npc = $this->db->queryOne(
            "SELECT id, name FROM npcs WHERE id = ? AND location_id = ? AND type = 'shop'",
            [$npcId, $char['location_id']]
        );

        if (!$npc) {
            $this->jsonError('Торговец не найден в этой локации');
        }

        // Get shop item price
        $shopItem = $this->db->queryOne(
            "SELECT si.price, i.name, i.level_requirement
             FROM shop_items si
             JOIN items i ON i.id = si.item_id
             WHERE si.npc_id = ? AND si.item_id = ?",
            [$npcId, $itemId]
        );

        if (!$shopItem) {
            $this->jsonError('Этот предмет не продаётся у данного торговца');
        }

        $totalCost = (int)$shopItem['price'] * $quantity;

        // Check level requirement
        $charData = $this->getCharacterData($charId);
        if ((int)$shopItem['level_requirement'] > (int)$charData['level']) {
            $this->jsonError("Требуется уровень {$shopItem['level_requirement']}");
        }

        // Check gold
        if ((int)$char['gold'] < $totalCost) {
            $this->jsonError('Недостаточно золота');
        }

        try {
            $this->db->beginTransaction();

            // Deduct gold
            $this->db->execute(
                "UPDATE characters SET gold = gold - ? WHERE id = ? AND gold >= ?",
                [$totalCost, $charId, $totalCost]
            );

            // Check if item already in inventory (stackable)
            $existing = $this->db->queryOne(
                "SELECT id, quantity FROM inventory WHERE character_id = ? AND item_id = ? AND is_equipped = FALSE",
                [$charId, $itemId]
            );

            if ($existing) {
                $this->db->execute(
                    "UPDATE inventory SET quantity = quantity + ? WHERE id = ?",
                    [$quantity, (int)$existing['id']]
                );
            } else {
                $this->db->insert('inventory', [
                    'character_id' => $charId,
                    'item_id'      => $itemId,
                    'quantity'     => $quantity,
                    'is_equipped'  => false,
                    'obtained_at'  => date('Y-m-d H:i:s'),
                ]);
            }

            $this->db->commit();

            $this->jsonSuccess([], "Вы купили предмет за {$totalCost} золота");
        } catch (\Throwable $e) {
            $this->db->rollback();
            $this->jsonError('Ошибка при покупке');
        }
    }

    /**
     * POST /api/shop/sell - Sell item from inventory
     */
    public function sell(array $params): void
    {
        $charId = $this->requireCharacter();
        $this->requireCsrf();

        $input = getInput();
        $inventoryId = (int)($input['inventory_id'] ?? 0);
        $quantity = max(1, (int)($input['quantity'] ?? 1));

        if ($inventoryId <= 0) {
            $this->jsonError('Укажите предмет для продажи');
        }

        // Get inventory item with sell price
        $invItem = $this->db->queryOne(
            "SELECT inv.id, inv.quantity, inv.is_equipped, i.name, i.sell_price
             FROM inventory inv
             JOIN items i ON i.id = inv.item_id
             WHERE inv.id = ? AND inv.character_id = ?",
            [$inventoryId, $charId]
        );

        if (!$invItem) {
            $this->jsonError('Предмет не найден в инвентаре');
        }

        if ((bool)$invItem['is_equipped']) {
            $this->jsonError('Сначала снимите предмет перед продажей');
        }

        if ($quantity > (int)$invItem['quantity']) {
            $quantity = (int)$invItem['quantity'];
        }

        $totalSell = (int)$invItem['sell_price'] * $quantity;

        if ($totalSell <= 0) {
            $this->jsonError('Этот предмет нельзя продать');
        }

        try {
            $this->db->beginTransaction();

            // Add gold
            $this->db->execute(
                "UPDATE characters SET gold = gold + ? WHERE id = ?",
                [$totalSell, $charId]
            );

            // Remove or reduce item
            if ($quantity >= (int)$invItem['quantity']) {
                $this->db->delete('inventory', 'id = ? AND character_id = ?', [$inventoryId, $charId]);
            } else {
                $this->db->execute(
                    "UPDATE inventory SET quantity = quantity - ? WHERE id = ?",
                    [$quantity, $inventoryId]
                );
            }

            $this->db->commit();

            $this->jsonSuccess([
                'gold_earned' => $totalSell,
            ], "Вы продали «{$invItem['name']}» за {$totalSell} золота");
        } catch (\Throwable $e) {
            $this->db->rollback();
            $this->jsonError('Ошибка при продаже');
        }
    }
}
