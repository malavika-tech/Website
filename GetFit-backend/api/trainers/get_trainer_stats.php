<?php
require_once __DIR__ . '/../../helpers.php';

$me = require_role('trainer');
$db = get_db();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_error('Method not allowed', 405);
}

// 1. Count assigned members
$assignedStmt = $db->prepare('SELECT COUNT(*) FROM trainer_assignments WHERE trainer_id = ?');
$assignedStmt->execute([$me['id']]);
$assignedMembers = (int)$assignedStmt->fetchColumn();

// 2. Count today's sessions
$sessionsTodayStmt = $db->prepare('SELECT COUNT(*) FROM trainer_sessions WHERE trainer_id = ? AND session_date = CURDATE()');
$sessionsTodayStmt->execute([$me['id']]);
$todaysSessions = (int)$sessionsTodayStmt->fetchColumn();

// 2b. Count today's scheduled sessions
$scheduledTodayStmt = $db->prepare("SELECT COUNT(*) FROM trainer_sessions WHERE trainer_id = ? AND session_date = CURDATE() AND status = 'scheduled'");
$scheduledTodayStmt->execute([$me['id']]);
$todaysScheduled = (int)$scheduledTodayStmt->fetchColumn();

// 3. Count goals achieved (progress reviews / progress entries from assigned members)
$goalsStmt = $db->prepare('
    SELECT COUNT(*) 
    FROM progress_entries pe 
    JOIN trainer_assignments ta ON pe.member_id = ta.member_id 
    WHERE ta.trainer_id = ?
');
$goalsStmt->execute([$me['id']]);
$goalsAchieved = (int)$goalsStmt->fetchColumn();

// 4. Fetch upcoming sessions list (and recent completed/cancelled for convenience, e.g. last 30 days)
$sessionsListStmt = $db->prepare('
    SELECT s.session_id, s.member_id, s.session_date, s.session_time, s.status, m.full_name AS member_name 
    FROM trainer_sessions s
    JOIN members m ON s.member_id = m.user_id
    WHERE s.trainer_id = ?
    ORDER BY s.session_date DESC, s.session_time DESC
');
$sessionsListStmt->execute([$me['id']]);
$sessions = $sessionsListStmt->fetchAll();

json_ok([
    'assignedMembers' => $assignedMembers,
    'todaysSessions'  => $todaysSessions,
    'todaysScheduled' => $todaysScheduled,
    'goalsAchieved'   => $goalsAchieved,
    'sessions'        => $sessions
]);
