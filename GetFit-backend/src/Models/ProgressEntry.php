<?php

namespace App\Models;

use App\Core\Database;

/**
 * ProgressEntry Domain Model
 * Tracks daily/weekly weight and BMI progress for members.
 */
class ProgressEntry
{
    private ?int $id;
    private int $memberId;
    private string $entryDate;
    private float $weight;
    private ?float $bmi;
    private ?string $notes;
    private ?string $createdAt;

    public function __construct(
        int $memberId,
        string $entryDate,
        float $weight,
        ?float $bmi = null,
        ?string $notes = null,
        ?int $id = null,
        ?string $createdAt = null
    ) {
        $this->memberId = $memberId;
        $this->entryDate = $entryDate;
        $this->weight = $weight;
        $this->bmi = $bmi;
        $this->notes = $notes;
        $this->id = $id;
        $this->createdAt = $createdAt;
    }

    // --- Getters ---

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMemberId(): int
    {
        return $this->memberId;
    }

    public function getEntryDate(): string
    {
        return $this->entryDate;
    }

    public function getWeight(): float
    {
        return $this->weight;
    }

    public function getBmi(): ?float
    {
        return $this->bmi;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function getCreatedAt(): ?string
    {
        return $this->createdAt;
    }

    // --- Persistence & Queries ---

    public static function fromArray(array $data): self
    {
        return new self(
            (int)$data['member_id'],
            $data['entry_date'],
            (float)$data['weight'],
            isset($data['bmi']) && $data['bmi'] !== null ? (float)$data['bmi'] : null,
            $data['notes'] ?? null,
            isset($data['id']) ? (int)$data['id'] : null,
            $data['created_at'] ?? null
        );
    }

    public static function log(int $memberId, string $date, float $weight, ?float $bmi = null, ?string $notes = null): self
    {
        $db = Database::getInstance();
        $db->execute(
            'INSERT INTO progress_entries (member_id, entry_date, weight, bmi, notes) VALUES (?, ?, ?, ?, ?)',
            [$memberId, $date, $weight, $bmi, $notes ?? '']
        );
        $id = (int)$db->lastInsertId();
        return new self($memberId, $date, $weight, $bmi, $notes, $id);
    }

    public static function getForMember(int $memberId, int $limit = 100): array
    {
        $db = Database::getInstance();
        $rows = $db->fetchAll(
            'SELECT entry_date, weight, bmi, notes FROM progress_entries WHERE member_id = ? ORDER BY entry_date DESC, id DESC LIMIT ' . (int)$limit,
            [$memberId]
        );
        return $rows;
    }

    public static function getLatestForMember(int $memberId): ?self
    {
        $db = Database::getInstance();
        $row = $db->fetchOne(
            'SELECT * FROM progress_entries WHERE member_id = ? ORDER BY entry_date DESC, id DESC LIMIT 1',
            [$memberId]
        );
        return $row ? self::fromArray($row) : null;
    }

    public function toArray(): array
    {
        return [
            'id'        => $this->id,
            'memberId'  => $this->memberId,
            'entryDate' => $this->entryDate,
            'weight'    => $this->weight,
            'bmi'       => $this->bmi,
            'notes'     => $this->notes,
            'createdAt' => $this->createdAt,
        ];
    }
}
