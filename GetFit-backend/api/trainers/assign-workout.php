<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../helpers.php';

$me = require_role('trainer');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_error('Method not allowed', 405);

$body = read_body();
body_require($body, 'memberId', 'duration', 'daysPerWeek', 'exercises');

$db = get_db();
$memberId = (int)$body['memberId'];

// Verify assignment
$check = $db->prepare('SELECT 1 FROM trainer_assignments WHERE member_id = ? AND trainer_id = ?');
$check->execute([$memberId, $me['id']]);
if (!$check->fetch()) json_error('Member not assigned to you', 403);

$db->beginTransaction();

// Replace existing plan
$db->prepare('DELETE FROM workout_plans WHERE member_id = ?')->execute([$memberId]);

$db->prepare('INSERT INTO workout_plans (member_id, trainer_id, duration, days_per_week) VALUES (?,?,?,?)')
   ->execute([$memberId, $me['id'], $body['duration'], $body['daysPerWeek']]);
$planId = (int)$db->lastInsertId();

$exercises = array_filter((array)$body['exercises'], fn($e) => trim($e) !== '');
foreach (array_values($exercises) as $i => $ex) {
    $db->prepare('INSERT INTO workout_exercises (plan_id, exercise_text, sort_order) VALUES (?,?,?)')
       ->execute([$planId, trim($ex), $i]);
}

$db->commit();

header('Content-Type: application/json');
echo json_encode(['success' => true, 'message' => 'Workout assigned successfully']);
exit;
