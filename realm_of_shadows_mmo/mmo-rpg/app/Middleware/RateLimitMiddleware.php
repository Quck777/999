<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Database;
use App\Core\Session;
use function App\Core\jsonResponse;

class RateLimitMiddleware
{
    private int $maxRequests;
    private int $windowSeconds;

    public function __construct(int $maxRequests = 60, int $windowSeconds = 60)
    {
        $this->maxRequests = $maxRequests;
        $this->windowSeconds = $windowSeconds;
    }

    public function handle(array $params): ?string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $key = 'rate_limit:' . $ip . ':' . ($params['__route_key__'] ?? $_SERVER['REQUEST_URI']);
        $now = time();
        $windowStart = $now - $this->windowSeconds;

        // Use file-based rate limiting (simple, no Redis dependency)
        $cacheDir = dirname(__DIR__, 2) . '/storage/cache/rate_limit';
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }

        $cacheFile = $cacheDir . '/' . md5($key) . '.json';
        $data = ['count' => 0, 'window_start' => $now];

        if (file_exists($cacheFile)) {
            $cached = json_decode(file_get_contents($cacheFile), true);
            if ($cached && $cached['window_start'] >= $windowStart) {
                $data = $cached;
            }
        }

        $data['count']++;

        if ($data['count'] > $this->maxRequests) {
            $retryAfter = $data['window_start'] + $this->windowSeconds - $now;
            header("Retry-After: {$retryAfter}");
            return jsonResponse([
                'success' => false,
                'message' => "Слишком много запросов. Повторите через {$retryAfter} секунд."
            ], 429);
        }

        if ($data['count'] === 1) {
            $data['window_start'] = $now;
        }

        file_put_contents($cacheFile, json_encode($data));

        // Add rate limit headers
        header("X-RateLimit-Limit: {$this->maxRequests}");
        header("X-RateLimit-Remaining: " . ($this->maxRequests - $data['count']));
        header("X-RateLimit-Reset: " . ($data['window_start'] + $this->windowSeconds));

        return null;
    }
}
