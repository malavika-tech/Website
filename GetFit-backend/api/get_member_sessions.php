<?php
require_once __DIR__ . '/../helpers.php';

$me = require_role('member');
$db = get_db();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_error('Method not allowed', 405);
}

try {
    $stmt = $db->prepare('
        SELECT s.session_id, s.trainer_id, s.session_date, s.session_time, s.status, t.full_name AS trainer_name 
        FROM trainer_sessions s
        LEFT JOIN trainers t ON s.trainer_id = t.user_id
        WHERE s.member_id = ? AND s.session_date >= CURDATE()
        ORDER BY s.session_date ASC, s.session_time ASC
    ');
    $stmt->execute([$me['id']]);
    $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($sessions)) {
        json_error('No sessions found', 404);
    }

    json_ok($sessions);

} catch (PDOException $e) {
    json_error('Database error: ' . $e->getMessage(), 500);
}
