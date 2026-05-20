<?php
require_once __DIR__ . '/../../helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_error('Method not allowed', 405);

$body = read_body();
body_require($body, 'username', 'password', 'role');

$db   = get_db();
$stmt = $db->prepare('SELECT * FROM users WHERE username = ? AND role = ?');
$stmt->execute([$body['username'], $body['role']]);
$user = $stmt->fetch();

if (!$user || !password_verify($body['password'], $user['password_hash'])) {
    json_error('Invalid username or password', 401);
}

// For trainers check status
if ($user['role'] === 'trainer') {
    $t = $db->prepare('SELECT status FROM trainers WHERE user_id = ?');
    $t->execute([$user['id']]);
    $trainer = $t->fetch();
    if ($trainer && $trainer['status'] === 'inactive') {
        json_error('Your account has been deactivated. Contact admin.', 403);
    }
}

// For members check status
if ($user['role'] === 'member') {
    $m = $db->prepare('SELECT status FROM members WHERE user_id = ?');
    $m->execute([$user['id']]);
    $member = $m->fetch();
    if ($member && $member['status'] === 'inactive') {
        json_error('Your account has been deactivated. Contact admin.', 403);
    }
}

$_SESSION['user_id'] = $user['id'];
$_SESSION['role']    = $user['role'];

json_ok([
    'id'       => $user['id'],
    'role'     => $user['role'],
    'username' => $user['username'],
    'email'    => $user['email'],
]);
