<?php

use App\Services\AuthService;

require_once __DIR__ . '/../../helpers.php';

$authService = new AuthService();
$user = $authService->getCurrentUser();

json_ok($user);
