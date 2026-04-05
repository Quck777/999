<?php
declare(strict_types=1);

// Load .env file
$envFile = dirname(__DIR__) . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#')) continue;
        if (str_contains($line, '=')) {
            [$key, $value] = explode('=', $line, 2);
            putenv(trim($key) . '=' . trim($value));
            $_ENV[trim($key)] = trim($value);
        }
    }
}

return [
    'db' => [
        'host' => getenv('DB_HOST') ?: 'localhost',
        'port' => (int)(getenv('DB_PORT') ?: 3306),
        'dbname' => getenv('DB_NAME') ?: 'mmo_rpg',
        'user' => getenv('DB_USER') ?: 'root',
        'pass' => getenv('DB_PASS') ?: '',
        'charset' => 'utf8mb4',
    ],
    'app' => [
        'name' => 'Realm of Shadows',
        'debug' => getenv('APP_DEBUG') === 'true',
        'timezone' => 'Europe/Moscow',
        'base_url' => getenv('APP_URL') ?: 'http://localhost',
        'session_lifetime' => 3600,
    ],
    'security' => [
        'csrf_token_name' => '_csrf_token',
        'max_login_attempts' => 5,
        'login_lockout_time' => 900,
        'password_min_length' => 8,
    ],
    'combat' => [
        'max_turns' => 30,
        'turn_timeout' => 60,
        'flee_chance_base' => 0.4,
        'crit_chance_per_luck' => 0.02,
        'dodge_chance_per_agility' => 0.015,
    ],
    'pagination' => [
        'default_per_page' => 20,
        'max_per_page' => 100,
    ],
];
