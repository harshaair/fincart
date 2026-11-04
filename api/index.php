<?php
/**
 * FinCart - Vercel Serverless Front Controller Router
 * Routes incoming web requests to the appropriate PHP script or static asset
 * while preserving directory context and relative include/require statements.
 */

$root = dirname(__DIR__);

// Normalize requested URI path
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($requestUri, PHP_URL_PATH);
$path = ltrim($path, '/');

// Default empty root request to index.html
if ($path === '' || $path === '/') {
    $targetFile = $root . '/index.html';
    if (file_exists($targetFile)) {
        header('Content-Type: text/html; charset=UTF-8');
        readfile($targetFile);
        exit;
    }
}

$targetFile = $root . '/' . $path;

// Support clean URLs (e.g. /login -> /login.php)
if (!file_exists($targetFile) && file_exists($targetFile . '.php')) {
    $targetFile .= '.php';
    $path .= '.php';
}

// Support directory indexes (e.g. /admin/ -> /admin/dashboard.php)
if (is_dir($targetFile)) {
    if (file_exists($targetFile . '/index.php')) {
        $targetFile .= '/index.php';
    } elseif (file_exists($targetFile . '/dashboard.php')) {
        $targetFile .= '/dashboard.php';
    }
}

$realTarget = realpath($targetFile);
$realRoot = realpath($root);

// Security: Prevent directory traversal outside project root
if (!$realTarget || !str_starts_with($realTarget, $realRoot) || !is_file($realTarget)) {
    http_response_code(404);
    echo "<!DOCTYPE html><html><head><title>404 Not Found</title></head><body style='font-family:sans-serif;text-align:center;padding:50px;'>";
    echo "<h1>404 - Page Not Found</h1><p>The requested page <code>" . htmlspecialchars($path) . "</code> does not exist.</p>";
    echo "<a href='/'>Return to FinCart Home</a></body></html>";
    exit;
}

$ext = strtolower(pathinfo($realTarget, PATHINFO_EXTENSION));

// Serve static assets with appropriate MIME types
if ($ext !== 'php') {
    $mimes = [
        'html' => 'text/html; charset=UTF-8',
        'css'  => 'text/css; charset=UTF-8',
        'js'   => 'application/javascript; charset=UTF-8',
        'json' => 'application/json',
        'png'  => 'image/png',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif'  => 'image/gif',
        'svg'  => 'image/svg+xml',
        'ico'  => 'image/x-icon',
        'txt'  => 'text/plain; charset=UTF-8',
        'pdf'  => 'application/pdf'
    ];
    if (isset($mimes[$ext])) {
        header('Content-Type: ' . $mimes[$ext]);
    }
    readfile($realTarget);
    exit;
}

// Change working directory to target script's directory so relative includes succeed
chdir(dirname($realTarget));

// Update execution environment server variables
$_SERVER['SCRIPT_FILENAME'] = $realTarget;
$_SERVER['SCRIPT_NAME'] = '/' . ltrim(str_replace('\\', '/', substr($realTarget, strlen($realRoot))), '/');
$_SERVER['PHP_SELF'] = $_SERVER['SCRIPT_NAME'];

// Execute the PHP application script
require $realTarget;

