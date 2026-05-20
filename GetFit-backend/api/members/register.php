<?php
require_once __DIR__ . '/../../helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_error('Method not allowed', 405);

$body = read_body();
body_require($body, 'fullName', 'username', 'password', 'email', 'membershipPlan');

$db = get_db();

// Check username taken
$check = $db->prepare('SELECT id FROM users WHERE username = ?');
$check->execute([$body['username']]);
if ($check->fetch()) json_error('Username already taken');

// Validate email format
if (!filter_var($body['email'], FILTER_VALIDATE_EMAIL)) {
    json_error('Invalid email format');
}

// Fetch current settings
$settingsStmt = $db->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('allow_registrations', 'max_members')");
$settingsRows = $settingsStmt->fetchAll();
$settings = [];
foreach ($settingsRows as $row) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

$allow_registrations = isset($settings['allow_registrations']) ? $settings['allow_registrations'] : '1';
$max_members = isset($settings['max_members']) ? (int)$settings['max_members'] : 500;

// Rule 1: Allow registrations check
if ($allow_registrations == '0' || !$allow_registrations) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Registration is currently disabled by the administrator.']);
    exit;
}

// Rule 2: Max members check
$countStmt = $db->query("SELECT COUNT(*) FROM members");
$currentMembersCount = (int)$countStmt->fetchColumn();

if ($currentMembersCount >= $max_members) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'The gym has reached its maximum member capacity.']);
    exit;
}

// Determine membership end date
$plan = $body['membershipPlan'];
$months = match($plan) {
    'monthly'   => 1,
    'quarterly' => 3,
    'yearly'    => 12,
    default     => 1,
};
$start = date('Y-m-d');
$end   = date('Y-m-d', strtotime("+$months months"));

$hash = password_hash($body['password'], PASSWORD_BCRYPT);

try {
    $db->beginTransaction();

    $db->prepare('INSERT INTO users (role, username, password_hash, email) VALUES (?,?,?,?)')
       ->execute(['member', $body['username'], $hash, $body['email'] ?? '']);

    $uid = (int)$db->lastInsertId();

    $weight = isset($body['weight']) ? (float)$body['weight'] : null;
    $height = isset($body['height']) ? (float)$body['height'] : null;
    $bmi    = ($weight && $height) ? calc_bmi($weight, $height) : null;

    $db->prepare('INSERT INTO members (user_id, full_name, age, gender, phone, height, weight, fitness_goal, registration_date, status)
                  VALUES (?,?,?,?,?,?,?,?,?,?)')
       ->execute([
           $uid,
           $body['fullName']     ?? '',
           $body['age']          ?? null,
           $body['gender']       ?? '',
           $body['phone']        ?? '',
           $height,
           $weight,
           $body['fitnessGoal']  ?? '',
           $start,
           'active',
       ]);

    $db->prepare('INSERT INTO memberships (member_id, plan, start_date, end_date, status) VALUES (?,?,?,?,?)')
       ->execute([$uid, $plan, $start, $end, 'active']);

    // Seed first progress entry from registration weight
    if ($weight && $bmi) {
        $db->prepare('INSERT INTO progress_entries (member_id, entry_date, weight, bmi, notes) VALUES (?,?,?,?,?)')
           ->execute([$uid, $start, $weight, $bmi, 'Initial weight at registration']);
    }

    $db->commit();
} catch (Exception $e) {
    $db->rollBack();
    json_error('Registration failed: ' . $e->getMessage(), 500);
}

// Auto-login
$_SESSION['user_id'] = $uid;
$_SESSION['role']    = 'member';

json_ok(['id' => $uid, 'role' => 'member', 'username' => $body['username']]);
