<?php

namespace App\Models;

use App\Core\Database;

/**
 * User Domain Model
 * Represents an authenticated user in the GetFit system.
 */
class User
{
    protected ?int $id;
    protected string $role;
    protected string $username;
    protected string $passwordHash;
    protected ?string $email;
    protected ?string $createdAt;

    public function __construct(
        string $role,
        string $username,
        string $passwordHash,
        ?string $email = null,
        ?int $id = null,
        ?string $createdAt = null
    ) {
        $this->role = $role;
        $this->username = $username;
        $this->passwordHash = $passwordHash;
        $this->email = $email;
        $this->id = $id;
        $this->createdAt = $createdAt;
    }

    // --- Getters ---

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRole(): string
    {
        return $this->role;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function getPasswordHash(): string
    {
        return $this->passwordHash;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function getCreatedAt(): ?string
    {
        return $this->createdAt;
    }

    // --- Setters ---

    public function setRole(string $role): void
    {
        $this->role = $role;
    }

    public function setUsername(string $username): void
    {
        $this->username = trim($username);
    }

    public function setEmail(?string $email): void
    {
        $this->email = $email !== null ? trim($email) : null;
    }

    public function setPlainPassword(string $plainPassword): void
    {
        $this->passwordHash = password_hash($plainPassword, PASSWORD_BCRYPT);
    }

    public function setPasswordHash(string $hash): void
    {
        $this->passwordHash = $hash;
    }

    // --- Authentication & Logic ---

    public function verifyPassword(string $plainPassword): bool
    {
        return password_verify($plainPassword, $this->passwordHash);
    }

    // --- Database Operations ---

    public static function fromArray(array $data): self
    {
        return new self(
            $data['role'] ?? 'member',
            $data['username'] ?? '',
            $data['password_hash'] ?? '',
            $data['email'] ?? null,
            isset($data['id']) ? (int)$data['id'] : null,
            $data['created_at'] ?? null
        );
    }

    public static function findById(int $id): ?self
    {
        $db = Database::getInstance();
        $row = $db->fetchOne('SELECT * FROM users WHERE id = ?', [$id]);
        return $row ? self::fromArray($row) : null;
    }

    public static function findByUsername(string $username): ?self
    {
        $db = Database::getInstance();
        $row = $db->fetchOne('SELECT * FROM users WHERE username = ?', [trim($username)]);
        return $row ? self::fromArray($row) : null;
    }

    public static function findByUsernameAndRole(string $username, string $role): ?self
    {
        $db = Database::getInstance();
        $row = $db->fetchOne('SELECT * FROM users WHERE username = ? AND role = ?', [trim($username), $role]);
        return $row ? self::fromArray($row) : null;
    }

    public static function existsByUsername(string $username, ?int $excludeId = null): bool
    {
        $db = Database::getInstance();
        if ($excludeId !== null) {
            $count = (int)$db->fetchColumn('SELECT COUNT(*) FROM users WHERE username = ? AND id != ?', [trim($username), $excludeId]);
        } else {
            $count = (int)$db->fetchColumn('SELECT COUNT(*) FROM users WHERE username = ?', [trim($username)]);
        }
        return $count > 0;
    }

    public function save(): bool
    {
        $db = Database::getInstance();

        if ($this->id !== null) {
            return $db->execute(
                'UPDATE users SET role = ?, username = ?, password_hash = ?, email = ? WHERE id = ?',
                [$this->role, $this->username, $this->passwordHash, $this->email, $this->id]
            );
        }

        $success = $db->execute(
            'INSERT INTO users (role, username, password_hash, email) VALUES (?, ?, ?, ?)',
            [$this->role, $this->username, $this->passwordHash, $this->email]
        );

        if ($success) {
            $this->id = (int)$db->lastInsertId();
            return true;
        }

        return false;
    }

    public function delete(): bool
    {
        if ($this->id === null) {
            return false;
        }
        $db = Database::getInstance();
        return $db->execute('DELETE FROM users WHERE id = ?', [$this->id]);
    }

    public function toArray(): array
    {
        return [
            'id'         => $this->id,
            'role'       => $this->role,
            'username'   => $this->username,
            'email'      => $this->email,
            'created_at' => $this->createdAt,
        ];
    }
}
