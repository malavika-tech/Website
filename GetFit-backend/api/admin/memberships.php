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
    if (!empty($_GET['plan'])) {
        $filters['plan'] = (string)$_GET['plan'];
    }
    if (!empty($_GET['status'])) {
        $filters['status'] = (string)$_GET['status'];
    }

    $memberships = $adminService->getMemberships($filters);
    json_ok($memberships);
}

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if (!$id) {
        json_error('Missing id');
    }

    $adminService->deleteMembership($id);
    json_ok();
}

json_error('Method not allowed', 405);
