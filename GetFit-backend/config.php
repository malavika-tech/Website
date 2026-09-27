<?php
define('DB_HOST', 'localhost');
define('DB_NAME', 'getfit');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

// Register PSR-4 Autoloader
require_once __DIR__ . '/src/autoload.php';
