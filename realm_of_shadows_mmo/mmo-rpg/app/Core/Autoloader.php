<?php
declare(strict_types=1);

namespace App\Core;

final class Autoloader
{
    private const APP_NAMESPACE = 'App\\';
    private const APP_PATH = __DIR__ . '/..';

    public static function register(): void
    {
        spl_autoload_register([self::class, 'autoload']);
    }

    public static function autoload(string $class): void
    {
        if (!str_starts_with($class, self::APP_NAMESPACE)) {
            return;
        }

        $relativeClass = substr($class, strlen(self::APP_NAMESPACE));
        $file = self::APP_PATH . '/' . str_replace('\\', '/', $relativeClass) . '.php';

        if (file_exists($file)) {
            require_once $file;
        }
    }
}
