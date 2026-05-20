<?php
require_once __DIR__ . '/../../helpers.php';

if (empty($_SESSION['user_id'])) {
    json_error('Not logged in', 401);
}

json_ok([
    'id'   => $_SESSION['user_id'],
    'role' => $_SESSION['role'],
]);
