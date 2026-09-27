<?php

use App\Services\MemberService;

require_once __DIR__ . '/../../helpers.php';

$me = require_role('member');

$memberService = new MemberService();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $profile = $memberService->getProfile((int)$me['id']);
    json_ok($profile);
}

if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $body = read_body();
    $memberService->updateProfile((int)$me['id'], $body);
    json_ok();
}

json_error('Method not allowed', 405);
