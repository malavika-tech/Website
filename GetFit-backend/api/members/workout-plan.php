<?php
require_once __DIR__ . '/../../helpers.php';

$me = require_role('member');
$db = get_db();

$plan = $db->prepare('SELECT * FROM workout_plans WHERE member_id = ? ORDER BY id DESC LIMIT 1');
$plan->execute([$me['id']]);
$row = $plan->fetch();

if (!$row) json_ok(null);

$ex = $db->prepare('SELECT exercise_text FROM workout_exercises WHERE plan_id = ? ORDER BY sort_order');
$ex->execute([$row['id']]);
$exercises = array_column($ex->fetchAll(), 'exercise_text');

json_ok([
    'duration'    => $row['duration'],
    'daysPerWeek' => $row['days_per_week'],
    'exercises'   => $exercises,
    'assignedAt'  => $row['assigned_at'],
]);
