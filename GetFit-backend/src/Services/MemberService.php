<?php

namespace App\Services;

use App\Core\Database;
use App\Models\Member;
use App\Models\Membership;
use App\Models\ProgressEntry;
use App\Models\WorkoutPlan;
use App\Models\DietPlan;
use App\Models\TrainerSession;

/**
 * MemberService
 * Handles member-facing operations: profile management, progress tracking, workout/diet views, and trainer info.
 */
class MemberService
{
    /**
     * Get member profile details.
     *
     * @param int $memberId
     * @return array<string, mixed>
     */
    public function getProfile(int $memberId): array
    {
        $member = Member::findById($memberId);
        if (!$member) {
            json_error('Member not found', 404);
        }
        return $member->toProfileArray();
    }

    /**
     * Update member personal information.
     *
     * @param int $memberId
     * @param array<string, mixed> $body
     * @return void
     */
    public function updateProfile(int $memberId, array $body): void
    {
        $member = Member::findById($memberId);
        if (!$member) {
            json_error('Member not found', 404);
        }

        $member->updateProfile($body);

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (isset($body['weight']) && $body['weight'] !== '') {
            $_SESSION['weight'] = (float)$body['weight'];
        }
    }

    /**
     * Get member dashboard statistics.
     *
     * @param int $memberId
     * @return array<string, mixed>
     */
    public function getDashboardData(int $memberId): array
    {
        $member = Member::findById($memberId);
        if (!$member) {
            json_error('Member not found', 404);
        }
        return $member->toDashboardArray();
    }

    /**
     * Get active membership subscription details and remaining days.
     *
     * @param int $memberId
     * @return array<string, mixed>
     */
    public function getMembershipInfo(int $memberId): array
    {
        $membership = Membership::getLatestForMember($memberId);
        if (!$membership) {
            json_error('No membership found', 404);
        }

        return [
            'plan'      => $membership->getPlan(),
            'startDate' => $membership->getStartDate(),
            'endDate'   => $membership->getEndDate(),
            'status'    => $membership->getStatus(),
            'totalDays' => $membership->getTotalDays(),
            'daysLeft'  => $membership->getDaysLeft(),
        ];
    }

    /**
     * Get historical progress logs for member.
     *
     * @param int $memberId
     * @return array<int, array<string, mixed>>
     */
    public function getProgressEntries(int $memberId): array
    {
        return ProgressEntry::getForMember($memberId);
    }

    /**
     * Record a new progress measurement entry.
     *
     * @param int $memberId
     * @param array<string, mixed> $body
     * @return array{bmi: float|null}
     */
    public function addProgressEntry(int $memberId, array $body): array
    {
        body_require($body, 'date', 'weight');

        $member = Member::findById($memberId);
        $height = $member ? $member->getHeight() : null;
        $weight = (float)$body['weight'];
        $bmi = Member::calculateBmi($weight, $height);

        ProgressEntry::log($memberId, $body['date'], $weight, $bmi, $body['notes'] ?? '');

        // Update member current weight
        $db = Database::getInstance();
        $db->execute('UPDATE members SET weight = ? WHERE user_id = ?', [$weight, $memberId]);

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['weight'] = $weight;

        return ['bmi' => $bmi];
    }

    /**
     * Get assigned workout plan.
     *
     * @param int $memberId
     * @return array<string, mixed>|null
     */
    public function getWorkoutPlan(int $memberId): ?array
    {
        $plan = WorkoutPlan::getLatestForMember($memberId);
        return $plan ? $plan->toArray() : null;
    }

    /**
     * Get assigned diet plan.
     *
     * @param int $memberId
     * @return array<string, mixed>|null
     */
    public function getDietPlan(int $memberId): ?array
    {
        $plan = DietPlan::getLatestForMember($memberId);
        return $plan ? $plan->toArray() : null;
    }

    /**
     * Get assigned trainer contact details.
     *
     * @param int $memberId
     * @return array<string, mixed>|null
     */
    public function getAssignedTrainer(int $memberId): ?array
    {
        $db = Database::getInstance();
        $trainer = $db->fetchOne(
            'SELECT t.full_name, t.specialization, u.email, t.phone
             FROM trainer_assignments ta
             JOIN trainers t ON ta.trainer_id = t.user_id
             JOIN users u    ON u.id = ta.trainer_id
             WHERE ta.member_id = ?',
            [$memberId]
        );
        return $trainer ?: null;
    }

    /**
     * Get upcoming scheduled training sessions.
     *
     * @param int $memberId
     * @return array<int, array<string, mixed>>
     */
    public function getUpcomingSessions(int $memberId): array
    {
        return TrainerSession::getUpcomingForMember($memberId);
    }
}
