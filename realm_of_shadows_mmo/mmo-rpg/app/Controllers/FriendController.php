<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\BaseController;
use App\Core\Session;

class FriendController extends BaseController
{
    /**
     * GET /api/friends - List character's friends
     */
    public function list(array $params): void
    {
        $charId = $this->requireCharacter();

        $friends = $this->db->query(
            "SELECT f.friend_character_id as character_id,
                    c.name, c.level, c.race, c.class, c.is_alive, c.in_combat,
                    l.name as location_name,
                    g.name as guild_name
             FROM friends f
             JOIN characters c ON c.id = f.friend_character_id
             LEFT JOIN locations l ON l.id = c.location_id
             LEFT JOIN guild_members gm ON gm.character_id = c.id
             LEFT JOIN guilds g ON g.id = gm.guild_id
             WHERE f.character_id = ?
             ORDER BY c.name ASC",
            [$charId]
        );

        // Get incoming friend requests
        $incomingRequests = $this->db->query(
            "SELECT fr.id as request_id, fr.from_character_id,
                    c.name, c.level, c.race, c.class, fr.created_at
             FROM friend_requests fr
             JOIN characters c ON c.id = fr.from_character_id
             WHERE fr.to_character_id = ? AND fr.status = 'pending'
             ORDER BY fr.created_at DESC",
            [$charId]
        );

        // Get outgoing friend requests
        $outgoingRequests = $this->db->query(
            "SELECT fr.id as request_id, fr.to_character_id,
                    c.name, c.level, c.race, c.class, fr.created_at
             FROM friend_requests fr
             JOIN characters c ON c.id = fr.to_character_id
             WHERE fr.from_character_id = ? AND fr.status = 'pending'
             ORDER BY fr.created_at DESC",
            [$charId]
        );

        // Get blocked characters
        $blocked = $this->db->query(
            "SELECT b.blocked_character_id as character_id, c.name, c.level
             FROM friends b
             JOIN characters c ON c.id = b.blocked_character_id
             WHERE b.character_id = ? AND b.is_blocked = TRUE
             ORDER BY c.name ASC",
            [$charId]
        );

        $this->jsonSuccess([
            'friends'            => $friends,
            'incoming_requests'  => $incomingRequests,
            'outgoing_requests'  => $outgoingRequests,
            'blocked'            => $blocked,
        ]);
    }

    /**
     * POST /api/friends/request - Send a friend request
     */
    public function request(array $params): void
    {
        $charId = $this->requireCharacter();
        $this->requireCsrf();

        $input = getInput();
        $targetCharacterId = (int)($input['character_id'] ?? 0);

        if ($targetCharacterId <= 0) {
            $this->jsonError('Укажите персонажа');
        }

        if ($targetCharacterId === $charId) {
            $this->jsonError('Нельзя добавить самого себя в друзья');
        }

        // Check target character exists
        $target = $this->db->queryOne(
            "SELECT id, name FROM characters WHERE id = ?",
            [$targetCharacterId]
        );

        if (!$target) {
            $this->jsonError('Персонаж не найден');
        }

        // Check if already friends
        $existingFriend = $this->db->queryOne(
            "SELECT id FROM friends
             WHERE character_id = ? AND friend_character_id = ? AND is_blocked = FALSE",
            [$charId, $targetCharacterId]
        );

        if ($existingFriend) {
            $this->jsonError('Этот персонаж уже в вашем списке друзей');
        }

        // Check reverse friendship
        $reverseFriend = $this->db->queryOne(
            "SELECT id FROM friends
             WHERE character_id = ? AND friend_character_id = ? AND is_blocked = FALSE",
            [$targetCharacterId, $charId]
        );

        if ($reverseFriend) {
            $this->jsonError('Этот персонаж уже в вашем списке друзей');
        }

        // Check for existing pending request (either direction)
        $existingRequest = $this->db->queryOne(
            "SELECT id, status FROM friend_requests
             WHERE ((from_character_id = ? AND to_character_id = ?)
                    OR (from_character_id = ? AND to_character_id = ?))
             AND status = 'pending'",
            [$charId, $targetCharacterId, $targetCharacterId, $charId]
        );

        if ($existingRequest) {
            $this->jsonError('Заявка уже отправлена или получена');
        }

        // Check if blocked
        $isBlocked = $this->db->queryOne(
            "SELECT id FROM friends
             WHERE (character_id = ? AND friend_character_id = ? AND is_blocked = TRUE)
                OR (character_id = ? AND friend_character_id = ? AND is_blocked = TRUE)",
            [$charId, $targetCharacterId, $targetCharacterId, $charId]
        );

        if ($isBlocked) {
            $this->jsonError('Невозможно отправить заявку');
        }

        try {
            $this->db->beginTransaction();

            $this->db->insert('friend_requests', [
                'from_character_id' => $charId,
                'to_character_id'   => $targetCharacterId,
                'status'            => 'pending',
                'created_at'        => date('Y-m-d H:i:s'),
            ]);

            $this->db->commit();

            $this->jsonSuccess([], "Заявка отправлена персонажу «{$target['name']}»");
        } catch (\Throwable $e) {
            $this->db->rollback();
            $this->jsonError('Ошибка при отправке заявки');
        }
    }

