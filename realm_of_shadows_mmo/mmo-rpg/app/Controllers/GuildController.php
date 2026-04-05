<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\BaseController;
use App\Core\Session;

class GuildController extends BaseController
{
    private const GUILD_CREATE_COST = 1000;
    private const GUILD_CREATE_MIN_LEVEL = 10;
    private const MAX_GUILD_NAME_LENGTH = 30;
    private const MAX_GUILD_MEMBERS = 50;

    /**
     * GET /api/guild/my - Get user's guild info
     */
    public function my(array $params): void
    {
        $charId = $this->requireCharacter();

        $membership = $this->db->queryOne(
            "SELECT gm.guild_id, gm.role as member_role, gm.joined_at,
                    g.name, g.description, g.level, g.experience, g.max_members,
                    g.leader_character_id
             FROM guild_members gm
             JOIN guilds g ON g.id = gm.guild_id
             WHERE gm.character_id = ?",
            [$charId]
        );

        if (!$membership) {
            $this->jsonSuccess(['guild' => null], 'Вы не состоите в гильдии');
            return;
        }

        // Get guild members
        $members = $this->db->query(
            "SELECT c.id, c.name, c.level, c.race, c.class,
                    gm.role as member_role, gm.joined_at
             FROM guild_members gm
             JOIN characters c ON c.id = gm.character_id
             WHERE gm.guild_id = ?
             ORDER BY
                 CASE gm.role WHEN 'leader' THEN 1 WHEN 'officer' THEN 2 ELSE 3 END,
                 c.level DESC",
            [(int)$membership['guild_id']]
        );

        // Get pending invites (for officers/leaders)
        $pendingInvites = [];
        $memberRole = $membership['member_role'];
        if ($memberRole === 'leader' || $memberRole === 'officer') {
            $pendingInvites = $this->db->query(
                "SELECT gi.id, gi.character_id, gi.invited_by, c.name as character_name,
                        inviter.name as inviter_name, gi.created_at
                 FROM guild_invites gi
                 JOIN characters c ON c.id = gi.character_id
                 JOIN characters inviter ON inviter.id = gi.invited_by
                 WHERE gi.guild_id = ? AND gi.status = 'pending'
                 ORDER BY gi.created_at DESC",
                [(int)$membership['guild_id']]
            );
        }

        $this->jsonSuccess([
            'guild'           => $membership,
            'members'         => $members,
            'member_count'    => count($members),
            'pending_invites' => $pendingInvites,
        ]);
    }

    /**
     * POST /api/guild/create - Create a new guild
     */
    public function create(array $params): void
    {
        $charId = $this->requireCharacter();
        $this->requireCsrf();

        $input = getInput();
        $name = sanitize($input['name'] ?? '');
        $description = sanitize($input['description'] ?? '');

        if (empty($name) || mb_strlen($name) > self::MAX_GUILD_NAME_LENGTH) {
            $this->jsonError('Название гильдии должно быть от 1 до ' . self::MAX_GUILD_NAME_LENGTH . ' символов');
        }

        // Get character data
        $char = $this->getCharacterData($charId);
        if (!$char) {
            $this->jsonError('Персонаж не найден', 404);
        }

        // Check level requirement
        if ((int)$char['level'] < self::GUILD_CREATE_MIN_LEVEL) {
            $this->jsonError('Для создания гильдии нужен уровень ' . self::GUILD_CREATE_MIN_LEVEL);
        }

        // Check gold
        if ((int)$char['gold'] < self::GUILD_CREATE_COST) {
            $this->jsonError('Недостаточно золота. Нужно ' . self::GUILD_CREATE_COST . ' золота.');
        }

        // Check not already in a guild
        $existingMembership = $this->db->queryOne(
            "SELECT id FROM guild_members WHERE character_id = ?",
            [$charId]
        );
        if ($existingMembership) {
            $this->jsonError('Вы уже состоите в гильдии');
        }

        // Check name uniqueness
        $existingGuild = $this->db->queryOne("SELECT id FROM guilds WHERE name = ?", [$name]);
        if ($existingGuild) {
            $this->jsonError('Гильдия с таким названием уже существует', 409);
        }

        try {
            $this->db->beginTransaction();

            // Deduct gold
            $this->db->execute(
                "UPDATE characters SET gold = gold - ? WHERE id = ? AND gold >= ?",
                [self::GUILD_CREATE_COST, $charId, self::GUILD_CREATE_COST]
            );

            // Create guild
            $guildId = $this->db->insert('guilds', [
                'name'                => $name,
                'description'         => $description,
                'level'               => 1,
                'experience'          => 0,
                'leader_character_id' => $charId,
                'max_members'         => self::MAX_GUILD_MEMBERS,
                'created_at'          => date('Y-m-d H:i:s'),
                'updated_at'          => date('Y-m-d H:i:s'),
            ]);

            // Add creator as leader
            $this->db->insert('guild_members', [
                'guild_id'   => $guildId,
                'character_id' => $charId,
                'role'       => 'leader',
                'joined_at'  => date('Y-m-d H:i:s'),
            ]);

            $this->db->commit();

            $this->jsonSuccess(['guild_id' => $guildId], "Гильдия «{$name}» создана!");
        } catch (\Throwable $e) {
            $this->db->rollback();
            $this->jsonError('Ошибка при создании гильдии');
        }
    }

