<?php
require_once __DIR__ . '/../../helpers.php';

$me = require_role('member');
$db = get_db();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $db->prepare('SELECT entry_date, weight, bmi, notes FROM progress_entries WHERE member_id = ? ORDER BY entry_date DESC, id DESC');
    $stmt->execute([$me['id']]);
    json_ok($stmt->fetchAll());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = read_body();
    body_require($body, 'date', 'weight');

    $weight = (float)$body['weight'];
    $height = null;

    // Fetch member height for BMI
    $h = $db->prepare('SELECT height FROM members WHERE user_id = ?');
    $h->execute([$me['id']]);
    $row = $h->fetch();
    if ($row) $height = (float)$row['height'];

    $bmi = ($height > 0) ? calc_bmi($weight, $height) : 0;

    $db->prepare('INSERT INTO progress_entries (member_id, entry_date, weight, bmi, notes) VALUES (?,?,?,?,?)')
       ->execute([$me['id'], $body['date'], $weight, $bmi, $body['notes'] ?? '']);

    // Update current weight in members table
    $db->prepare('UPDATE members SET weight = ? WHERE user_id = ?')->execute([$weight, $me['id']]);

    json_ok(['bmi' => $bmi]);
}

json_error('Method not allowed', 405);
