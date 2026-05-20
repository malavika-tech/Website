<?php
require_once __DIR__ . '/../../helpers.php';

$me = require_role('member');
$db = get_db();

$stmt = $db->prepare(
    'SELECT t.full_name, t.specialization, u.email, t.phone
     FROM trainer_assignments ta
     JOIN trainers t ON ta.trainer_id = t.user_id
     JOIN users u    ON u.id = ta.trainer_id
     WHERE ta.member_id = ?'
);
$stmt->execute([$me['id']]);
$trainer = $stmt->fetch();

json_ok($trainer ?: null);
