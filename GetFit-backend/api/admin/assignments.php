<?php
require_once __DIR__ . '/../../helpers.php';

require_role('admin');
$db = get_db();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $members = $db->query(
        "SELECT m.user_id AS id, m.full_name,
                (SELECT trainer_id FROM trainer_assignments WHERE member_id = m.user_id) AS trainer_id,
                (SELECT plan FROM memberships WHERE member_id = m.user_id ORDER BY id DESC LIMIT 1) AS plan
         FROM members m WHERE m.status = 'active' ORDER BY m.full_name"
    )->fetchAll();

    $trainers = $db->query(
        "SELECT t.user_id AS id, t.full_name, t.specialization
         FROM trainers t WHERE t.status = 'active' ORDER BY t.full_name"
    )->fetchAll();

    json_ok(['members' => $members, 'trainers' => $trainers]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = read_body();
    body_require($body, 'memberId', 'trainerId');

    $db->prepare(
        'INSERT INTO trainer_assignments (member_id, trainer_id) VALUES (?,?)
         ON DUPLICATE KEY UPDATE trainer_id = VALUES(trainer_id), assigned_at = CURRENT_TIMESTAMP'
    )->execute([(int)$body['memberId'], (int)$body['trainerId']]);

    json_ok();
}

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $memberId = isset($_GET['memberId']) ? (int)$_GET['memberId'] : 0;
    if (!$memberId) json_error('Missing memberId');
    $db->prepare('DELETE FROM trainer_assignments WHERE member_id = ?')->execute([$memberId]);
    json_ok();
}

json_error('Method not allowed', 405);
