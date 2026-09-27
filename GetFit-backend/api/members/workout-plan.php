<?php

use App\Services\MemberService;

require_once __DIR__ . '/../../helpers.php';

$me = require_role('member');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_error('Method not allowed', 405);
}

$memberService = new MemberService();
$plan = $memberService->getWorkoutPlan((int)$me['id']);

json_ok($plan);
