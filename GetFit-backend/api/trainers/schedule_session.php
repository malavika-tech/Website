<?php

use App\Services\TrainerService;

require_once __DIR__ . '/../../helpers.php';

$me = require_role('trainer');

$trainerService = new TrainerService();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = read_body();
    $result = $trainerService->scheduleSession((int)$me['id'], $body);
    json_ok($result);
}

if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $body = read_body();
    $result = $trainerService->updateSessionStatus((int)$me['id'], $body);
    json_ok($result);
}

json_error('Method not allowed', 405);
