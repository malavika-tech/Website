<?php
require_once __DIR__ . '/../../helpers.php';

require_role('admin');
$db = get_db();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $where  = '1=1';
    $params = [];

    if (!empty($_GET['search'])) {
        $s = '%' . $_GET['search'] . '%';
        $where .= ' AND m.full_name LIKE ?';
        $params[] = $s;
    }
    if (!empty($_GET['plan'])) {
        $where .= ' AND ms.plan = ?';
        $params[] = $_GET['plan'];
    }
    if (!empty($_GET['status'])) {
        $where .= ' AND ms.status = ?';
        $params[] = $_GET['status'];
    }

    $stmt = $db->prepare(
        "SELECT ms.id, m.full_name, ms.plan, ms.start_date, ms.end_date, ms.status
         FROM memberships ms
         JOIN members m ON ms.member_id = m.user_id
         WHERE $where
         ORDER BY ms.id DESC"
    );
    $stmt->execute($params);
    json_ok($stmt->fetchAll());
}

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if (!$id) json_error('Missing id');
    $db->prepare('DELETE FROM memberships WHERE id = ?')->execute([$id]);
    json_ok();
}

json_error('Method not allowed', 405);
