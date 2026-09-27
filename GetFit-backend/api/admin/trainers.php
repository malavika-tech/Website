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
    if (!empty($_GET['status'])) {
        $filters['status'] = (string)$_GET['status'];
    }

    $result = $adminService->getTrainers($filters);
    json_ok($result);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = read_body();
    body_require($body, 'fullName', 'email', 'username', 'password');

    $uid = $adminService->createTrainer($body);

    header('Content-Type: application/json');
    echo json_encode([
        'ok'      => true,
        'success' => true,
        'id'      => $uid,
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if (!$id) {
        json_error('Missing id');
    }

    $body = read_body();
    $adminService->updateTrainer($id, $body);

    json_ok();
}

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if (!$id) {
        json_error('Missing id');
    }

    $adminService->deleteTrainer($id);
    json_ok();
}

json_error('Method not allowed', 405);
