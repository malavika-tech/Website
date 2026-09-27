<?php

use App\Services\TrainerService;

require_once __DIR__ . '/../../helpers.php';

$me = require_role('trainer');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_error('Method not allowed', 405);
}

$trainerService = new TrainerService();
$stats = $trainerService->getTrainerStats((int)$me['id']);

json_ok($stats);
