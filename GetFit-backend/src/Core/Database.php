<?php

namespace App\Core;

use PDO;
use PDOStatement;
use Exception;

/**
 * Singleton Database Connection & Query Wrapper using PDO.
 */
class Database
{
    private static ?Database $instance = null;
    private ?PDO $pdo = null;

    /**
     * Private constructor to enforce Singleton pattern.
     */
    private function __construct()
    {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            defined('DB_HOST') ? DB_HOST : 'localhost',
            defined('DB_NAME') ? DB_NAME : 'getfit',
            defined('DB_CHARSET') ? DB_CHARSET : 'utf8mb4'
        );

        $user = defined('DB_USER') ? DB_USER : 'root';
        $pass = defined('DB_PASS') ? DB_PASS : '';

        $this->pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }

    /**
     * Prevent cloning of the singleton instance.
     */
    private function __clone()
    {
    }

    /**
     * Prevent unserialization of the singleton instance.
     *
     * @throws Exception
     */
    public function __wakeup()
    {
        throw new Exception("Cannot unserialize singleton");
    }

    /**
     * Retrieve the singleton Database instance.
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Get the active PDO connection.
     */
    public function getConnection(): PDO
    {
        return $this->pdo;
    }

    /**
     * Prepare a SQL statement.
     */
    public function prepare(string $sql): PDOStatement
    {
        return $this->pdo->prepare($sql);
    }

    /**
     * Execute a prepared query with bound parameters and return the PDOStatement.
     *
     * @param string $sql
     * @param array<int|string, mixed> $params
     * @return PDOStatement
     */
    public function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * Execute a query and return a single associative array row or null.
     *
     * @param string $sql
     * @param array<int|string, mixed> $params
     * @return array<string, mixed>|null
     */
    public function fetchOne(string $sql, array $params = []): ?array
    {
        $stmt = $this->query($sql, $params);
        $result = $stmt->fetch();
        return $result !== false ? $result : null;
    }

    /**
     * Execute a query and return all matching rows as an array of associative arrays.
     *
     * @param string $sql
     * @param array<int|string, mixed> $params
     * @return array<int, array<string, mixed>>
     */
    public function fetchAll(string $sql, array $params = []): array
    {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }

    /**
     * Execute a query and return a single scalar value.
     *
     * @param string $sql
     * @param array<int|string, mixed> $params
     * @return mixed
     */
    public function fetchColumn(string $sql, array $params = []): mixed
    {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchColumn();
    }

    /**
     * Execute an INSERT/UPDATE/DELETE query and return boolean success.
     *
     * @param string $sql
     * @param array<int|string, mixed> $params
     * @return bool
     */
    public function execute(string $sql, array $params = []): bool
    {
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Get the ID of the last inserted row.
     */
    public function lastInsertId(?string $name = null): string|int
    {
        $id = $this->pdo->lastInsertId($name);
        return is_numeric($id) ? (int)$id : $id;
    }

    /**
     * Begin a database transaction.
     */
    public function beginTransaction(): bool
    {
        return $this->pdo->beginTransaction();
    }

    /**
     * Commit the current transaction.
     */
    public function commit(): bool
    {
        return $this->pdo->commit();
    }

    /**
     * Rollback the current transaction.
     */
    public function rollBack(): bool
    {
        return $this->pdo->rollBack();
    }

    /**
     * Check if currently inside an active transaction.
     */
    public function inTransaction(): bool
    {
        return $this->pdo->inTransaction();
    }
}
