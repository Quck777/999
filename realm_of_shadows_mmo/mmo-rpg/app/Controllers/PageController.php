<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\BaseController;

class PageController extends BaseController
{
    public function index(array $params): void
    {
        // Serve the SPA entry point
        $indexPath = dirname(__DIR__, 2) . '/public/index.html';
        if (!file_exists($indexPath)) {
            echo '<h1>Realm of Shadows</h1><p>Frontend not found. Place index.html in public/</p>';
            return;
        }
        readfile($indexPath);
    }
}
