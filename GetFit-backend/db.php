<?php

use App\Core\Database;

require_once __DIR__ . '/config.php';

/**
 * Backward-compatible helper to retrieve the PDO connection.
 * Delegates to the Singleton Database instance.
 */
function get_db(): PDO
{
    return Database::getInstance()->getConnection();
}
