<?php
declare(strict_types=1);

namespace App\Core;

use App\Core\Database;
use App\Core\Session;
use RuntimeException;

abstract class BaseController
{
    protected Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    protected function requireAuth(): int
    {
        $userId = Session::getUserId();
        if ($userId === null) {
            echo jsonResponse(['success' => false, 'message' => 'Требуется авторизация'], 401);
            exit;
        }
        return $userId;
    }

    protected function requireCharacter(): int
    {
        $characterId = Session::getCharacterId();
        if ($characterId === null) {
            echo jsonResponse(['success' => false, 'message' => 'Персонаж не выбран'], 403);
            exit;
        }
        return $characterId;
    }

    protected function requireCsrf(): void
    {
        $input = getInput();
        $token = $input['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        if (!Session::validateCsrfToken($token)) {
            echo jsonResponse(['success' => false, 'message' => 'Неверный CSRF-токен. Обновите страницу.'], 403);
            exit;
        }
    }

    protected function validateCsrfOrSkip(): bool
    {
        $input = getInput();
        $token = $input['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        return Session::validateCsrfToken($token);
    }

    protected function jsonSuccess(array $data = [], string $message = '', int $code = 200): void
    {
        $response = ['success' => true];
        if ($message) $response['message'] = $message;
        if ($data) $response['data'] = $data;
        $response['csrf'] = Session::getCsrfToken();
        echo jsonResponse($response, $code);
        exit;
    }

    protected function jsonError(string $message, int $code = 400, array $errors = []): void
    {
        $response = ['success' => false, 'message' => $message];
        if ($errors) $response['errors'] = $errors;
        $response['csrf'] = Session::generateCsrfToken();
        echo jsonResponse($response, $code);
        exit;
    }

    protected function getCharacterData(int $characterId): ?array
    {
        return $this->db->queryOne(
            "SELECT c.id as character_id, c.user_id, c.name, c.race, c.class, c.level, c.experience,
                    c.current_hp, c.max_hp, c.current_mana, c.max_mana, c.current_energy, c.max_energy,
                    c.gold, c.rating, c.location_id, c.is_alive, c.in_combat, c.kills, c.deaths,
                    c.created_at, c.updated_at,
                    cs.strength, cs.agility, cs.endurance, cs.intelligence, cs.luck, cs.stat_points
             FROM characters c
             JOIN character_stats cs ON cs.character_id = c.id
             WHERE c.id = ? AND c.is_alive = TRUE",
            [$characterId]
        );
    }
}
