<?php
require_once __DIR__ . '/../../helpers.php';

$me = require_role('trainer');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_error('Method not allowed', 405);

$body = read_body();
body_require($body, 'memberId', 'dailyCalories', 'meals');

$db = get_db();
$memberId = (int)$body['memberId'];

// Verify assignment
$check = $db->prepare('SELECT 1 FROM trainer_assignments WHERE member_id = ? AND trainer_id = ?');
$check->execute([$memberId, $me['id']]);
if (!$check->fetch()) json_error('Member not assigned to you', 403);

$db->beginTransaction();

// Replace existing plan
$db->prepare('DELETE FROM diet_plans WHERE member_id = ?')->execute([$memberId]);

$db->prepare('INSERT INTO diet_plans (member_id, trainer_id, daily_calories) VALUES (?,?,?)')
   ->execute([$memberId, $me['id'], (int)$body['dailyCalories']]);
$planId = (int)$db->lastInsertId();

foreach ((array)$body['meals'] as $i => $meal) {
    $db->prepare('INSERT INTO diet_meals (plan_id, meal_time, food, calories, sort_order) VALUES (?,?,?,?,?)')
       ->execute([$planId, $meal['time'] ?? '', $meal['food'] ?? '', (int)($meal['calories'] ?? 0), $i]);
}

$db->commit();
json_ok();
