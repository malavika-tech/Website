<?php

namespace App\Models;

use App\Core\Database;

/**
 * WorkoutPlan Domain Model
 * Represents a personalized workout plan and its associated exercise routine.
 */
class WorkoutPlan
{
    private ?int $id;
    private int $memberId;
    private ?int $trainerId;
    private string $duration;
    private string $daysPerWeek;
    private ?string $assignedAt;
    /** @var array<int, string> */
    private array $exercises;

    /**
     * @param array<int, string> $exercises
     */
    public function __construct(
        int $memberId,
        ?int $trainerId,
        string $duration,
        string $daysPerWeek,
        array $exercises = [],
        ?int $id = null,
        ?string $assignedAt = null
    ) {
        $this->memberId = $memberId;
        $this->trainerId = $trainerId;
        $this->duration = $duration;
        $this->daysPerWeek = $daysPerWeek;
        $this->exercises = $exercises;
        $this->id = $id;
        $this->assignedAt = $assignedAt;
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

    public function getTrainerId(): ?int
    {
        return $this->trainerId;
    }

    public function getDuration(): string
    {
        return $this->duration;
    }

    public function getDaysPerWeek(): string
    {
        return $this->daysPerWeek;
    }

    public function getAssignedAt(): ?string
    {
        return $this->assignedAt;
    }

    /**
     * @return array<int, string>
     */
    public function getExercises(): array
    {
        return $this->exercises;
    }

    // --- Persistence & Queries ---

    public static function getLatestForMember(int $memberId): ?self
    {
        $db = Database::getInstance();
        $row = $db->fetchOne(
            'SELECT * FROM workout_plans WHERE member_id = ? ORDER BY id DESC LIMIT 1',
            [$memberId]
        );

        if (!$row) {
            return null;
        }

        $exRows = $db->fetchAll(
            'SELECT exercise_text FROM workout_exercises WHERE plan_id = ? ORDER BY sort_order',
            [$row['id']]
        );

        $exercises = array_column($exRows, 'exercise_text');

        return new self(
            (int)$row['member_id'],
            isset($row['trainer_id']) ? (int)$row['trainer_id'] : null,
            $row['duration'] ?? '',
            $row['days_per_week'] ?? '',
            $exercises,
            (int)$row['id'],
            $row['assigned_at'] ?? null
        );
    }

    /**
     * Assign and replace workout plan for a member.
     *
     * @param int $trainerId
     * @param int $memberId
     * @param string $duration
     * @param string $daysPerWeek
     * @param array<int, string> $exercises
     * @return self
     */
    public static function assign(
        int $trainerId,
        int $memberId,
        string $duration,
        string $daysPerWeek,
        array $exercises
    ): self {
        $db = Database::getInstance();
        $db->beginTransaction();

        try {
            // Remove previous workout plans for member
            $db->execute('DELETE FROM workout_plans WHERE member_id = ?', [$memberId]);

            // Insert new plan
            $db->execute(
                'INSERT INTO workout_plans (member_id, trainer_id, duration, days_per_week) VALUES (?, ?, ?, ?)',
                [$memberId, $trainerId, $duration, $daysPerWeek]
            );
            $planId = (int)$db->lastInsertId();

            // Insert exercises
            $cleanExercises = array_values(array_filter($exercises, fn($e) => trim((string)$e) !== ''));
            foreach ($cleanExercises as $i => $ex) {
                $db->execute(
                    'INSERT INTO workout_exercises (plan_id, exercise_text, sort_order) VALUES (?, ?, ?)',
                    [$planId, trim((string)$ex), $i]
                );
            }

            $db->commit();

            return new self($memberId, $trainerId, $duration, $daysPerWeek, $cleanExercises, $planId, date('Y-m-d H:i:s'));
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public function toArray(): array
    {
        return [
            'id'          => $this->id,
            'duration'    => $this->duration,
            'daysPerWeek' => $this->daysPerWeek,
            'exercises'   => $this->exercises,
            'assignedAt'  => $this->assignedAt,
        ];
    }
}
