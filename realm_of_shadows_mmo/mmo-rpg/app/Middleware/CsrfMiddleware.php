<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Session;
use function App\Core\jsonResponse;

class CsrfMiddleware
{
    public function handle(array $params): ?string
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' || $_SERVER['REQUEST_METHOD'] === 'PUT' || $_SERVER['REQUEST_METHOD'] === 'DELETE') {
            $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? null;
            if (!Session::validateCsrfToken($token)) {
                return jsonResponse(['success' => false, 'message' => 'Неверный CSRF-токен'], 403);
            }
        }
        return null;
    }
}
