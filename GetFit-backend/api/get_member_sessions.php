<?php

use App\Services\MemberService;

require_once __DIR__ . '/../helpers.php';

$me = require_role('member');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_error('Method not allowed', 405);
}

$memberService = new MemberService();
$sessions = $memberService->getUpcomingSessions((int)$me['id']);

if (empty($sessions)) {
    json_error('No sessions found', 404);
}

json_ok($sessions);
