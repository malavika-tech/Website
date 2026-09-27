<?php

use App\Services\TrainerService;

require_once __DIR__ . '/../../helpers.php';

$me = require_role('trainer');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_error('Method not allowed', 405);
}

$memberId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$memberId) {
    json_error('Missing member id');
}

$trainerService = new TrainerService();
$member = $trainerService->getMemberDetails((int)$me['id'], $memberId);

json_ok($member);
