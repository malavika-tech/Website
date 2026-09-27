<?php

use App\Services\AdminService;

require_once __DIR__ . '/../../helpers.php';

require_role('admin');

$adminService = new AdminService();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $data = $adminService->getAssignments();
    json_ok($data);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = read_body();
    body_require($body, 'memberId', 'trainerId');

    $adminService->assignTrainer((int)$body['memberId'], (int)$body['trainerId']);
    json_ok();
}

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $memberId = isset($_GET['memberId']) ? (int)$_GET['memberId'] : 0;
    if (!$memberId) {
        json_error('Missing memberId');
    }

    $adminService->removeAssignment($memberId);
    json_ok();
}

json_error('Method not allowed', 405);
