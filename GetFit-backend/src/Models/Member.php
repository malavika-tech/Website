<?php

namespace App\Models;

use App\Core\Database;

/**
 * Member Domain Model
 * Represents a registered gym member, extending the base User entity.
 */
class Member extends User
{
    private ?string $fullName;
    private ?int $age;
    private ?string $gender;
    private ?string $phone;
    private ?float $height;
    private ?float $weight;
    private ?string $fitnessGoal;
    private ?string $registrationDate;
    private string $status;

    public function __construct(
        string $username,
        string $passwordHash,
        ?string $email = null,
        ?string $fullName = null,
        ?int $age = null,
        ?string $gender = null,
        ?string $phone = null,
        ?float $height = null,
        ?float $weight = null,
        ?string $fitnessGoal = null,
        ?string $registrationDate = null,
        string $status = 'active',
        ?int $id = null,
        ?string $createdAt = null
    ) {
        parent::__construct('member', $username, $passwordHash, $email, $id, $createdAt);
        $this->fullName = $fullName;
        $this->age = $age;
        $this->gender = $gender;
        $this->phone = $phone;
        $this->height = $height;
        $this->weight = $weight;
        $this->fitnessGoal = $fitnessGoal;
        $this->registrationDate = $registrationDate;
        $this->status = $status;
    }

    // --- Getters ---

    public function getFullName(): ?string
    {
        return $this->fullName;
    }

    public function getAge(): ?int
    {
        return $this->age;
    }

    public function getGender(): ?string
    {
        return $this->gender;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function getHeight(): ?float
    {
        return $this->height;
    }

    public function getWeight(): ?float
    {
        return $this->weight;
    }

    public function getFitnessGoal(): ?string
    {
        return $this->fitnessGoal;
    }

    public function getRegistrationDate(): ?string
    {
        return $this->registrationDate;
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

    public function setAge(?int $age): void
    {
        $this->age = $age;
    }

    public function setGender(?string $gender): void
    {
        $this->gender = $gender;
    }

    public function setPhone(?string $phone): void
    {
        $this->phone = $phone !== null ? trim($phone) : null;
    }

    public function setHeight(?float $height): void
    {
        $this->height = $height;
    }

    public function setWeight(?float $weight): void
    {
        $this->weight = $weight;
    }

    public function setFitnessGoal(?string $goal): void
    {
        $this->fitnessGoal = $goal;
    }

    public function setStatus(string $status): void
    {
        $this->status = in_array(strtolower($status), ['active', 'inactive'], true)
            ? strtolower($status)
            : 'active';
    }

    // --- Calculations & Relationships ---

    public static function calculateBmi(?float $weight, ?float $height): ?float
    {
        if ($weight === null || $height === null || $height <= 0) {
            return null;
        }
        $h = $height / 100;
        return round($weight / ($h * $h), 1);
    }

    public function getBmi(): ?float
    {
        return self::calculateBmi($this->weight, $this->height);
    }

    public function getActiveMembership(): ?Membership
    {
        if ($this->id === null) {
            return null;
        }
        return Membership::getLatestForMember($this->id);
    }

    public function getLatestProgress(): ?ProgressEntry
    {
        if ($this->id === null) {
            return null;
        }
        return ProgressEntry::getLatestForMember($this->id);
    }

    // --- Persistence & Queries ---

    public static function findById(int $userId): ?self
    {
        $db = Database::getInstance();
        $row = $db->fetchOne(
            'SELECT u.*, m.full_name, m.age, m.gender, m.phone, m.height, m.weight, m.fitness_goal, m.registration_date, m.status AS member_status
             FROM users u
             JOIN members m ON u.id = m.user_id
             WHERE u.id = ? AND u.role = ?',
            [$userId, 'member']
        );

        if (!$row) {
            return null;
        }

        return new self(
            $row['username'],
            $row['password_hash'],
            $row['email'],
            $row['full_name'],
            $row['age'] !== null ? (int)$row['age'] : null,
            $row['gender'],
            $row['phone'],
            $row['height'] !== null ? (float)$row['height'] : null,
            $row['weight'] !== null ? (float)$row['weight'] : null,
            $row['fitness_goal'],
            $row['registration_date'],
            $row['member_status'] ?? 'active',
            (int)$row['id'],
            $row['created_at']
        );
    }

    public function updateProfile(array $data): bool
    {
        if ($this->id === null) {
            return false;
        }

        if (array_key_exists('fullName', $data)) {
            $this->setFullName($data['fullName']);
        }
        if (array_key_exists('age', $data)) {
            $this->setAge($data['age'] !== '' && $data['age'] !== null ? (int)$data['age'] : null);
        }
        if (array_key_exists('height', $data)) {
            $this->setHeight($data['height'] !== '' && $data['height'] !== null ? (float)$data['height'] : null);
        }
        if (array_key_exists('weight', $data)) {
            $this->setWeight($data['weight'] !== '' && $data['weight'] !== null ? (float)$data['weight'] : null);
        }
        if (array_key_exists('fitnessGoal', $data)) {
            $this->setFitnessGoal($data['fitnessGoal']);
        }

        $db = Database::getInstance();
        return $db->execute(
            'UPDATE members SET full_name = ?, age = ?, height = ?, weight = ?, fitness_goal = ? WHERE user_id = ?',
            [
                $this->fullName,
                $this->age,
                $this->height,
                $this->weight,
                $this->fitnessGoal,
                $this->id,
            ]
        );
    }

    public function updateStatus(string $status): bool
    {
        if ($this->id === null) {
            return false;
        }
        $this->setStatus($status);
        $db = Database::getInstance();
        return $db->execute('UPDATE members SET status = ? WHERE user_id = ?', [$this->status, $this->id]);
    }

    public function toggleStatus(): string
    {
        $newStatus = $this->status === 'active' ? 'inactive' : 'active';
        $this->updateStatus($newStatus);
        return $newStatus;
    }

    public function toProfileArray(): array
    {
        $membership = $this->getActiveMembership();
        return [
            'username'          => $this->username,
            'email'             => $this->email,
            'full_name'         => $this->fullName,
            'age'               => $this->age,
            'gender'            => $this->gender,
            'phone'             => $this->phone,
            'height'            => $this->height,
            'weight'            => $this->weight,
            'bmi'               => $this->getBmi(),
            'fitness_goal'      => $this->fitnessGoal,
            'registration_date' => $this->registrationDate,
            'status'            => $this->status,
            'membership_plan'   => $membership ? $membership->getPlan() : null,
        ];
    }

    public function toDashboardArray(): array
    {
        $latestProgress = $this->getLatestProgress();
        $weight = $latestProgress ? $latestProgress->getWeight() : (float)($this->weight ?? 0);
        $bmi = ($latestProgress && $latestProgress->getBmi() !== null)
            ? $latestProgress->getBmi()
            : self::calculateBmi($weight, $this->height);

        return [
            'weight'      => $weight,
            'bmi'         => $bmi,
            'fitnessGoal' => $this->fitnessGoal ?? '',
            'lastUpdate'  => $latestProgress ? $latestProgress->getEntryDate() : null,
        ];
    }
}
