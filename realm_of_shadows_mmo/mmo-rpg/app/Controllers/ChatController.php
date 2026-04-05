<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\BaseController;
use App\Core\Session;

class ChatController extends BaseController
{
    /**
     * GET /api/chat/{channel} - Get messages for a channel
     */
    public function messages(array $params): void
    {
        $charId = $this->requireCharacter();

        $channel = $params['channel'] ?? 'global';
        $validChannels = ['global', 'local', 'guild', 'trade', 'battle'];
        if (!in_array($channel, $validChannels, true)) {
            $this->jsonError('Некорректный канал');
        }

        $limit = min((int)($_GET['limit'] ?? 50), 100);
        $beforeId = (int)($_GET['before_id'] ?? 0);

        $sql = "SELECT cm.*, c.name as character_name, c.class, c.level,
                       g.name as guild_name
                FROM chat_messages cm
                JOIN characters c ON c.id = cm.character_id
                LEFT JOIN guild_members gm ON gm.character_id = c.id
                LEFT JOIN guilds g ON g.id = gm.guild_id
                WHERE cm.channel = ?";

        $queryParams = [$channel];

        if ($beforeId > 0) {
            $sql .= " AND cm.id < ?";
            $queryParams[] = $beforeId;
        }

        $sql .= " ORDER BY cm.created_at DESC LIMIT ?";
        $queryParams[] = $limit;

        $messages = $this->db->query($sql, $queryParams);
        $messages = array_reverse($messages); // Reverse for chronological order

        $this->jsonSuccess(['messages' => $messages, 'channel' => $channel]);
    }

    /**
     * POST /api/chat/send - Send a message
     */
    public function send(array $params): void
    {
        $charId = $this->requireCharacter();
        $this->requireCsrf();

        $input = getInput();
        $channel = $input['channel'] ?? 'global';
        $message = trim($input['message'] ?? '');
        $targetId = (int)($input['target_character_id'] ?? 0);

        $validChannels = ['global', 'local', 'guild', 'trade', 'battle', 'private'];
        if (!in_array($channel, $validChannels, true)) {
            $this->jsonError('Некорректный канал');
        }

        if (mb_strlen($message) === 0 || mb_strlen($message) > 500) {
            $this->jsonError('Сообщение должно быть от 1 до 500 символов');
        }

        if ($channel === 'private' && $targetId <= 0) {
            $this->jsonError('Для личного сообщения укажите получателя');
        }

        // Rate limiting: max 10 messages per minute
        $minuteAgo = date('Y-m-d H:i:s', time() - 60);
        $recentCount = $this->db->queryOne(
            "SELECT COUNT(*) as cnt FROM chat_messages WHERE character_id = ? AND created_at > ?",
            [$charId, $minuteAgo]
        );
        if ((int)$recentCount['cnt'] >= 10) {
            $this->jsonError('Слишком много сообщений. Подождите.', 429);
        }

        // Sanitize message (strip HTML, preserve safe formatting)
        $safeMessage = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');

        $messageId = $this->db->insert('chat_messages', [
            'character_id'          => $charId,
            'channel'               => $channel,
            'target_character_id'   => $targetId > 0 ? $targetId : null,
            'message'               => $safeMessage,
            'created_at'            => date('Y-m-d H:i:s'),
        ]);

        $this->jsonSuccess([
            'message_id' => $messageId,
            'channel'    => $channel,
        ], 'Сообщение отправлено');
    }
}
