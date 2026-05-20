<?php
/**
 * POST /GetFit/GetFit-backend/api/admin/delete_member.php
 *
 * Deletes a member permanently.
 *
 * Request body (JSON):
 *   { "member_id": 5 }          — numeric ID
 *   { "member_id": "M005" }     — formatted display ID also accepted
 *
 * On success: { "ok": true, "data": null }
 * On error:   { "ok": false, "error": "..." }   (with appropriate HTTP status)
 *
 * Deleting from `users` is enough — every child table (members, memberships,
 * trainer_assignments, workout_plans, diet_plans, progress_entries) has
 * ON DELETE CASCADE in the schema, so all related rows are removed automatically.
 */

require_once __DIR__ . '/../../helpers.php';

// Only admins may call this endpoint
require_role('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed — use POST', 405);
}

// ── Parse & validate member_id ───────────────────────────────────────────────
$body = read_body();

if (!isset($body['member_id']) || $body['member_id'] === '') {
    json_error('Missing required field: member_id');
}

// Accept both plain integers and formatted IDs like "M002"
$raw_id = (string) $body['member_id'];
$id     = (int) ltrim($raw_id, 'Mm');   // strip leading M/m then cast

if ($id <= 0) {
    json_error('Invalid member_id — must be a positive integer or formatted ID like "M002"');
}

// ── Verify the member actually exists before deleting ────────────────────────
try {
    $db = get_db();

    $check = $db->prepare('SELECT id FROM users WHERE id = ? AND role = ?');
    $check->execute([$id, 'member']);

    if (!$check->fetch()) {
        http_response_code(404);
        echo json_encode([
            'ok'      => false,
            'error'   => 'Member not found (id=' . $id . ')',
            'status'  => 'error',
            'message' => 'Member not found (id=' . $id . ')',
        ]);
        exit;
    }

    // Delete from users — cascades to all related tables automatically
    $stmt = $db->prepare('DELETE FROM users WHERE id = ? AND role = ?');
    $stmt->execute([$id, 'member']);

    if ($stmt->rowCount() === 0) {
        http_response_code(500);
        echo json_encode([
            'ok'      => false,
            'error'   => 'Delete failed — member may already have been removed',
            'status'  => 'error',
            'message' => 'Delete failed — member may already have been removed',
        ]);
        exit;
    }

    echo json_encode(['ok' => true, 'data' => null, 'status' => 'success', 'message' => 'Member deleted']);

} catch (Exception $e) {
    // Catches PDOException, TypeError, and any other runtime error
    http_response_code(500);
    echo json_encode([
        'ok'      => false,
        'error'   => 'Server error: ' . $e->getMessage(),
        'status'  => 'error',
        'message' => get_class($e) . ': ' . $e->getMessage() . ' in ' . basename($e->getFile()) . ':' . $e->getLine(),
    ]);
}
