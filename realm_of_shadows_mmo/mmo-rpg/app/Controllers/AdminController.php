<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\BaseController;
use App\Core\Session;

class AdminController extends BaseController
{
    /**
     * Verify the current user has admin role
     */
    private function requireAdmin(): int
    {
        $userId = $this->requireAuth();

        $user = $this->db->queryOne(
            "SELECT id, role FROM users WHERE id = ?",
            [$userId]
        );

        if (!$user || $user['role'] !== 'admin') {
            $this->jsonError('Доступ запрещён. Требуются права администратора.', 403);
        }

        return $userId;
    }

    /**
     * GET /api/admin/users - List all users with pagination
     */
    public function users(array $params): void
    {
        $this->requireAdmin();

        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = min(50, (int)($_GET['per_page'] ?? 20));
        $offset = ($page - 1) * $perPage;

        $search = $_GET['search'] ?? '';

        if (!empty($search)) {
            $like = '%' . $search . '%';
            $users = $this->db->query(
                "SELECT u.id, u.username, u.email, u.role, u.is_active, u.is_banned,
                        u.last_login_at, u.created_at,
                        (SELECT COUNT(*) FROM characters c WHERE c.user_id = u.id) as character_count
                 FROM users u
                 WHERE u.username LIKE ? OR u.email LIKE ?
                 ORDER BY u.id DESC
                 LIMIT ? OFFSET ?",
                [$like, $like, $perPage, $offset]
            );

            $total = $this->db->queryOne(
                "SELECT COUNT(*) as cnt FROM users WHERE username LIKE ? OR email LIKE ?",
                [$like, $like]
            );
        } else {
            $users = $this->db->query(
                "SELECT u.id, u.username, u.email, u.role, u.is_active, u.is_banned,
                        u.last_login_at, u.created_at,
                        (SELECT COUNT(*) FROM characters c WHERE c.user_id = u.id) as character_count
                 FROM users u
                 ORDER BY u.id DESC
                 LIMIT ? OFFSET ?",
                [$perPage, $offset]
            );

            $total = $this->db->queryOne("SELECT COUNT(*) as cnt FROM users");
        }

        $this->jsonSuccess([
            'users'     => $users,
            'total'     => (int)$total['cnt'],
            'page'      => $page,
            'per_page'  => $perPage,
            'last_page' => (int)ceil((int)$total['cnt'] / $perPage),
        ]);
    }

    /**
     * GET /api/admin/items - List all game items
     */
    public function items(array $params): void
    {
        $this->requireAdmin();

        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = min(100, (int)($_GET['per_page'] ?? 50));
        $offset = ($page - 1) * $perPage;

        $type = $_GET['type'] ?? '';
        $rarity = $_GET['rarity'] ?? '';

        $sql = "SELECT * FROM items";
        $sqlParams = [];
        $conditions = [];

        if (!empty($type)) {
            $conditions[] = "type = ?";
            $sqlParams[] = $type;
        }

        if (!empty($rarity)) {
            $conditions[] = "rarity = ?";
            $sqlParams[] = $rarity;
        }

        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(' AND ', $conditions);
        }

        $countSql = str_replace("SELECT *", "SELECT COUNT(*) as cnt", $sql);
        $total = $this->db->queryOne($countSql, $sqlParams);

        $sql .= " ORDER BY id DESC LIMIT ? OFFSET ?";
        $sqlParams[] = $perPage;
        $sqlParams[] = $offset;

        $items = $this->db->query($sql, $sqlParams);

