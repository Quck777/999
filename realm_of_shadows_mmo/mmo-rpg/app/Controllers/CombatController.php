<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\BaseController;
use App\Core\Session;
use App\Models\CombatModel;

class CombatController extends BaseController
{
    private CombatModel $combatModel;

    public function __construct()
    {
        parent::__construct();
        $this->combatModel = new CombatModel();
    }

    /**
     * POST /api/combat/start - Start a PvE battle
     */
    public function start(array $params): void
    {
        $charId = $this->requireCharacter();
        $this->requireCsrf();

        $input = getInput();
        $monsterId = (int)($input['monster_id'] ?? 0);

        if ($monsterId <= 0) {
            $this->jsonError('Укажите монстра для боя');
        }

        try {
            $result = $this->combatModel->initPvEBattle($charId, $monsterId);
            $this->jsonSuccess($result);
        } catch (\RuntimeException $e) {
            $this->jsonError($e->getMessage());
        }
    }

    /**
     * POST /api/combat/action - Perform a combat action
     */
    public function action(array $params): void
    {
        $charId = $this->requireCharacter();
        $this->requireCsrf();

        $input = getInput();
        $battleId = (int)($input['battle_id'] ?? 0);
        $action = $input['action'] ?? '';
        $actionParams = $input['params'] ?? [];

        $validActions = ['attack', 'defend', 'skill', 'item', 'flee'];
        if ($battleId <= 0) {
            $this->jsonError('Укажите ID боя');
        }
        if (!in_array($action, $validActions, true)) {
            $this->jsonError('Некорректное действие. Допустимые: ' . implode(', ', $validActions));
        }

        // Verify character is in this battle
        $participant = $this->db->queryOne(
            "SELECT id, is_alive FROM battle_participants WHERE battle_id = ? AND character_id = ? AND is_alive = TRUE",
            [$battleId, $charId]
        );
        if (!$participant) {
            $this->jsonError('Вы не участвуете в этом бою или мертвы');
        }

        try {
            $result = $this->combatModel->processAction(
                $battleId,
                (int)$participant['id'],
                $action,
                $actionParams
            );
            $this->jsonSuccess($result);
        } catch (\RuntimeException $e) {
            $this->jsonError($e->getMessage());
        }
    }

    /**
     * GET /api/combat/status - Get current battle status
     */
    public function status(array $params): void
    {
        $charId = $this->requireCharacter();

        $input = getInput();
        $battleId = (int)($input['battle_id'] ?? $params['id'] ?? 0);

        if ($battleId <= 0) {
            // Try to find active battle for character
            $battle = $this->db->queryOne(
                "SELECT b.id FROM battles b
                 JOIN battle_participants bp ON bp.battle_id = b.id AND bp.character_id = ?
                 WHERE b.status = 'active'",
                [$charId]
            );
            $battleId = $battle ? (int)$battle['id'] : 0;
        }

        if ($battleId <= 0) {
            $this->jsonSuccess(['in_battle' => false]);
            return;
        }

        $status = $this->combatModel->getBattleStatus($battleId);
        $this->jsonSuccess(['in_battle' => true, 'battle' => $status]);
    }

    /**
     * GET /api/combat/log - Get battle log (for polling)
     */
    public function log(array $params): void
    {
        $charId = $this->requireCharacter();

        $battleId = (int)($_GET['battle_id'] ?? 0);
        $sinceTurn = (int)($_GET['since_turn'] ?? 0);

        if ($battleId <= 0) {
            $this->jsonError('Укажите ID боя');
        }

        $logs = $this->combatModel->getBattleLog($battleId, $sinceTurn);
        $this->jsonSuccess(['logs' => $logs]);
    }

    /**
     * POST /api/combat/flee - Attempt to flee
     */
    public function flee(array $params): void
    {
        $charId = $this->requireCharacter();
        $this->requireCsrf();

        $input = getInput();
        $battleId = (int)($input['battle_id'] ?? 0);

        if ($battleId <= 0) {
            $this->jsonError('Укажите ID боя');
        }

        $participant = $this->db->queryOne(
            "SELECT id FROM battle_participants WHERE battle_id = ? AND character_id = ?",
            [$battleId, $charId]
        );
        if (!$participant) {
            $this->jsonError('Вы не в бою');
        }

        try {
            $result = $this->combatModel->processAction(
                $battleId,
                (int)$participant['id'],
                'flee'
            );
            $this->jsonSuccess($result);
        } catch (\RuntimeException $e) {
            $this->jsonError($e->getMessage());
        }
    }
}
