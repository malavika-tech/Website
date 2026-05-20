<?php
require_once __DIR__ . '/../../helpers.php';

$me = require_role('trainer');
$db = get_db();

$assigned = $db->prepare('SELECT COUNT(*) FROM trainer_assignments WHERE trainer_id = ?');
$assigned->execute([$me['id']]);
$count = (int)$assigned->fetchColumn();

json_ok([
    'assignedMembers' => $count,
    'todaysSessions'  => 0,
    'goalsAchieved'   => 0,
]);
