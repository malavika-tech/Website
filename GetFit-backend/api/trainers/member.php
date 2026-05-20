<?php
require_once __DIR__ . '/../../helpers.php';

$me = require_role('trainer');
$db = get_db();

$memberId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$memberId) json_error('Missing member id');

// Ensure this member is assigned to this trainer
$check = $db->prepare('SELECT 1 FROM trainer_assignments WHERE member_id = ? AND trainer_id = ?');
$check->execute([$memberId, $me['id']]);
if (!$check->fetch()) json_error('Member not assigned to you', 403);

$stmt = $db->prepare(
    'SELECT m.user_id AS id, m.full_name, m.age, m.gender, m.phone, m.height, m.weight,
            m.fitness_goal, m.registration_date, u.email
     FROM members m JOIN users u ON u.id = m.user_id
     WHERE m.user_id = ?'
);
$stmt->execute([$memberId]);
$member = $stmt->fetch();
if (!$member) json_error('Member not found', 404);

$member['bmi'] = ($member['weight'] && $member['height'])
    ? calc_bmi((float)$member['weight'], (float)$member['height'])
    : null;

$progress = $db->prepare('SELECT entry_date, weight, bmi, notes FROM progress_entries WHERE member_id = ? ORDER BY entry_date DESC LIMIT 10');
$progress->execute([$memberId]);
$member['progressHistory'] = $progress->fetchAll();

json_ok($member);
