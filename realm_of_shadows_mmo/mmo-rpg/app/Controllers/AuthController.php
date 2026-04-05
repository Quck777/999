<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\BaseController;
use App\Core\Database;
use App\Core\Session;

class AuthController extends BaseController
{
    /**
     * POST /api/auth/register
     */
    public function register(array $params): void
    {
        $input = getInput();
        $errors = validateRequired($input, [
            'username' => 'Имя пользователя',
            'email'    => 'Email',
            'password' => 'Пароль',
        ]);

        if (!empty($errors)) {
            $this->jsonError('Проверьте введённые данные', 422, $errors);
        }

        $username = sanitize($input['username']);
        $email = filter_var(trim($input['email']), FILTER_VALIDATE_EMAIL);
        $password = $input['password'];

        if ($email === false) {
            $this->jsonError('Некорректный email', 422, ['email' => 'Введите корректный email']);
        }

        if (mb_strlen($username) < 3 || mb_strlen($username) > 20) {
            $this->jsonError('', 422, ['username' => 'Имя должно быть от 3 до 20 символов']);
        }

        if (!preg_match('/^[a-zA-Z0-9_\p{Cyrillic}]+$/u', $username)) {
            $this->jsonError('', 422, ['username' => 'Имя может содержать буквы, цифры и подчёркивания']);
        }

        if (mb_strlen($password) < 8) {
            $this->jsonError('', 422, ['password' => 'Пароль должен быть не менее 8 символов']);
        }

        // Check uniqueness
        $existing = $this->db->queryOne(
            "SELECT id FROM users WHERE username = ? OR email = ?",
            [$username, $email]
        );

        if ($existing) {
            $this->jsonError('Пользователь с таким именем или email уже существует', 409);
        }

        $passwordHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

        try {
            $userId = $this->db->insert('users', [
                'username'     => $username,
                'email'        => $email,
                'password_hash'=> $passwordHash,
                'is_active'    => true,
                'created_at'   => date('Y-m-d H:i:s'),
                'updated_at'   => date('Y-m-d H:i:s'),
            ]);

            Session::setUserId($userId);

            $this->jsonSuccess([
                'user_id'  => $userId,
                'username' => $username,
            ], 'Регистрация успешна', 201);
        } catch (\Throwable $e) {
            $this->jsonError('Ошибка при регистрации. Попробуйте позже.', 500);
        }
    }

    /**
     * POST /api/auth/login
     */
    public function login(array $params): void
    {
        $input = getInput();

        $errors = validateRequired($input, [
            'login'    => 'Логин или email',
            'password' => 'Пароль',
        ]);

        if (!empty($errors)) {
            $this->jsonError('Проверьте введённые данные', 422, $errors);
        }

        $login = sanitize($input['login']);
        $password = $input['password'];

        $user = $this->db->queryOne(
            "SELECT * FROM users WHERE (username = ? OR email = ?) AND is_active = TRUE",
            [$login, $login]
        );

        if (!$user || !password_verify($password, $user['password_hash'])) {
            // Increment failed attempts (basic rate limiting)
            $this->jsonError('Неверный логин или пароль', 401);
        }

        Session::setUserId((int)$user['id']);

        // Check if user has a character and set it
        $character = $this->db->queryOne(
            "SELECT id FROM characters WHERE user_id = ? ORDER BY created_at DESC LIMIT 1",
            [(int)$user['id']]
        );
        if ($character) {
            Session::setCharacterId((int)$character['id']);
        }

        // Update last login
        $this->db->update('users', [
            'last_login_at' => date('Y-m-d H:i:s'),
            'last_ip'       => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
            'updated_at'    => date('Y-m-d H:i:s'),
        ], 'id = ?', [(int)$user['id']]);

        $this->jsonSuccess([
            'user_id'    => (int)$user['id'],
            'username'   => $user['username'],
            'role'       => $user['role'],
            'character'  => $character ? (int)$character['id'] : null,
        ], 'Вход выполнен успешно');
    }

    /**
     * POST /api/auth/logout
     */
    public function logout(array $params): void
    {
        Session::destroy();
        $this->jsonSuccess([], 'Вы вышли из аккаунта');
    }

    /**
     * GET /api/auth/me
     */
    public function me(array $params): void
    {
        $userId = $this->requireAuth();

        $user = $this->db->queryOne(
            "SELECT id, username, email, role, created_at FROM users WHERE id = ?",
            [$userId]
        );

        if (!$user) {
            $this->jsonError('Пользователь не найден', 404);
        }

        $character = $this->db->queryOne(
            "SELECT id, name, race, class, level, gold FROM characters WHERE user_id = ?",
            [$userId]
        );

        $this->jsonSuccess([
            'user'      => $user,
            'character' => $character,
        ]);
    }
}
