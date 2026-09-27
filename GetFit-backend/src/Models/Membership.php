<?php

namespace App\Models;

use App\Core\Database;
use DateTime;

/**
 * Membership Domain Model
 * Manages gym membership contracts, subscription plans, and validity.
 */
class Membership
{
    private ?int $id;
    private int $memberId;
    private string $plan;
    private string $startDate;
    private string $endDate;
    private string $status;

    public function __construct(
        int $memberId,
        string $plan,
        string $startDate,
        string $endDate,
        string $status = 'active',
        ?int $id = null
    ) {
        $this->memberId = $memberId;
        $this->plan = $plan;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->status = $status;
        $this->id = $id;
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

    public function getPlan(): string
    {
        return $this->plan;
    }

    public function getStartDate(): string
    {
        return $this->startDate;
    }

    public function getEndDate(): string
    {
        return $this->endDate;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    // --- Business Calculations ---

    public function getTotalDays(): int
    {
        $start = new DateTime($this->startDate);
        $end = new DateTime($this->endDate);
        return (int)$start->diff($end)->days;
    }

    public function getDaysLeft(): int
    {
        $end = new DateTime($this->endDate);
        $today = new DateTime();
        if ($today > $end) {
            return 0;
        }
        return max(0, (int)$today->diff($end)->days);
    }

    public function isExpired(): bool
    {
        return $this->endDate < date('Y-m-d');
    }

    /**
     * Check if membership expired and update in database if needed.
     */
    public function checkAndUpdateExpiration(): void
    {
        if ($this->status === 'active' && $this->isExpired()) {
            $this->status = 'expired';
            if ($this->id !== null) {
                $db = Database::getInstance();
                $db->execute('UPDATE memberships SET status = ? WHERE id = ?', ['expired', $this->id]);
            }
        }
    }

    // --- Factory & Persistence ---

    public static function fromArray(array $data): self
    {
        return new self(
            (int)$data['member_id'],
            $data['plan'] ?? 'monthly',
            $data['start_date'] ?? date('Y-m-d'),
            $data['end_date'] ?? date('Y-m-d'),
            $data['status'] ?? 'active',
            isset($data['id']) ? (int)$data['id'] : null
        );
    }

    public static function createForMember(int $memberId, string $plan, ?string $startDate = null): self
    {
        $start = $startDate ?? date('Y-m-d');
        $months = match ($plan) {
            'monthly'   => 1,
            'quarterly' => 3,
            'yearly'    => 12,
            default     => 1,
        };
        $end = date('Y-m-d', strtotime("+$months months", strtotime($start)));

        $db = Database::getInstance();
        $db->execute(
            'INSERT INTO memberships (member_id, plan, start_date, end_date, status) VALUES (?, ?, ?, ?, ?)',
            [$memberId, $plan, $start, $end, 'active']
        );

        $id = (int)$db->lastInsertId();
        return new self($memberId, $plan, $start, $end, 'active', $id);
    }

    public static function getLatestForMember(int $memberId): ?self
    {
        $db = Database::getInstance();
        $row = $db->fetchOne(
            'SELECT * FROM memberships WHERE member_id = ? ORDER BY id DESC LIMIT 1',
            [$memberId]
        );

        if (!$row) {
            return null;
        }

        $membership = self::fromArray($row);
        $membership->checkAndUpdateExpiration();
        return $membership;
    }

    public static function deleteById(int $id): bool
    {
        $db = Database::getInstance();
        return $db->execute('DELETE FROM memberships WHERE id = ?', [$id]);
    }

    public function toArray(): array
    {
        return [
            'id'        => $this->id,
            'memberId'  => $this->memberId,
            'plan'      => $this->plan,
            'startDate' => $this->startDate,
            'endDate'   => $this->endDate,
            'status'    => $this->status,
            'totalDays' => $this->getTotalDays(),
            'daysLeft'  => $this->getDaysLeft(),
        ];
    }
}