    /**
     * POST /api/guild/invite - Invite a character to guild
     */
    public function invite(array $params): void
    {
        $charId = $this->requireCharacter();
        $this->requireCsrf();

        $input = getInput();
        $targetCharacterId = (int)($input['character_id'] ?? 0);

        if ($targetCharacterId <= 0) {
            $this->jsonError('Укажите персонажа для приглашения');
        }

        if ($targetCharacterId === $charId) {
            $this->jsonError('Нельзя пригласить самого себя');
        }

        // Check inviter is guild officer or leader
        $membership = $this->db->queryOne(
            "SELECT guild_id, role as member_role FROM guild_members WHERE character_id = ?",
            [$charId]
        );

        if (!$membership) {
            $this->jsonError('Вы не состоите в гильдии');
        }

        if (!in_array($membership['member_role'], ['leader', 'officer'], true)) {
            $this->jsonError('Только лидер и офицеры могут приглашать');
        }

        $guildId = (int)$membership['guild_id'];

        // Check guild member count
        $memberCount = $this->db->queryOne(
            "SELECT COUNT(*) as cnt FROM guild_members WHERE guild_id = ?",
            [$guildId]
        );

        $guildMax = $this->db->queryOne(
            "SELECT max_members FROM guilds WHERE id = ?",
            [$guildId]
        );

        if ((int)$memberCount['cnt'] >= (int)$guildMax['max_members']) {
            $this->jsonError('Гильдия заполнена. Максимум ' . $guildMax['max_members'] . ' участников.');
        }

        // Check target character exists and is not already in guild
        $target = $this->db->queryOne(
            "SELECT id, name FROM characters WHERE id = ? AND is_alive = TRUE",
            [$targetCharacterId]
        );

        if (!$target) {
            $this->jsonError('Персонаж не найден');
        }

        $targetInGuild = $this->db->queryOne(
            "SELECT id FROM guild_members WHERE character_id = ?",
            [$targetCharacterId]
        );

        if ($targetInGuild) {
            $this->jsonError('Этот персонаж уже состоит в гильдии');
        }

        // Check for existing pending invite
        $existingInvite = $this->db->queryOne(
            "SELECT id FROM guild_invites
             WHERE guild_id = ? AND character_id = ? AND status = 'pending'",
            [$guildId, $targetCharacterId]
        );

        if ($existingInvite) {
            $this->jsonError('Приглашение уже отправлено этому персонажу');
        }

        try {
            $this->db->beginTransaction();

            $this->db->insert('guild_invites', [
                'guild_id'    => $guildId,
                'character_id' => $targetCharacterId,
                'invited_by'  => $charId,
                'status'      => 'pending',
                'created_at'  => date('Y-m-d H:i:s'),
            ]);

            $this->db->commit();

            $this->jsonSuccess([], "Приглашение отправлено персонажу «{$target['name']}»");
        } catch (\Throwable $e) {
            $this->db->rollback();
            $this->jsonError('Ошибка при отправке приглашения');
        }
    }

    /**
     * POST /api/guild/leave - Leave guild
     */
    public function leave(array $params): void
    {
        $charId = $this->requireCharacter();
        $this->requireCsrf();

        // Get membership
        $membership = $this->db->queryOne(
            "SELECT gm.guild_id, gm.role as member_role, g.name as guild_name,
                    g.leader_character_id
             FROM guild_members gm
             JOIN guilds g ON g.id = gm.guild_id
             WHERE gm.character_id = ?",
            [$charId]
        );

        if (!$membership) {
            $this->jsonError('Вы не состоите в гильдии');
        }

        if ($membership['member_role'] === 'leader') {
            // Check if there are other members
            $otherMembers = $this->db->queryOne(
                "SELECT COUNT(*) as cnt FROM guild_members WHERE guild_id = ? AND character_id != ?",
                [(int)$membership['guild_id'], $charId]
            );

            if ((int)$otherMembers['cnt'] > 0) {
                $this->jsonError('Лидер не может покинуть гильдию с другими участниками. Сначала передайте лидерство или распустите гильдию.');
            }

            // Leader is last member — disband guild
            try {
                $this->db->beginTransaction();

                $this->db->delete('guild_members', 'character_id = ?', [$charId]);
                $this->db->delete('guild_invites', 'guild_id = ?', [(int)$membership['guild_id']]);
                $this->db->delete('guilds', 'id = ?', [(int)$membership['guild_id']]);

                $this->db->commit();

                $this->jsonSuccess([], 'Гильдия распущена. Вы были единственным участником.');
            } catch (\Throwable $e) {
                $this->db->rollback();
                $this->jsonError('Ошибка при выходе из гильдии');
            }
            return;
        }

        try {
            $this->db->beginTransaction();

            $this->db->delete('guild_members', 'character_id = ?', [$charId]);

            // Also remove any pending invites sent to this character for this guild
            $this->db->execute(
                "UPDATE guild_invites SET status = 'rejected' WHERE character_id = ? AND guild_id = ? AND status = 'pending'",
                [$charId, (int)$membership['guild_id']]
            );

            $this->db->commit();

            $this->jsonSuccess([], "Вы покинули гильдию «{$membership['guild_name']}»");
        } catch (\Throwable $e) {
            $this->db->rollback();
            $this->jsonError('Ошибка при выходе из гильдии');
        }
    }
}
