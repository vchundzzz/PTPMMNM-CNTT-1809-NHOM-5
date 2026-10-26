<?php
/**
 * FlagShip Local Development Router for PHP Built-in Server
 * Usage: php -S localhost:8000 router.php
 */

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// API route routing: /api/xxx -> api/xxx
if (strpos($uri, '/api/') === 0) {
    $script = __DIR__ . $uri;
    if (file_exists($script) && is_file($script)) {
        require $script;
        exit();
    }
    // If without .php extension
    if (file_exists($script . '.php') && is_file($script . '.php')) {
        require $script . '.php';
        exit();
    }
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'API endpoint not found: ' . $uri]);
    exit();
}

if ($uri === '/apple' || $uri === '/apple.php') {
    header('Content-Type: text/html; charset=utf-8');
    require __DIR__ . '/public/apple.php';
    exit();
}

// Static asset routing: /css/..., /js/..., /index.php
$filePath = __DIR__ . '/public' . $uri;

if ($uri !== '/' && file_exists($filePath) && is_file($filePath)) {
    // Determine content type
    $ext = pathinfo($filePath, PATHINFO_EXTENSION);
    $mimes = [
        'json' => 'application/json; charset=utf-8',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'svg' => 'image/svg+xml',
        'ico' => 'image/x-icon',
        'php' => 'text/html; charset=utf-8'
    ];
    if (isset($mimes[$ext])) {
        header('Content-Type: ' . $mimes[$ext]);
    }
    if ($ext === 'php') {
        require $filePath;
    } else {
        readfile($filePath);
    }
    exit();
}

// Default fallback to public/index.php
header('Content-Type: text/html; charset=utf-8');
require __DIR__ . '/public/index.php';
exit();