        $this->jsonSuccess([
            'items'     => $items,
            'total'     => (int)$total['cnt'],
            'page'      => $page,
            'per_page'  => $perPage,
            'last_page' => (int)ceil((int)$total['cnt'] / $perPage),
        ]);
    }

    /**
     * POST /api/admin/items/create - Create a new game item
     */
    public function createItem(array $params): void
    {
        $adminId = $this->requireAdmin();
        $this->requireCsrf();

        $input = getInput();

        $errors = [];
        $name = sanitize($input['name'] ?? '');
        $type = $input['type'] ?? '';
        $rarity = $input['rarity'] ?? '';

        if (empty($name)) {
            $errors['name'] = 'Укажите название предмета';
        }
        if (empty($type)) {
            $errors['type'] = 'Укажите тип предмета';
        }
        if (empty($rarity)) {
            $errors['rarity'] = 'Укажите редкость предмета';
        }

        $validTypes = ['weapon', 'armor', 'helmet', 'boots', 'gloves', 'shield', 'accessory', 'consumable', 'material'];
        $validRarities = ['common', 'uncommon', 'rare', 'epic', 'legendary'];

        if (!in_array($type, $validTypes, true)) {
            $errors['type'] = 'Некорректный тип. Допустимые: ' . implode(', ', $validTypes);
        }
        if (!in_array($rarity, $validRarities, true)) {
            $errors['rarity'] = 'Некорректная редкость. Допустимые: ' . implode(', ', $validRarities);
        }

        if (!empty($errors)) {
            $this->jsonError('Проверьте введённые данные', 422, $errors);
        }

        // Check name uniqueness
        $existing = $this->db->queryOne("SELECT id FROM items WHERE name = ?", [$name]);
        if ($existing) {
            $this->jsonError('Предмет с таким названием уже существует', 409);
        }

        $stats = $input['stats'] ?? [];
        if (is_string($stats)) {
            $stats = json_decode($stats, true);
        }
        if (!is_array($stats)) {
            $stats = [];
        }

        try {
            $this->db->beginTransaction();

            $itemId = $this->db->insert('items', [
                'name'              => $name,
                'type'              => $type,
                'rarity'            => $rarity,
                'icon'              => $input['icon'] ?? '',
                'slot'              => $input['slot'] ?? null,
                'stats'             => json_encode($stats),
                'description'       => sanitize($input['description'] ?? ''),
                'level_requirement' => (int)($input['level_requirement'] ?? 1),
                'price'             => (int)($input['price'] ?? 0),
                'sell_price'        => (int)($input['sell_price'] ?? 0),
                'created_at'        => date('Y-m-d H:i:s'),
                'updated_at'        => date('Y-m-d H:i:s'),
            ]);

            // Log admin action
            $this->logAdminAction($adminId, 'create_item', "Создан предмет: {$name} (ID: {$itemId})");

            $this->db->commit();

            $this->jsonSuccess(['item_id' => $itemId], "Предмет «{$name}» создан!", 201);
        } catch (\Throwable $e) {
            $this->db->rollback();
            $this->jsonError('Ошибка при создании предмета', 500);
        }
    }

    /**
     * GET /api/admin/logs - View admin action logs
     */
    public function logs(array $params): void
    {
        $this->requireAdmin();

        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = min(100, (int)($_GET['per_page'] ?? 50));
        $offset = ($page - 1) * $perPage;

        $action = $_GET['action'] ?? '';
        $userId = (int)($_GET['user_id'] ?? 0);

        $sql = "SELECT al.*, u.username FROM admin_logs al LEFT JOIN users u ON u.id = al.admin_user_id";
        $sqlParams = [];
        $conditions = [];

        if (!empty($action)) {
            $conditions[] = "al.action = ?";
            $sqlParams[] = $action;
        }

        if ($userId > 0) {
            $conditions[] = "al.admin_user_id = ?";
            $sqlParams[] = $userId;
        }

        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(' AND ', $conditions);
        }

        $countSql = str_replace(
            "SELECT al.*, u.username",
            "SELECT COUNT(*) as cnt",
            $sql
        );
        $total = $this->db->queryOne($countSql, $sqlParams);

        $sql .= " ORDER BY al.created_at DESC LIMIT ? OFFSET ?";
        $sqlParams[] = $perPage;
        $sqlParams[] = $offset;

        $logs = $this->db->query($sql, $sqlParams);

        $this->jsonSuccess([
            'logs'      => $logs,
            'total'     => (int)$total['cnt'],
            'page'      => $page,
            'per_page'  => $perPage,
            'last_page' => (int)ceil((int)$total['cnt'] / $perPage),
        ]);
    }

    /**
     * POST /api/admin/ban - Ban or unban a user
     */
    public function ban(array $params): void
    {
        $adminId = $this->requireAdmin();
        $this->requireCsrf();

        $input = getInput();
        $targetUserId = (int)($input['user_id'] ?? 0);
        $ban = (bool)($input['ban'] ?? true);
        $reason = sanitize($input['reason'] ?? '');

        if ($targetUserId <= 0) {
            $this->jsonError('Укажите пользователя');
        }

        if ($targetUserId === $adminId) {
            $this->jsonError('Нельзя заблокировать самого себя');
        }

        $targetUser = $this->db->queryOne(
            "SELECT id, username, role, is_banned FROM users WHERE id = ?",
            [$targetUserId]
        );

        if (!$targetUser) {
            $this->jsonError('Пользователь не найден', 404);
        }

        if ($targetUser['role'] === 'admin') {
            $this->jsonError('Нельзя заблокировать администратора');
        }

        if ($ban && !$reason) {
            $this->jsonError('Укажите причину блокировки');
        }

        try {
            $this->db->beginTransaction();

            $this->db->update('users', [
                'is_banned'  => $ban,
                'ban_reason' => $ban ? $reason : null,
                'banned_at'  => $ban ? date('Y-m-d H:i:s') : null,
                'updated_at' => date('Y-m-d H:i:s'),
            ], 'id = ?', [$targetUserId]);

            if ($ban) {
                // Kill all character sessions for banned user
                $this->db->execute(
                    "UPDATE characters SET is_alive = FALSE WHERE user_id = ? AND is_alive = TRUE",
                    [$targetUserId]
                );
            }

            $action = $ban ? 'ban_user' : 'unban_user';
            $detail = $ban
                ? "Пользователь {$targetUser['username']} (ID: {$targetUserId}) заблокирован. Причина: {$reason}"
                : "Пользователь {$targetUser['username']} (ID: {$targetUserId}) разблокирован";

            $this->logAdminAction($adminId, $action, $detail);

            $this->db->commit();

            $message = $ban
                ? "Пользователь «{$targetUser['username']}» заблокирован"
                : "Пользователь «{$targetUser['username']}» разблокирован";

            $this->jsonSuccess([], $message);
        } catch (\Throwable $e) {
            $this->db->rollback();
            $this->jsonError('Ошибка при изменении статуса блокировки');
        }
    }

    /**
     * Log an admin action to admin_logs table
     */
    private function logAdminAction(int $adminUserId, string $action, string $detail): void
    {
        try {
            $this->db->insert('admin_logs', [
                'admin_user_id' => $adminUserId,
                'action'        => $action,
                'detail'        => $detail,
                'ip_address'    => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
                'created_at'    => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            // Log failure should not break the main transaction
            // In production this would be logged to error monitoring
        }
    }
}
