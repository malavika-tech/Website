<?php

use App\Services\AuthService;

require_once __DIR__ . '/../../helpers.php';

$authService = new AuthService();
$authService->logout();

json_ok();
