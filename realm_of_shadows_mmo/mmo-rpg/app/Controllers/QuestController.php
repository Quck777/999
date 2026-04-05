<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\BaseController;
use App\Core\Session;

class QuestController extends BaseController
{
    /**
     * GET /api/quests - Return available quests for character's level
     */
    public function list(array $params): void
    {
        $charId = $this->requireCharacter();

        $char = $this->getCharacterData($charId);
        if (!$char) {
            $this->jsonError('Персонаж не найден', 404);
        }

        $level = (int)$char['level'];

        // Get quests available for this level that are not already accepted or completed
        $quests = $this->db->query(
            "SELECT q.* FROM quests q
             WHERE q.min_level <= ? AND q.max_level >= ?
             AND q.id NOT IN (
                 SELECT cq.quest_id FROM character_quests cq
                 WHERE cq.character_id = ? AND cq.status IN ('active', 'completed')
             )
             ORDER BY q.min_level ASC, q.id ASC",
            [$level, $level, $charId]
        );

        $this->jsonSuccess(['quests' => $quests]);
    }

    /**
     * GET /api/quests/active - Return character's active quests
     */
    public function active(array $params): void
    {
        $charId = $this->requireCharacter();

        $activeQuests = $this->db->query(
            "SELECT cq.id as character_quest_id, cq.quest_id, cq.status, cq.progress,
                    cq.started_at,
                    q.name, q.description, q.objectives, q.rewards
             FROM character_quests cq
             JOIN quests q ON q.id = cq.quest_id
             WHERE cq.character_id = ? AND cq.status = 'active'
             ORDER BY cq.started_at DESC",
            [$charId]
        );

        $this->jsonSuccess(['quests' => $activeQuests]);
    }

    /**
     * POST /api/quests/accept - Accept a quest
     */
    public function accept(array $params): void
    {
        $charId = $this->requireCharacter();
        $this->requireCsrf();

        $input = getInput();
        $questId = (int)($input['quest_id'] ?? 0);

        if ($questId <= 0) {
            $this->jsonError('Укажите задание');
        }

        $char = $this->getCharacterData($charId);
        if (!$char) {
            $this->jsonError('Персонаж не найден', 404);
        }

        $level = (int)$char['level'];

        // Check quest exists and meets level requirements
        $quest = $this->db->queryOne(
            "SELECT * FROM quests WHERE id = ? AND min_level <= ? AND max_level >= ?",
            [$questId, $level, $level]
        );

        if (!$quest) {
            $this->jsonError('Задание не найдено или недоступно для вашего уровня');
        }

        // Check if already accepted
        $existing = $this->db->queryOne(
            "SELECT id, status FROM character_quests
             WHERE character_id = ? AND quest_id = ? AND status IN ('active', 'completed')",
            [$charId, $questId]
        );

        if ($existing) {
            $this->jsonError('Вы уже приняли или выполнили это задание');
        }

        // Check max active quests (limit to 5)
        $activeCount = $this->db->queryOne(
            "SELECT COUNT(*) as cnt FROM character_quests WHERE character_id = ? AND status = 'active'",
            [$charId]
        );

        if ((int)$activeCount['cnt'] >= 5) {
            $this->jsonError('Максимум 5 активных заданий');
        }

        try {
            $this->db->beginTransaction();

            $this->db->insert('character_quests', [
                'character_id' => $charId,
                'quest_id'     => $questId,
                'status'       => 'active',
                'progress'     => 0,
                'started_at'   => date('Y-m-d H:i:s'),
                'updated_at'   => date('Y-m-d H:i:s'),
            ]);

            $this->db->commit();

            $this->jsonSuccess([], "Задание «{$quest['name']}» принято!");
        } catch (\Throwable $e) {
            $this->db->rollback();
            $this->jsonError('Ошибка при принятии задания');
        }
    }

    /**
     * POST /api/quests/complete - Complete a quest and award rewards
     */
    public function complete(array $params): void
    {
        $charId = $this->requireCharacter();
        $this->requireCsrf();

        $input = getInput();
        $characterQuestId = (int)($input['character_quest_id'] ?? 0);

        if ($characterQuestId <= 0) {
            $this->jsonError('Укажите задание');
        }

        // Get character quest with quest details
        $charQuest = $this->db->queryOne(
            "SELECT cq.id as character_quest_id, cq.quest_id, cq.progress,
                    q.name, q.objectives, q.rewards
             FROM character_quests cq
             JOIN quests q ON q.id = cq.quest_id
             WHERE cq.id = ? AND cq.character_id = ? AND cq.status = 'active'",
            [$characterQuestId, $charId]
        );

        if (!$charQuest) {
            $this->jsonError('Активное задание не найдено');
        }

        // Check progress (simple: progress >= 1 means completed objectives)
        // In a full implementation, objectives would be parsed and checked individually
        $objectives = json_decode($charQuest['objectives'] ?? '[]', true);
        $targetProgress = is_array($objectives) ? count($objectives) : 1;

        if ((int)$charQuest['progress'] < $targetProgress) {
            $this->jsonError('Задание ещё не выполнено. Продолжайте выполнять условия.');
        }

        $rewards = json_decode($charQuest['rewards'] ?? '{}', true);
        $goldReward = (int)($rewards['gold'] ?? 0);
        $expReward = (int)($rewards['experience'] ?? 0);
        $itemRewards = $rewards['items'] ?? [];

        try {
            $this->db->beginTransaction();

            // Mark quest as completed
            $this->db->update('character_quests', [
                'status'     => 'completed',
                'updated_at' => date('Y-m-d H:i:s'),
            ], 'id = ?', [$characterQuestId]);

            // Award gold
            if ($goldReward > 0) {
                $this->db->execute(
                    "UPDATE characters SET gold = gold + ? WHERE id = ?",
                    [$goldReward, $charId]
                );
            }

            // Award experience
            if ($expReward > 0) {
                $this->db->execute(
                    "UPDATE characters SET experience = experience + ? WHERE id = ?",
                    [$expReward, $charId]
                );
            }

            // Award items
            foreach ($itemRewards as $itemReward) {
                $rewardItemId = (int)($itemReward['item_id'] ?? 0);
                $rewardQty = max(1, (int)($itemReward['quantity'] ?? 1));

                if ($rewardItemId <= 0) {
                    continue;
                }

                // Check if item already in inventory
                $existing = $this->db->queryOne(
                    "SELECT id, quantity FROM inventory WHERE character_id = ? AND item_id = ? AND is_equipped = FALSE",
                    [$charId, $rewardItemId]
                );

                if ($existing) {
                    $this->db->execute(
                        "UPDATE inventory SET quantity = quantity + ? WHERE id = ?",
                        [$rewardQty, (int)$existing['id']]
                    );
                } else {
                    $this->db->insert('inventory', [
                        'character_id' => $charId,
                        'item_id'      => $rewardItemId,
                        'quantity'     => $rewardQty,
                        'is_equipped'  => false,
                        'obtained_at'  => date('Y-m-d H:i:s'),
                    ]);
                }
            }

            $this->db->commit();

            $this->jsonSuccess([
                'gold_earned'  => $goldReward,
                'exp_earned'   => $expReward,
                'items_earned' => $itemRewards,
            ], "Задание «{$charQuest['name']}» выполнено!");
        } catch (\Throwable $e) {
            $this->db->rollback();
            $this->jsonError('Ошибка при завершении задания');
        }
    }
}
