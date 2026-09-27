<?php

use App\Services\AdminService;

require_once __DIR__ . '/../../helpers.php';

require_role('admin');

$adminService = new AdminService();
$adminUserId = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $data = $adminService->getSettings($adminUserId);
    json_ok($data);
}

if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $body = read_body();
    $section = isset($_GET['section']) ? (string)$_GET['section'] : '';

    $adminService->updateSettings($section, $body, $adminUserId);
    json_ok();
}

json_error('Method not allowed', 405);
