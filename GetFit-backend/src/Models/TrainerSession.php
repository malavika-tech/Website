<?php

namespace App\Models;

use App\Core\Database;

/**
 * TrainerSession Domain Model
 * Manages scheduled one-on-one fitness sessions between trainers and members.
 */
class TrainerSession
{
    private ?int $sessionId;
    private int $trainerId;
    private int $memberId;
    private string $sessionDate;
    private string $sessionTime;
    private string $status;
    private ?string $trainerName;
    private ?string $memberName;

    public function __construct(
        int $trainerId,
        int $memberId,
        string $sessionDate,
        string $sessionTime,
        string $status = 'scheduled',
        ?int $sessionId = null,
        ?string $trainerName = null,
        ?string $memberName = null
    ) {
        $this->trainerId = $trainerId;
        $this->memberId = $memberId;
        $this->sessionDate = $sessionDate;
        $this->sessionTime = $sessionTime;
        $this->status = $status;
        $this->sessionId = $sessionId;
        $this->trainerName = $trainerName;
        $this->memberName = $memberName;
    }

    // --- Getters ---

    public function getSessionId(): ?int
    {
        return $this->sessionId;
    }

    public function getTrainerId(): int
    {
        return $this->trainerId;
    }

    public function getMemberId(): int
    {
        return $this->memberId;
    }

    public function getSessionDate(): string
    {
        return $this->sessionDate;
    }

    public function getSessionTime(): string
    {
        return $this->sessionTime;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getTrainerName(): ?string
    {
        return $this->trainerName;
    }

    public function getMemberName(): ?string
    {
        return $this->memberName;
    }

    // --- Actions ---

    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

    // --- Persistence & Queries ---

    public static function findById(int $sessionId): ?self
    {
        $db = Database::getInstance();
        $row = $db->fetchOne('SELECT * FROM trainer_sessions WHERE session_id = ?', [$sessionId]);
        if (!$row) {
            return null;
        }

        return new self(
            (int)$row['trainer_id'],
            (int)$row['member_id'],
            $row['session_date'],
            $row['session_time'],
            $row['status'] ?? 'scheduled',
            (int)$row['session_id']
        );
    }

    public static function schedule(
        int $trainerId,
        int $memberId,
        string $sessionDate,
        string $sessionTime,
        string $status = 'scheduled'
    ): self {
        $db = Database::getInstance();
        $db->execute(
            'INSERT INTO trainer_sessions (trainer_id, member_id, session_date, session_time, status) VALUES (?, ?, ?, ?, ?)',
            [$trainerId, $memberId, $sessionDate, $sessionTime, $status]
        );
        $id = (int)$db->lastInsertId();

        return new self($trainerId, $memberId, $sessionDate, $sessionTime, $status, $id);
    }

    public function updateStatusInDb(string $status): bool
    {
        if ($this->sessionId === null) {
            return false;
        }

        $this->status = $status;
        $db = Database::getInstance();
        return $db->execute(
            'UPDATE trainer_sessions SET status = ? WHERE session_id = ?',
            [$status, $this->sessionId]
        );
    }

    public static function getUpcomingForMember(int $memberId): array
    {
        $db = Database::getInstance();
        return $db->fetchAll(
            'SELECT s.session_id, s.trainer_id, s.session_date, s.session_time, s.status, t.full_name AS trainer_name 
             FROM trainer_sessions s
             LEFT JOIN trainers t ON s.trainer_id = t.user_id
             WHERE s.member_id = ? AND s.session_date >= CURDATE()
             ORDER BY s.session_date ASC, s.session_time ASC',
            [$memberId]
        );
    }

    public static function getSessionsForTrainer(int $trainerId): array
    {
        $db = Database::getInstance();
        return $db->fetchAll(
            'SELECT s.session_id, s.member_id, s.session_date, s.session_time, s.status, m.full_name AS member_name 
             FROM trainer_sessions s
             JOIN members m ON s.member_id = m.user_id
             WHERE s.trainer_id = ?
             ORDER BY s.session_date DESC, s.session_time DESC',
            [$trainerId]
        );
    }

    public function toArray(): array
    {
        return [
            'session_id'   => $this->sessionId,
            'trainer_id'   => $this->trainerId,
            'member_id'    => $this->memberId,
            'session_date' => $this->sessionDate,
            'session_time' => $this->sessionTime,
            'status'       => $this->status,
            'trainer_name' => $this->trainerName,
            'member_name'  => $this->memberName,
        ];
    }
}
