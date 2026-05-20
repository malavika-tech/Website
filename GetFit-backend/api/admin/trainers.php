<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../helpers.php';
require_once __DIR__ . '/../../db.php';

require_role('admin');
$db = get_db();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $where  = '1=1';
    $params = [];

    if (!empty($_GET['search'])) {
        $s = '%' . $_GET['search'] . '%';
        $where .= ' AND (t.full_name LIKE ? OR u.email LIKE ?)';
        $params = [$s, $s];
    }
    if (!empty($_GET['status'])) {
        $where .= ' AND t.status = ?';
        $params[] = $_GET['status'];
    }

    $stmt = $db->prepare(
        "SELECT t.user_id AS id, t.full_name, u.email, t.phone, t.specialization, t.status,
                (SELECT COUNT(*) FROM trainer_assignments WHERE trainer_id = t.user_id) AS assignedMembers
         FROM trainers t JOIN users u ON u.id = t.user_id
         WHERE $where ORDER BY t.full_name"
    );
    $stmt->execute($params);
    $trainersList = $stmt->fetchAll();

    // 1. Current month new trainers count
    $curMonthStmt = $db->prepare("SELECT COUNT(*) FROM users WHERE role = 'trainer' AND YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE())");
    $curMonthStmt->execute();
    $newTrainersCount = (int)$curMonthStmt->fetchColumn();

    // 2. Previous month new trainers count
    $prevMonthStmt = $db->prepare("SELECT COUNT(*) FROM users WHERE role = 'trainer' AND YEAR(created_at) = YEAR(CURRENT_DATE - INTERVAL 1 MONTH) AND MONTH(created_at) = MONTH(CURRENT_DATE - INTERVAL 1 MONTH)");
    $prevMonthStmt->execute();
    $prevMonthCount = (int)$prevMonthStmt->fetchColumn();

    // 3. Growth calculation
    if ($prevMonthCount === 0) {
        $growthPercentage = $newTrainersCount > 0 ? 100 : 0;
    } else {
        $growthPercentage = (int)round((($newTrainersCount - $prevMonthCount) / $prevMonthCount) * 100);
    }

    json_ok([
        'trainers' => $trainersList,
        'newTrainersCount' => $newTrainersCount,
        'growthPercentage' => $growthPercentage
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true);
    if (!is_array($body)) {
        $body = [];
    }
    body_require($body, 'fullName', 'email', 'username', 'password');

    $phone = isset($body['phone']) ? trim($body['phone']) : '';
    if (strlen($phone) !== 10) {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(["success" => false, "message" => "Invalid phone number length"]);
        exit;
    }

    $check = $db->prepare('SELECT id FROM users WHERE username = ?');
    $check->execute([$body['username']]);
    if ($check->fetch()) {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(["success" => false, "message" => "Username already taken"]);
        exit;
    }

    $email = isset($body['email']) ? trim($body['email']) : '';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Invalid email format']);
        exit;
    }

    $hash = password_hash($body['password'], PASSWORD_BCRYPT);

    $db->beginTransaction();
    $db->prepare('INSERT INTO users (role, username, password_hash, email) VALUES (?,?,?,?)')
       ->execute(['trainer', $body['username'], $hash, $email]);
    $uid = (int)$db->lastInsertId();

    $db->prepare('INSERT INTO trainers (user_id, full_name, phone, specialization, status) VALUES (?,?,?,?,?)')
       ->execute([
           $uid, $body['fullName'], $body['phone'] ?? '',
           $body['specialization'] ?? '',
           $body['status'] ?? 'active',
       ]);
    $db->commit();

    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'id' => $uid]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if (!$id) json_error('Missing id');
    $body = read_body();

    $db->prepare('UPDATE trainers SET full_name=?, phone=?, specialization=?, status=? WHERE user_id=?')
       ->execute([$body['fullName'] ?? null, $body['phone'] ?? null, $body['specialization'] ?? null, $body['status'] ?? 'active', $id]);

    if (!empty($body['email'])) {
        $db->prepare('UPDATE users SET email=? WHERE id=?')->execute([$body['email'], $id]);
    }
    if (!empty($body['password'])) {
        $hash = password_hash($body['password'], PASSWORD_BCRYPT);
        $db->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([$hash, $id]);
    }

    json_ok();
}

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if (!$id) json_error('Missing id');
    $db->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
    json_ok();
}

json_error('Method not allowed', 405);
