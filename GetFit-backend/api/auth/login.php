<?php

use App\Services\AuthService;

require_once __DIR__ . '/../../helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed', 405);
}

$body = read_body();
body_require($body, 'username', 'password', 'role');

$authService = new AuthService();
$user = $authService->login($body['username'], $body['password'], $body['role']);

json_ok($user);
