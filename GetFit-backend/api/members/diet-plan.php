<?php
require_once __DIR__ . '/../../helpers.php';

$me = require_role('member');
$db = get_db();

$plan = $db->prepare('SELECT * FROM diet_plans WHERE member_id = ? ORDER BY id DESC LIMIT 1');
$plan->execute([$me['id']]);
$row = $plan->fetch();

if (!$row) json_ok(null);

$meals = $db->prepare('SELECT meal_time, food, calories FROM diet_meals WHERE plan_id = ? ORDER BY sort_order');
$meals->execute([$row['id']]);

json_ok([
    'dailyCalories' => $row['daily_calories'],
    'meals'         => $meals->fetchAll(),
    'assignedAt'    => $row['assigned_at'],
]);
