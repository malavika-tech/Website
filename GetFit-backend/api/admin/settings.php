<?php
require_once __DIR__ . '/../../helpers.php';

require_role('admin');
$db = get_db();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $rows = $db->query('SELECT setting_key, setting_value FROM settings')->fetchAll();
    $settings = [];
    foreach ($rows as $row) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }

    // Admin profile
    $admin = $db->prepare('SELECT username, email FROM users WHERE id = ?');
    $admin->execute([$_SESSION['user_id']]);
    $adminRow = $admin->fetch();

    json_ok(['settings' => $settings, 'admin' => $adminRow]);
}

if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $body    = read_body();
    $section = $_GET['section'] ?? '';

    if ($section === 'gym') {
        $contact_phone = isset($body['gym_phone']) ? trim($body['gym_phone']) : '';
        if (!preg_match("/^[0-9]{10}$/", $contact_phone)) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid phone number format']);
            exit;
        }

        $keys = ['gym_name', 'gym_address', 'gym_email', 'gym_phone'];
        foreach ($keys as $key) {
            if (isset($body[$key])) {
                $db->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=?')
                   ->execute([$key, $body[$key], $body[$key]]);
            }
        }
        json_ok();
    }

    if ($section === 'profile') {
        if (!empty($body['username'])) {
            $db->prepare('UPDATE users SET username=?, email=? WHERE id=?')
               ->execute([$body['username'], $body['email'] ?? '', $_SESSION['user_id']]);
        }
        json_ok();
    }

    if ($section === 'password') {
        body_require($body, 'currentPassword', 'newPassword');
        $stmt = $db->prepare('SELECT password_hash FROM users WHERE id = ?');
        $stmt->execute([$_SESSION['user_id']]);
        $row = $stmt->fetch();
        if (!$row || !password_verify($body['currentPassword'], $row['password_hash'])) {
            json_error('Current password is incorrect', 401);
        }
        $hash = password_hash($body['newPassword'], PASSWORD_BCRYPT);
        $db->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([$hash, $_SESSION['user_id']]);
        json_ok();
    }

    if ($section === 'system') {
        $keys = ['allow_registrations', 'max_members'];
        foreach ($keys as $key) {
            if (isset($body[$key])) {
                $db->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=?')
                   ->execute([$key, $body[$key], $body[$key]]);
            }
        }
        json_ok();
    }

    json_error('Unknown section');
}

json_error('Method not allowed', 405);
