<?php
declare(strict_types=1);

/**
 * Realm of Shadows — Entry Point
 * 
 * Для GET-запросов к не-API путям — отдаём index.html напрямую.
 * Это гарантирует загрузку страницы даже без MySQL/PHP-FPM.
 * API запросы (GET/POST /api/*) проходят через полный стек фреймворка.
 */

// URI without query string
$uri = '/' . ltrim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// --- FAST PATH: Serve static files directly without PHP overhead ---
// CSS, JS, images, favicon — just let nginx handle these (this file shouldn't even be called)
// But as fallback, serve them:
$staticExtensions = ['css' => 'text/css', 'js' => 'application/javascript', 'png' => 'image/png', 
                     'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif',
                     'svg' => 'image/svg+xml', 'ico' => 'image/x-icon', 'woff' => 'font/woff',
                     'woff2' => 'font/woff2', 'ttf' => 'font/ttf'];
$ext = strtolower(pathinfo($uri, PATHINFO_EXTENSION));
if (isset($staticExtensions[$ext])) {
    $filePath = __DIR__ . $uri;
    if (file_exists($filePath)) {
        header('Content-Type: ' . $staticExtensions[$ext]);
        header('Cache-Control: public, max-age=86400');
        readfile($filePath);
        exit;
    }
}

// --- FAST PATH: Serve index.html for all non-API GET requests ---
// This works even if MySQL is down or PHP extensions are missing
if ($method === 'GET' && !str_starts_with($uri, '/api/')) {
    $indexPath = __DIR__ . '/index.html';
    if (file_exists($indexPath)) {
        header('Content-Type: text/html; charset=utf-8');
        readfile($indexPath);
        exit;
    }
}

// --- FULL STACK: Only for API requests (/api/*) ---
// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
$logDir = dirname(__DIR__) . '/logs';
if (!is_dir($logDir)) mkdir($logDir, 0755, true);
ini_set('error_log', $logDir . '/app.log');

// Timezone
date_default_timezone_set('Europe/Moscow');

// Load autoloader
require_once __DIR__ . '/../app/Core/Autoloader.php';
\App\Core\Autoloader::register();

// Load helpers
require_once __DIR__ . '/../app/Core/Router.php';

// Boot dependencies (DB may be unavailable — catch gracefully)
try {
    $config = require __DIR__ . '/../config/app.php';
    \App\Core\Database::getInstance($config['db']);
} catch (\Throwable $e) {
    // Database unavailable — return JSON error for API requests
    http_response_code(503);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'message' => 'Сервер временно недоступен. База данных не подключена.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

\App\Core\Session::start();

// Generate CSRF if needed
if (!\App\Core\Session::getCsrfToken()) {
    \App\Core\Session::generateCsrfToken();
}

// Initialize Router and load routes
$router = new \App\Core\Router();
$routes = require __DIR__ . '/../config/routes.php';

// Remove SPA fallback from routes — we already handle it above
$routes = array_filter($routes, function($r) {
    return !str_contains($r[1], '{path:');
});

// Apply middleware based on route groups
foreach ($routes as &$route) {
    $middleware = $route[3] ?? [];
    // Add auth middleware to all /api/ routes except auth
    if (str_starts_with($route[1], '/api/') && !str_starts_with($route[1], '/api/auth/')) {
        array_unshift($middleware, 'AuthMiddleware');
    }
    // Add CSRF to POST/PUT/DELETE
    if (in_array($route[0], ['POST', 'PUT', 'DELETE']) && str_starts_with($route[1], '/api/')) {
        $middleware[] = 'CsrfMiddleware';
    }
    $route[3] = $middleware;
}
unset($route);

$router->loadRoutes($routes);

// Dispatch
$result = $router->dispatch($method, $uri);

if (is_string($result)) {
    echo $result;
}
