<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\BaseController;
use App\Core\Session;

class ResourceController extends BaseController
{
    private const ENERGY_COST = 5;

    /**
     * POST /api/resource/gather - Gather resources at current location
     */
    public function gather(array $params): void
    {
        $charId = $this->requireCharacter();
        $this->requireCsrf();

        $input = getInput();
        $nodeId = (int)($input['node_id'] ?? 0);

        if ($nodeId <= 0) {
            $this->jsonError('Укажите ресурсную точку');
        }

        // Get character data (energy, location)
        $char = $this->db->queryOne(
            "SELECT c.current_energy, c.max_energy, c.location_id, c.in_combat, c.is_alive,
                    cs.luck
             FROM characters c
             JOIN character_stats cs ON cs.character_id = c.id
             WHERE c.id = ?",
            [$charId]
        );

        if (!$char) {
            $this->jsonError('Персонаж не найден', 404);
        }

        if (!(bool)$char['is_alive']) {
            $this->jsonError('Мёртвые не могут добывать ресурсы');
        }

        if ((bool)$char['in_combat']) {
            $this->jsonError('Нельзя добывать ресурсы во время боя');
        }

        if ((int)$char['current_energy'] < self::ENERGY_COST) {
            $this->jsonError('Недостаточно энергии. Нужно ' . self::ENERGY_COST . ' ед.');
        }

        // Get resource node at character's location
        $node = $this->db->queryOne(
            "SELECT rn.*, r.name as resource_name, r.type as resource_type, r.icon,
                    r.min_level, r.experience_reward
             FROM resource_nodes rn
             JOIN resources r ON r.id = rn.resource_id
             WHERE rn.id = ? AND rn.location_id = ?",
            [$nodeId, $char['location_id']]
        );

        if (!$node) {
            $this->jsonError('Ресурсная точка не найдена в этой локации');
        }

        if ((int)$node['min_level'] > 0) {
            $charFull = $this->getCharacterData($charId);
            if ((int)$charFull['level'] < (int)$node['min_level']) {
                $this->jsonError("Требуется уровень {$node['min_level']} для добычи");
            }
        }

        $luck = (int)$char['luck'];
        $baseChance = 70; // base 70% success
        $successChance = min(95, $baseChance + $luck); // luck improves chance

        if (mt_rand(1, 100) > $successChance) {
            // Failed to gather, still cost energy
            try {
                $this->db->beginTransaction();

                $this->db->execute(
                    "UPDATE characters SET current_energy = GREATEST(0, current_energy - ?) WHERE id = ?",
                    [self::ENERGY_COST, $charId]
                );

                $this->db->commit();

                $this->jsonSuccess([], 'Вам не удалось добыть ресурс. Попробуйте ещё раз.');
            } catch (\Throwable $e) {
                $this->db->rollback();
                $this->jsonError('Ошибка при добыче ресурса');
            }
            return;
        }

        // Determine reward based on resource type and random chance
        $rewardItems = $this->getResourceRewards((int)$node['resource_id'], (int)$node['id']);

        try {
            $this->db->beginTransaction();

            // Deduct energy
            $this->db->execute(
                "UPDATE characters SET current_energy = GREATEST(0, current_energy - ?) WHERE id = ?",
                [self::ENERGY_COST, $charId]
            );

            // Award experience
            $expReward = (int)($node['experience_reward'] ?? 5);
            if ($expReward > 0) {
                $this->db->execute(
                    "UPDATE characters SET experience = experience + ? WHERE id = ?",
                    [$expReward, $charId]
                );
            }

            // Add items to inventory
            $addedItems = [];
            foreach ($rewardItems as $reward) {
                $itemId = (int)$reward['item_id'];
                $qty = (int)$reward['quantity'];

                if ($itemId <= 0 || $qty <= 0) {
                    continue;
                }

                $existing = $this->db->queryOne(
                    "SELECT id, quantity FROM inventory WHERE character_id = ? AND item_id = ? AND is_equipped = FALSE",
                    [$charId, $itemId]
                );

                if ($existing) {
                    $this->db->execute(
                        "UPDATE inventory SET quantity = quantity + ? WHERE id = ?",
                        [$qty, (int)$existing['id']]
                    );
                } else {
                    $this->db->insert('inventory', [
                        'character_id' => $charId,
                        'item_id'      => $itemId,
                        'quantity'     => $qty,
                        'is_equipped'  => false,
                        'obtained_at'  => date('Y-m-d H:i:s'),
                    ]);
                }

                $itemName = $this->db->queryOne("SELECT name FROM items WHERE id = ?", [$itemId]);
                $addedItems[] = [
                    'item_id'  => $itemId,
                    'name'     => $itemName ? $itemName['name'] : 'Неизвестно',
                    'quantity' => $qty,
                ];
            }

            $this->db->commit();

            $this->jsonSuccess([
                'energy_spent' => self::ENERGY_COST,
                'exp_earned'   => $expReward,
                'items'        => $addedItems,
            ], 'Вы успешно добыли ресурсы!');
        } catch (\Throwable $e) {
            $this->db->rollback();
            $this->jsonError('Ошибка при добыче ресурса');
        }
    }

    /**
     * Determine random rewards for a resource node
     */
    private function getResourceRewards(int $resourceId, int $nodeId): array
    {
        // Look up possible item drops for this resource
        $drops = $this->db->query(
            "SELECT * FROM resource_drops WHERE resource_id = ? ORDER BY chance DESC",
            [$resourceId]
        );

        if (empty($drops)) {
            // Fallback: return a generic material based on resource
            return [];
        }

        $rewards = [];
        foreach ($drops as $drop) {
            $chance = (float)$drop['chance'];
            $minQty = (int)($drop['min_quantity'] ?? 1);
            $maxQty = (int)($drop['max_quantity'] ?? 1);

            if (mt_rand(1, 100) <= ($chance * 100)) {
                $rewards[] = [
                    'item_id'  => (int)$drop['item_id'],
                    'quantity' => mt_rand($minQty, max($minQty, $maxQty)),
                ];
            }
        }

        // Always guarantee at least 1 item on success
        if (empty($rewards) && !empty($drops)) {
            $firstDrop = $drops[0];
            $rewards[] = [
                'item_id'  => (int)$firstDrop['item_id'],
                'quantity' => (int)($firstDrop['min_quantity'] ?? 1),
            ];
        }

        return $rewards;
    }
}
