<?php

namespace App\Models;

use App\Core\Database;

/**
 * DietPlan Domain Model
 * Represents a nutritional diet plan and its daily meal breakdowns.
 */
class DietPlan
{
    private ?int $id;
    private int $memberId;
    private ?int $trainerId;
    private int $dailyCalories;
    private ?string $assignedAt;
    /** @var array<int, array{meal_time: string, food: string, calories: int}> */
    private array $meals;

    /**
     * @param array<int, array{meal_time: string, food: string, calories: int}> $meals
     */
    public function __construct(
        int $memberId,
        ?int $trainerId,
        int $dailyCalories,
        array $meals = [],
        ?int $id = null,
        ?string $assignedAt = null
    ) {
        $this->memberId = $memberId;
        $this->trainerId = $trainerId;
        $this->dailyCalories = $dailyCalories;
        $this->meals = $meals;
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

    public function getDailyCalories(): int
    {
        return $this->dailyCalories;
    }

    public function getAssignedAt(): ?string
    {
        return $this->assignedAt;
    }

    /**
     * @return array<int, array{meal_time: string, food: string, calories: int}>
     */
    public function getMeals(): array
    {
        return $this->meals;
    }

    // --- Persistence & Queries ---

    public static function getLatestForMember(int $memberId): ?self
    {
        $db = Database::getInstance();
        $row = $db->fetchOne(
            'SELECT * FROM diet_plans WHERE member_id = ? ORDER BY id DESC LIMIT 1',
            [$memberId]
        );

        if (!$row) {
            return null;
        }

        $meals = $db->fetchAll(
            'SELECT meal_time, food, calories FROM diet_meals WHERE plan_id = ? ORDER BY sort_order',
            [$row['id']]
        );

        return new self(
            (int)$row['member_id'],
            isset($row['trainer_id']) ? (int)$row['trainer_id'] : null,
            (int)($row['daily_calories'] ?? 0),
            $meals,
            (int)$row['id'],
            $row['assigned_at'] ?? null
        );
    }

    /**
     * Assign and replace diet plan for a member.
     *
     * @param int $trainerId
     * @param int $memberId
     * @param int $dailyCalories
     * @param array<int, array{time?: string, meal_time?: string, food?: string, calories?: int|string}> $meals
     * @return self
     */
    public static function assign(
        int $trainerId,
        int $memberId,
        int $dailyCalories,
        array $meals
    ): self {
        $db = Database::getInstance();
        $db->beginTransaction();

        try {
            // Remove previous diet plans for member
            $db->execute('DELETE FROM diet_plans WHERE member_id = ?', [$memberId]);

            // Insert new plan
            $db->execute(
                'INSERT INTO diet_plans (member_id, trainer_id, daily_calories) VALUES (?, ?, ?)',
                [$memberId, $trainerId, $dailyCalories]
            );
            $planId = (int)$db->lastInsertId();

            // Insert meals
            $formattedMeals = [];
            foreach (array_values($meals) as $i => $meal) {
                $time = $meal['time'] ?? $meal['meal_time'] ?? '';
                $food = $meal['food'] ?? '';
                $cal = (int)($meal['calories'] ?? 0);

                $db->execute(
                    'INSERT INTO diet_meals (plan_id, meal_time, food, calories, sort_order) VALUES (?, ?, ?, ?, ?)',
                    [$planId, $time, $food, $cal, $i]
                );

                $formattedMeals[] = [
                    'meal_time' => $time,
                    'food'      => $food,
                    'calories'  => $cal,
                ];
            }

            $db->commit();

            return new self($memberId, $trainerId, $dailyCalories, $formattedMeals, $planId, date('Y-m-d H:i:s'));
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public function toArray(): array
    {
        return [
            'id'            => $this->id,
            'dailyCalories' => $this->dailyCalories,
            'meals'         => $this->meals,
            'assignedAt'    => $this->assignedAt,
        ];
    }
}
