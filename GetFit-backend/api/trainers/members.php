<?php
require_once __DIR__ . '/../../helpers.php';

$me = require_role('trainer');
$db = get_db();

$stmt = $db->prepare(
    'SELECT m.user_id AS id, m.full_name, m.fitness_goal, m.weight, m.height, ms.plan, ms.status AS membershipStatus
     FROM trainer_assignments ta
     JOIN members m    ON ta.member_id = m.user_id
     LEFT JOIN memberships ms ON ms.member_id = m.user_id AND ms.id = (
         SELECT MAX(id) FROM memberships WHERE member_id = m.user_id
     )
     WHERE ta.trainer_id = ?
     ORDER BY m.full_name'
);
$stmt->execute([$me['id']]);
$members = $stmt->fetchAll();

foreach ($members as &$member) {
    $member['bmi'] = ($member['weight'] && $member['height'])
        ? calc_bmi((float)$member['weight'], (float)$member['height'])
        : null;
}

json_ok($members);
