<?php

use App\Services\MemberService;

require_once __DIR__ . '/../../helpers.php';

$me = require_role('member');

$memberService = new MemberService();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $entries = $memberService->getProgressEntries((int)$me['id']);
    json_ok($entries);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = read_body();
    $result = $memberService->addProgressEntry((int)$me['id'], $body);
    json_ok($result);
}

json_error('Method not allowed', 405);
