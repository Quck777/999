<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Session;
use function App\Core\jsonResponse;

class AuthMiddleware
{
    public function handle(array $params): ?string
    {
        if (!Session::isLoggedIn()) {
            return jsonResponse(['success' => false, 'message' => 'Требуется авторизация'], 401);
        }
        return null;
    }
}
