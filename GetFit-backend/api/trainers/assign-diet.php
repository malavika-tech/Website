<?php

use App\Services\TrainerService;

require_once __DIR__ . '/../../helpers.php';

$me = require_role('trainer');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed', 405);
}

$body = read_body();

$trainerService = new TrainerService();
$trainerService->assignDiet((int)$me['id'], $body);

json_ok(['message' => 'Diet plan assigned successfully']);
