<?php

namespace App\Models;

use App\Core\Database;

/**
 * Trainer Domain Model
 * Represents a personal trainer, extending the base User entity.
 */
class Trainer extends User
{
    private ?string $fullName;
    private ?string $phone;
    private ?string $specialization;
    private ?string $experience;
    private ?string $certification;
    private ?string $hoursAvailable;
    private string $status;

    public function __construct(
        string $username,
        string $passwordHash,
        ?string $email = null,
        ?string $fullName = null,
        ?string $phone = null,
        ?string $specialization = null,
        ?string $experience = null,
        ?string $certification = null,
        ?string $hoursAvailable = null,
        string $status = 'active',
        ?int $id = null,
        ?string $createdAt = null
    ) {
        parent::__construct('trainer', $username, $passwordHash, $email, $id, $createdAt);
        $this->fullName = $fullName;
        $this->phone = $phone;
        $this->specialization = $specialization;
        $this->experience = $experience;
        $this->certification = $certification;
        $this->hoursAvailable = $hoursAvailable;
        $this->status = $status;
    }

    // --- Getters ---

    public function getFullName(): ?string
    {
        return $this->fullName;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function getSpecialization(): ?string
    {
        return $this->specialization;
    }

    public function getExperience(): ?string
    {
        return $this->experience;
    }

    public function getCertification(): ?string
    {
        return $this->certification;
    }

    public function getHoursAvailable(): ?string
    {
        return $this->hoursAvailable;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    // --- Setters ---

    public function setFullName(?string $fullName): void
    {
        $this->fullName = $fullName !== null ? trim($fullName) : null;
    }

    public function setPhone(?string $phone): void
    {
        $this->phone = $phone !== null ? trim($phone) : null;
    }

    public function setSpecialization(?string $specialization): void
    {
        $this->specialization = $specialization !== null ? trim($specialization) : null;
    }

    public function setStatus(string $status): void
    {
        $this->status = in_array(strtolower($status), ['active', 'inactive'], true)
            ? strtolower($status)
            : 'active';
    }

    // --- Domain Queries ---

    public function isMemberAssigned(int $memberId): bool
    {
        if ($this->id === null) {
            return false;
        }
        $db = Database::getInstance();
        $row = $db->fetchOne(
            'SELECT 1 FROM trainer_assignments WHERE member_id = ? AND trainer_id = ?',
            [$memberId, $this->id]
        );
        return $row !== null;
    }

    public function getAssignedMembersCount(): int
    {
        if ($this->id === null) {
            return 0;
        }
        $db = Database::getInstance();
        return (int)$db->fetchColumn(
            'SELECT COUNT(*) FROM trainer_assignments WHERE trainer_id = ?',
            [$this->id]
        );
    }

    public function getAssignedMembersList(): array
    {
        if ($this->id === null) {
            return [];
        }
        $db = Database::getInstance();
        $rows = $db->fetchAll(
            'SELECT m.user_id AS id, m.full_name, m.fitness_goal, m.weight, m.height, ms.plan, ms.status AS membershipStatus
             FROM trainer_assignments ta
             JOIN members m ON ta.member_id = m.user_id
             LEFT JOIN memberships ms ON ms.member_id = m.user_id AND ms.id = (
                 SELECT MAX(id) FROM memberships WHERE member_id = m.user_id
             )
             WHERE ta.trainer_id = ?
             ORDER BY m.full_name',
            [$this->id]
        );

        foreach ($rows as &$member) {
            $member['bmi'] = ($member['weight'] && $member['height'])
                ? Member::calculateBmi((float)$member['weight'], (float)$member['height'])
                : null;
        }

        return $rows;
    }

    // --- Persistence & Finder ---

    public static function findById(int $userId): ?self
    {
        $db = Database::getInstance();
        $row = $db->fetchOne(
            'SELECT u.*, t.full_name, t.phone, t.specialization, t.experience, t.certification, t.hours_available, t.status AS trainer_status
             FROM users u
             JOIN trainers t ON u.id = t.user_id
             WHERE u.id = ? AND u.role = ?',
            [$userId, 'trainer']
        );

        if (!$row) {
            return null;
        }

        return new self(
            $row['username'],
            $row['password_hash'],
            $row['email'],
            $row['full_name'],
            $row['phone'],
            $row['specialization'],
            $row['experience'],
            $row['certification'],
            $row['hours_available'],
            $row['trainer_status'] ?? 'active',
            (int)$row['id'],
            $row['created_at']
        );
    }

    public function updateDetails(array $data): bool
    {
        if ($this->id === null) {
            return false;
        }

        if (array_key_exists('fullName', $data)) {
            $this->setFullName($data['fullName']);
        }
        if (array_key_exists('phone', $data)) {
            $this->setPhone($data['phone']);
        }
        if (array_key_exists('specialization', $data)) {
            $this->setSpecialization($data['specialization']);
        }
        if (array_key_exists('status', $data)) {
            $this->setStatus($data['status']);
        }

        $db = Database::getInstance();
        $trainerUpdated = $db->execute(
            'UPDATE trainers SET full_name = ?, phone = ?, specialization = ?, status = ? WHERE user_id = ?',
            [$this->fullName, $this->phone, $this->specialization, $this->status, $this->id]
        );

        if (!empty($data['email'])) {
            $this->setEmail($data['email']);
            $db->execute('UPDATE users SET email = ? WHERE id = ?', [$this->email, $this->id]);
        }

        if (!empty($data['password'])) {
            $this->setPlainPassword($data['password']);
            $db->execute('UPDATE users SET password_hash = ? WHERE id = ?', [$this->passwordHash, $this->id]);
        }

        return $trainerUpdated;
    }

    public function toArray(): array
    {
        return [
            'id'              => $this->id,
            'username'        => $this->username,
            'email'           => $this->email,
            'full_name'       => $this->fullName,
            'phone'           => $this->phone,
            'specialization'  => $this->specialization,
            'status'          => $this->status,
            'assignedMembers' => $this->getAssignedMembersCount(),
        ];
    }
}
