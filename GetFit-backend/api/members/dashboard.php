<?php
require_once __DIR__ . '/../../helpers.php';

$me = require_role('member');
$db = get_db();

$stmt = $db->prepare('SELECT weight, height, fitness_goal FROM members WHERE user_id = ?');
$stmt->execute([$me['id']]);
$member = $stmt->fetch();

$latest = $db->prepare('SELECT weight, bmi, entry_date FROM progress_entries WHERE member_id = ? ORDER BY entry_date DESC, id DESC LIMIT 1');
$latest->execute([$me['id']]);
$progress = $latest->fetch();

$weight = $progress ? (float)$progress['weight'] : (float)($member['weight'] ?? 0);
$bmi    = $progress ? (float)$progress['bmi']    : calc_bmi($weight, (float)($member['height'] ?? 0));

json_ok([
    'weight'      => $weight,
    'bmi'         => $bmi,
    'fitnessGoal' => $member['fitness_goal'] ?? '',
    'lastUpdate'  => $progress ? $progress['entry_date'] : null,
]);
