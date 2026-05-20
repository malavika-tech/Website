<?php
require_once __DIR__ . '/../../helpers.php';

require_role('admin');
$db = get_db();

$total    = (int)$db->query("SELECT COUNT(*) FROM members")->fetchColumn();
$active   = (int)$db->query("SELECT COUNT(*) FROM members WHERE status='active'")->fetchColumn();
$inactive = (int)$db->query("SELECT COUNT(*) FROM members WHERE status='inactive'")->fetchColumn();
$trainers = (int)$db->query("SELECT COUNT(*) FROM trainers")->fetchColumn();

// Active memberships (not expired)
$activeMem = (int)$db->query("SELECT COUNT(*) FROM memberships WHERE status='active' AND end_date >= CURDATE()")->fetchColumn();
$expiredMem = (int)$db->query("SELECT COUNT(*) FROM memberships WHERE status='expired' OR end_date < CURDATE()")->fetchColumn();

json_ok([
    'totalMembers'    => $total,
    'activeMembers'   => $active,
    'inactiveMembers' => $inactive,
    'totalTrainers'   => $trainers,
    'activeMemberships'  => $activeMem,
    'expiredMemberships' => $expiredMem,
]);
