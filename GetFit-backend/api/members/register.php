<?php

use App\Services\AuthService;

require_once __DIR__ . '/../../helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed', 405);
}

$body = read_body();
body_require($body, 'fullName', 'username', 'password', 'email', 'membershipPlan');

$authService = new AuthService();
$member = $authService->registerMember($body);

json_ok($member);
