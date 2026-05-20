<?php
define('DB_HOST', 'localhost');
define('DB_NAME', 'getfit');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

session_start();

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
