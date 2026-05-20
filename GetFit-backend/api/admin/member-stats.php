<?php
/**
 * GET /GetFit/GetFit-backend/api/admin/member-stats.php
 *
 * Returns aggregate member statistics for the admin dashboard cards:
 *   - total:        All members ever registered
 *   - active:       Members with status = 'active'
 *   - pending:      Members with status = 'inactive'  (awaiting activation)
 *   - new_this_month: Members whose users.created_at falls in the current calendar month
 */

require_once __DIR__ . '/../../helpers.php';

require_role('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_error('Method not allowed', 405);
}

try {
    $db = get_db();

    /* --- Total members -------------------------------------------------- */
    $total = (int) $db->query('SELECT COUNT(*) FROM members')->fetchColumn();

    /* --- Active members -------------------------------------------------- */
    $active = (int) $db
        ->query("SELECT COUNT(*) FROM members WHERE status = 'active'")
        ->fetchColumn();

    /* --- Pending (inactive) members ------------------------------------- */
    $pending = (int) $db
        ->query("SELECT COUNT(*) FROM members WHERE status = 'inactive'")
        ->fetchColumn();

    /* --- New this month -------------------------------------------------- */
    // We join to `users` so we can use the account-creation timestamp.
    $stmt = $db->prepare(
        "SELECT COUNT(*)
         FROM members m
         JOIN users u ON u.id = m.user_id
         WHERE YEAR(u.created_at)  = YEAR(NOW())
           AND MONTH(u.created_at) = MONTH(NOW())"
    );
    $stmt->execute();
    $new_this_month = (int) $stmt->fetchColumn();

    json_ok([
        'total'          => $total,
        'active'         => $active,
        'pending'        => $pending,
        'new_this_month' => $new_this_month,
    ]);

} catch (PDOException $e) {
    json_error('Database error: ' . $e->getMessage(), 500);
}
