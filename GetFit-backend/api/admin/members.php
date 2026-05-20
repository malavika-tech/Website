<?php
require_once __DIR__ . '/../../helpers.php';

require_role('admin');
$db = get_db();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $where = '1=1';
    $params = [];

    if (!empty($_GET['search'])) {
        $where .= ' AND (m.full_name LIKE ? OR u.email LIKE ? OR u.username LIKE ?)';
        $s = '%' . $_GET['search'] . '%';
        $params = array_merge($params, [$s, $s, $s]);
    }
    if (!empty($_GET['duration'])) {
        $where .= ' AND ms.plan = ?';
        $params[] = $_GET['duration'];
    }

    $stmt = $db->prepare(
        "SELECT m.user_id AS id, m.full_name, u.email, m.phone, ms.plan, ms.start_date, ms.end_date,
                ms.status AS membershipStatus, m.status
         FROM members m
         JOIN users u ON u.id = m.user_id
         LEFT JOIN memberships ms ON ms.member_id = m.user_id
             AND ms.id = (SELECT MAX(id) FROM memberships WHERE member_id = m.user_id)
         WHERE $where
         ORDER BY m.full_name"
    );
    $stmt->execute($params);
    json_ok($stmt->fetchAll());
}

if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $body = read_body();
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if (!$id) json_error('Missing id');
    body_require($body, 'status');
    $db->prepare('UPDATE members SET status = ? WHERE user_id = ?')->execute([$body['status'], $id]);
    json_ok();
}

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if (!$id) json_error('Missing id');
    $db->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
    json_ok();
}

json_error('Method not allowed', 405);
