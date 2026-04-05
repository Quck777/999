<?php
declare(strict_types=1);

namespace App\Core;

use RuntimeException;

final class Session
{
    private const CSRF_KEY = '_csrf_token';
    private const USER_KEY = '_user_id';
    private const CHARACTER_KEY = '_character_id';
    private const FLASH_KEY = '_flash';

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start([
                'cookie_httponly' => true,
                'cookie_secure'   => getenv('APP_ENV') === 'production',
                'cookie_samesite' => 'Strict',
                'use_strict_mode' => true,
                'gc_maxlifetime'  => 3600,
            ]);
        }
    }

    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function remove(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public static function setUserId(int $userId): void
    {
        self::set(self::USER_KEY, $userId);
    }

    public static function getUserId(): ?int
    {
        $id = self::get(self::USER_KEY);
        return $id !== null ? (int)$id : null;
    }

    public static function setCharacterId(int $characterId): void
    {
        self::set(self::CHARACTER_KEY, $characterId);
    }

    public static function getCharacterId(): ?int
    {
        $id = self::get(self::CHARACTER_KEY);
        return $id !== null ? (int)$id : null;
    }

    public static function isLoggedIn(): bool
    {
        return self::getUserId() !== null;
    }

    public static function generateCsrfToken(): string
    {
        $token = bin2hex(random_bytes(32));
        self::set(self::CSRF_KEY, $token);
        return $token;
    }

    public static function getCsrfToken(): ?string
    {
        return self::get(self::CSRF_KEY);
    }

    public static function validateCsrfToken(?string $token): bool
    {
        if ($token === null || !self::has(self::CSRF_KEY)) {
            return false;
        }
        return hash_equals(self::get(self::CSRF_KEY), $token);
    }

    // Flash messages
    public static function setFlash(string $type, string $message): void
    {
        $flash = self::get(self::FLASH_KEY, []);
        $flash[$type] = $message;
        self::set(self::FLASH_KEY, $flash);
    }

    public static function getFlash(string $type): ?string
    {
        $flash = self::get(self::FLASH_KEY, []);
        $message = $flash[$type] ?? null;
        if ($message !== null) {
            unset($flash[$type]);
            self::set(self::FLASH_KEY, $flash);
        }
        return $message;
    }

    public static function destroy(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params['path'], $params['domain'],
                $params['secure'], $params['httponly']
            );
        }
        session_destroy();
    }
}
