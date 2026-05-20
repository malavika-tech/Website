<?php
require_once __DIR__ . '/db.php';

function json_ok($data = null): never {
    echo json_encode(['ok' => true, 'data' => $data]);
    exit;
}

function json_error(string $message, int $status = 400): never {
    http_response_code($status);
    echo json_encode(['ok' => false, 'error' => $message]);
    exit;
}

function require_role(string ...$roles): array {
    if (empty($_SESSION['user_id'])) {
        json_error('Not logged in', 401);
    }
    if (!in_array($_SESSION['role'], $roles, true)) {
        json_error('Forbidden', 403);
    }
    return ['id' => $_SESSION['user_id'], 'role' => $_SESSION['role']];
}

function read_body(): array {
    $raw = file_get_contents('php://input');
    if (!$raw) return [];
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function body_require(array $data, string ...$keys): void {
    foreach ($keys as $key) {
        if (!isset($data[$key]) || $data[$key] === '') {
            json_error("Missing field: $key");
        }
    }
}

function calc_bmi(float $weight_kg, float $height_cm): float {
    if ($height_cm <= 0) return 0;
    $h = $height_cm / 100;
    return round($weight_kg / ($h * $h), 1);
}
