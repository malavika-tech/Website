<?php
require_once __DIR__ . '/../../helpers.php';

$me = require_role('trainer');
$db = get_db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = read_body();
    body_require($body, 'member_id', 'session_date', 'session_time');

    $member_id = (int)$body['member_id'];
    $session_date = $body['session_date'];
    $session_time = $body['session_time'];
    $status = $body['status'] ?? 'scheduled';

    // Verify member is assigned to this trainer
    $check = $db->prepare('SELECT 1 FROM trainer_assignments WHERE member_id = ? AND trainer_id = ?');
    $check->execute([$member_id, $me['id']]);
    if (!$check->fetch()) {
        json_error('Selected member is not assigned to you.');
    }

    $stmt = $db->prepare('INSERT INTO trainer_sessions (trainer_id, member_id, session_date, session_time, status) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$me['id'], $member_id, $session_date, $session_time, $status]);

    json_ok([
        'session_id' => $db->lastInsertId(),
        'message' => 'Session scheduled successfully'
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $body = read_body();
    body_require($body, 'session_id', 'status');
    
    $session_id = (int)$body['session_id'];
    $status = $body['status'];

    // Verify ownership of session
    $check = $db->prepare('SELECT 1 FROM trainer_sessions WHERE session_id = ? AND trainer_id = ?');
    $check->execute([$session_id, $me['id']]);
    if (!$check->fetch()) {
        json_error('Session not found or not assigned to you.', 404);
    }

    $stmt = $db->prepare('UPDATE trainer_sessions SET status = ? WHERE session_id = ?');
    $stmt->execute([$status, $session_id]);

    json_ok([
        'message' => 'Session updated successfully'
    ]);
}

json_error('Method not allowed', 405);
