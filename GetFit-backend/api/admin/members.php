<?php

use App\Services\AdminService;

require_once __DIR__ . '/../../helpers.php';

require_role('admin');

$adminService = new AdminService();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $filters = [];
    if (!empty($_GET['search'])) {
        $filters['search'] = (string)$_GET['search'];
    }
    if (!empty($_GET['duration'])) {
        $filters['duration'] = (string)$_GET['duration'];
    }

    $members = $adminService->getMembers($filters);
    json_ok($members);
}

if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if (!$id) {
        json_error('Missing id');
    }

    $body = read_body();
    body_require($body, 'status');

    $adminService->updateMemberStatus($id, (string)$body['status']);
    json_ok();
}

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if (!$id) {
        json_error('Missing id');
    }

    $adminService->deleteMember($id);
    json_ok();
}

json_error('Method not allowed', 405);
