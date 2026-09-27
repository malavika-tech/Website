<?php

namespace App\Services;

use App\Core\Database;
use App\Models\Trainer;
use App\Models\Member;
use App\Models\WorkoutPlan;
use App\Models\DietPlan;
use App\Models\TrainerSession;
use App\Models\ProgressEntry;

/**
 * TrainerService
 * Handles trainer operations: client roster, session scheduling, workout/diet prescription, and statistics.
 */
class TrainerService
{
    /**
     * Get trainer overview metrics for trainer-dashboard.
     *
     * @param int $trainerId
     * @return array<string, int>
     */
    public function getDashboard(int $trainerId): array
    {
        $trainer = Trainer::findById($trainerId);
        $count = $trainer ? $trainer->getAssignedMembersCount() : 0;

        return [
            'assignedMembers' => $count,
            'todaysSessions'  => 0,
            'goalsAchieved'   => 0,
        ];
    }

    /**
     * Get list of members assigned to this trainer.
     *
     * @param int $trainerId
     * @return array<int, array<string, mixed>>
     */
    public function getAssignedMembers(int $trainerId): array
    {
        $trainer = Trainer::findById($trainerId);
        return $trainer ? $trainer->getAssignedMembersList() : [];
    }

    /**
     * Get comprehensive profile details for an assigned member.
     *
     * @param int $trainerId
     * @param int $memberId
     * @return array<string, mixed>
     */
    public function getMemberDetails(int $trainerId, int $memberId): array
    {
        $trainer = Trainer::findById($trainerId);
        if (!$trainer || !$trainer->isMemberAssigned($memberId)) {
            json_error('Member not assigned to you', 403);
        }

        $db = Database::getInstance();
        $member = $db->fetchOne(
            'SELECT m.user_id AS id, m.full_name, m.age, m.gender, m.phone, m.height, m.weight,
                    m.fitness_goal, m.registration_date, u.email
             FROM members m JOIN users u ON u.id = m.user_id
             WHERE m.user_id = ?',
            [$memberId]
        );

        if (!$member) {
            json_error('Member not found', 404);
        }

        $member['bmi'] = ($member['weight'] && $member['height'])
            ? Member::calculateBmi((float)$member['weight'], (float)$member['height'])
            : null;

        $member['progressHistory'] = ProgressEntry::getForMember($memberId, 10);

        return $member;
    }

    /**
     * Prescribe a workout routine to an assigned member.
     *
     * @param int $trainerId
     * @param array<string, mixed> $body
     * @return void
     */
    public function assignWorkout(int $trainerId, array $body): void
    {
        body_require($body, 'memberId', 'duration', 'daysPerWeek', 'exercises');
        $memberId = (int)$body['memberId'];

        $trainer = Trainer::findById($trainerId);
        if (!$trainer || !$trainer->isMemberAssigned($memberId)) {
            json_error('Member not assigned to you', 403);
        }

        WorkoutPlan::assign(
            $trainerId,
            $memberId,
            (string)$body['duration'],
            (string)$body['daysPerWeek'],
            (array)$body['exercises']
        );
    }

    /**
     * Prescribe a diet plan to an assigned member.
     *
     * @param int $trainerId
     * @param array<string, mixed> $body
     * @return void
     */
    public function assignDiet(int $trainerId, array $body): void
    {
        body_require($body, 'memberId', 'dailyCalories', 'meals');
        $memberId = (int)$body['memberId'];

        $trainer = Trainer::findById($trainerId);
        if (!$trainer || !$trainer->isMemberAssigned($memberId)) {
            json_error('Member not assigned to you', 403);
        }

        DietPlan::assign(
            $trainerId,
            $memberId,
            (int)$body['dailyCalories'],
            (array)$body['meals']
        );
    }

    /**
     * Schedule a new training session for an assigned member.
     *
     * @param int $trainerId
     * @param array<string, mixed> $body
     * @return array{session_id: int|string, message: string}
     */
    public function scheduleSession(int $trainerId, array $body): array
    {
        body_require($body, 'member_id', 'session_date', 'session_time');

        $memberId = (int)$body['member_id'];
        $sessionDate = $body['session_date'];
        $sessionTime = $body['session_time'];
        $status = $body['status'] ?? 'scheduled';

        $trainer = Trainer::findById($trainerId);
        if (!$trainer || !$trainer->isMemberAssigned($memberId)) {
            json_error('Selected member is not assigned to you.');
        }

        $session = TrainerSession::schedule($trainerId, $memberId, $sessionDate, $sessionTime, $status);

        return [
            'session_id' => $session->getSessionId(),
            'message'    => 'Session scheduled successfully',
        ];
    }

    /**
     * Update session status by session ID.
     *
     * @param int $trainerId
     * @param array<string, mixed> $body
     * @return array{message: string}
     */
    public function updateSessionStatus(int $trainerId, array $body): array
    {
        body_require($body, 'session_id', 'status');

        $sessionId = (int)$body['session_id'];
        $status = $body['status'];

        $session = TrainerSession::findById($sessionId);
        if (!$session || $session->getTrainerId() !== $trainerId) {
            json_error('Session not found or not assigned to you.', 404);
        }

        $session->updateStatusInDb($status);

        return [
            'message' => 'Session updated successfully',
        ];
    }

    /**
     * Get trainer performance statistics, session counts, and session list.
     *
     * @param int $trainerId
     * @return array<string, mixed>
     */
    public function getTrainerStats(int $trainerId): array
    {
        $db = Database::getInstance();

        // 1. Assigned members count
        $assignedMembers = (int)$db->fetchColumn(
            'SELECT COUNT(*) FROM trainer_assignments WHERE trainer_id = ?',
            [$trainerId]
        );

        // 2. Today's total sessions
        $todaysSessions = (int)$db->fetchColumn(
            'SELECT COUNT(*) FROM trainer_sessions WHERE trainer_id = ? AND session_date = CURDATE()',
            [$trainerId]
        );

        // 3. Today's scheduled sessions
        $todaysScheduled = (int)$db->fetchColumn(
            "SELECT COUNT(*) FROM trainer_sessions WHERE trainer_id = ? AND session_date = CURDATE() AND status = 'scheduled'",
            [$trainerId]
        );

        // 4. Goals achieved (progress reviews from assigned members)
        $goalsAchieved = (int)$db->fetchColumn(
            'SELECT COUNT(*) FROM progress_entries pe 
             JOIN trainer_assignments ta ON pe.member_id = ta.member_id 
             WHERE ta.trainer_id = ?',
            [$trainerId]
        );

        // 5. Sessions list
        $sessions = TrainerSession::getSessionsForTrainer($trainerId);

        return [
            'assignedMembers' => $assignedMembers,
            'todaysSessions'  => $todaysSessions,
            'todaysScheduled' => $todaysScheduled,
            'goalsAchieved'   => $goalsAchieved,
            'sessions'        => $sessions,
        ];
    }
}
