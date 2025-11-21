<?php
// Simple router for PHP built-in server to work with this app structure.
// Serves existing files directly; otherwise bootstraps the application via index.php.

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$filePath = __DIR__ . $uri;

if ($uri !== '/' && file_exists($filePath) && is_file($filePath)) {
    return false; // Serve the requested resource as-is
}

require_once __DIR__ . '/index.php';