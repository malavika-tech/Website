<?php
require_once __DIR__ . '/../../helpers.php';

$me = require_role('member');
$db = get_db();

$stmt = $db->prepare('SELECT * FROM memberships WHERE member_id = ? ORDER BY id DESC LIMIT 1');
$stmt->execute([$me['id']]);
$row = $stmt->fetch();

if (!$row) json_error('No membership found', 404);

// Update status if expired
if ($row['status'] === 'active' && $row['end_date'] < date('Y-m-d')) {
    $db->prepare('UPDATE memberships SET status=? WHERE id=?')->execute(['expired', $row['id']]);
    $row['status'] = 'expired';
}

$start    = new DateTime($row['start_date']);
$end      = new DateTime($row['end_date']);
$today    = new DateTime();
$total    = (int)$start->diff($end)->days;
$remaining = max(0, (int)$today->diff($end)->days);

json_ok([
    'plan'       => $row['plan'],
    'startDate'  => $row['start_date'],
    'endDate'    => $row['end_date'],
    'status'     => $row['status'],
    'totalDays'  => $total,
    'daysLeft'   => $remaining,
]);