    /**
     * POST /api/friends/accept - Accept a friend request
     */
    public function accept(array $params): void
    {
        $charId = $this->requireCharacter();
        $this->requireCsrf();

        $input = getInput();
        $requestId = (int)($input['request_id'] ?? 0);

        if ($requestId <= 0) {
            $this->jsonError('Укажите заявку');
        }

        // Get the request
        $friendRequest = $this->db->queryOne(
            "SELECT fr.id, fr.from_character_id, fr.to_character_id, c.name
             FROM friend_requests fr
             JOIN characters c ON c.id = fr.from_character_id
             WHERE fr.id = ? AND fr.to_character_id = ? AND fr.status = 'pending'",
            [$requestId, $charId]
        );

        if (!$friendRequest) {
            $this->jsonError('Заявка не найдена или уже обработана');
        }

        $fromCharacterId = (int)$friendRequest['from_character_id'];

        try {
            $this->db->beginTransaction();

            // Mark request as accepted
            $this->db->update('friend_requests', [
                'status'     => 'accepted',
                'updated_at' => date('Y-m-d H:i:s'),
            ], 'id = ?', [$requestId]);

            // Add bidirectional friendship
            $now = date('Y-m-d H:i:s');

            $this->db->insert('friends', [
                'character_id'       => $charId,
                'friend_character_id' => $fromCharacterId,
                'is_blocked'         => false,
                'created_at'         => $now,
            ]);

            $this->db->insert('friends', [
                'character_id'       => $fromCharacterId,
                'friend_character_id' => $charId,
                'is_blocked'         => false,
                'created_at'         => $now,
            ]);

            $this->db->commit();

            $this->jsonSuccess([], "Вы добавили «{$friendRequest['name']}» в друзья!");
        } catch (\Throwable $e) {
            $this->db->rollback();
            $this->jsonError('Ошибка при принятии заявки');
        }
    }

    /**
     * POST /api/friends/block - Block a character
     */
    public function block(array $params): void
    {
        $charId = $this->requireCharacter();
        $this->requireCsrf();

        $input = getInput();
        $targetCharacterId = (int)($input['character_id'] ?? 0);

        if ($targetCharacterId <= 0) {
            $this->jsonError('Укажите персонажа');
        }

        if ($targetCharacterId === $charId) {
            $this->jsonError('Нельзя заблокировать самого себя');
        }

        $target = $this->db->queryOne(
            "SELECT id, name FROM characters WHERE id = ?",
            [$targetCharacterId]
        );

        if (!$target) {
            $this->jsonError('Персонаж не найден');
        }

        try {
            $this->db->beginTransaction();

            // Remove friendship if exists
            $this->db->delete(
                'friends',
                '(character_id = ? AND friend_character_id = ? AND is_blocked = FALSE)
                 OR (character_id = ? AND friend_character_id = ? AND is_blocked = FALSE)',
                [$charId, $targetCharacterId, $targetCharacterId, $charId]
            );

            // Reject any pending requests between them
            $this->db->execute(
                "UPDATE friend_requests SET status = 'rejected', updated_at = ?
                 WHERE status = 'pending'
                 AND ((from_character_id = ? AND to_character_id = ?)
                      OR (from_character_id = ? AND to_character_id = ?))",
                [date('Y-m-d H:i:s'), $charId, $targetCharacterId, $targetCharacterId, $charId]
            );

            // Add or update block
            $existingBlock = $this->db->queryOne(
                "SELECT id FROM friends WHERE character_id = ? AND friend_character_id = ? AND is_blocked = TRUE",
                [$charId, $targetCharacterId]
            );

            if ($existingBlock) {
                // Already blocked
                $this->db->commit();
                $this->jsonSuccess([], "Персонаж «{$target['name']}» уже заблокирован");
                return;
            }

            $this->db->insert('friends', [
                'character_id'       => $charId,
                'friend_character_id' => $targetCharacterId,
                'is_blocked'         => true,
                'created_at'         => date('Y-m-d H:i:s'),
            ]);

            $this->db->commit();

            $this->jsonSuccess([], "Персонаж «{$target['name']}» заблокирован");
        } catch (\Throwable $e) {
            $this->db->rollback();
            $this->jsonError('Ошибка при блокировке');
        }
    }
}
