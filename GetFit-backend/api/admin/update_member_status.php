<?php
/**
 * POST /GetFit/GetFit-backend/api/admin/update_member_status.php
 *
 * Activates or deactivates a member.
 *
 * Request body (JSON) — two usage modes:
 *
 *   Mode A — Explicit:  { "member_id": 5, "status": "inactive" }
 *   Mode B — Toggle:    { "member_id": "M005" }   (status is flipped automatically)
 *
 * Accepted status values: "active" | "inactive"
 *
 * On success: { "ok": true, "data": { "new_status": "inactive" } }
 * On error:   { "ok": false, "error": "..." }   (with appropriate HTTP status)
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

$raw_id = (string) $body['member_id'];
$id     = (int) ltrim($raw_id, 'Mm');   // accept plain int or "M002" format

if ($id <= 0) {
    json_error('Invalid member_id — must be a positive integer or formatted ID like "M002"');
}

// ── Validate explicit status value (if provided) ─────────────────────────────
$allowed_statuses = ['active', 'inactive'];

if (isset($body['status'])) {
    $requested_status = strtolower(trim($body['status']));
    if (!in_array($requested_status, $allowed_statuses, true)) {
        json_error('Invalid status value — must be "active" or "inactive"');
    }
}

// ── Fetch current status & determine new status ────────────────────────────
try {
    $db = get_db();

    // Confirm the user exists and is a member
    $check = $db->prepare(
        'SELECT m.status FROM members m
         JOIN users u ON u.id = m.user_id
         WHERE m.user_id = ? AND u.role = ?'
    );
    $check->execute([$id, 'member']);
    $row = $check->fetch();

    if (!$row) {
        http_response_code(404);
        echo json_encode([
            'ok'      => false,
            'error'   => 'Member not found (id=' . $id . ')',
            'status'  => 'error',
            'message' => 'Member not found (id=' . $id . ')',
        ]);
        exit;
    }

    // Mode A — caller provided an explicit status
    // Mode B — toggle the current status
    if (isset($requested_status)) {
        $new_status = $requested_status;
    } else {
        $new_status = ($row['status'] === 'active') ? 'inactive' : 'active';
    }

    // Nothing to do if the status is already the desired value
    if ($new_status === $row['status']) {
        echo json_encode([
            'ok'      => true,
            'data'    => ['new_status' => $new_status],
            'status'  => 'success',
            'message' => 'Status unchanged (already ' . $new_status . ')',
        ]);
        exit;
    }

    // Apply the update using a prepared statement
    $stmt = $db->prepare('UPDATE members SET status = ? WHERE user_id = ?');
    $stmt->execute([$new_status, $id]);

    if ($stmt->rowCount() === 0) {
        http_response_code(500);
        echo json_encode([
            'ok'      => false,
            'error'   => 'Status update failed — no rows were changed',
            'status'  => 'error',
            'message' => 'UPDATE affected 0 rows for member_id=' . $id,
        ]);
        exit;
    }

    echo json_encode([
        'ok'      => true,
        'data'    => ['new_status' => $new_status],
        'status'  => 'success',
        'message' => 'Member status updated to ' . $new_status,
    ]);

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
