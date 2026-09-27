<?php

use App\Services\AdminService;

require_once __DIR__ . '/../../helpers.php';

require_role('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed — use POST', 405);
}

$body = read_body();

if (!isset($body['member_id']) || $body['member_id'] === '') {
    json_error('Missing required field: member_id');
}

$adminService = new AdminService();
$adminService->deleteMember($body['member_id']);

echo json_encode([
    'ok'      => true,
    'data'    => null,
    'status'  => 'success',
    'message' => 'Member deleted',
]);
exit;
