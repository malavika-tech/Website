<?php
require_once __DIR__ . '/../../helpers.php';

$me = require_role('member');
$db = get_db();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $db->prepare(
        'SELECT u.username, u.email, m.full_name, m.age, m.gender, m.phone, m.height, m.weight, m.fitness_goal, m.registration_date, m.status, ms.plan AS membership_plan
         FROM users u 
         JOIN members m ON u.id = m.user_id 
         LEFT JOIN memberships ms ON m.user_id = ms.member_id AND ms.status = \'active\'
         WHERE u.id = ?'
    );
    $stmt->execute([$me['id']]);
    $row = $stmt->fetch();
    if (!$row) json_error('Member not found', 404);
    $row['bmi'] = ($row['weight'] && $row['height']) ? calc_bmi((float)$row['weight'], (float)$row['height']) : null;
    json_ok($row);
}

if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $body = read_body();
    $weight = isset($body['weight']) ? (float)$body['weight'] : null;
    $db->prepare(
        'UPDATE members SET full_name=?, age=?, height=?, weight=?, fitness_goal=? WHERE user_id=?'
    )->execute([
        $body['fullName']    ?? null,
        $body['age']         ?? null,
        $body['height']      ?? null,
        $weight,
        $body['fitnessGoal'] ?? null,
        $me['id'],
    ]);

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if ($weight !== null) {
        $_SESSION['weight'] = $weight;
    }
    json_ok();
}

json_error('Method not allowed', 405);
