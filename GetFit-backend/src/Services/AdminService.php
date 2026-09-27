<?php

namespace App\Services;

use App\Core\Database;
use App\Models\User;
use App\Models\Trainer;
use App\Models\Member;
use App\Models\Membership;
use App\Models\GymSettings;
use Exception;

/**
 * AdminService
 * Handles administrator operations: dashboards, trainers, members, assignments, memberships, and gym settings.
 */
class AdminService
{
    /**
     * Get aggregate statistics for the main admin dashboard.
     *
     * @return array<string, int>
     */
    public function getDashboardMetrics(): array
    {
        $db = Database::getInstance();

        $total    = (int)$db->fetchColumn("SELECT COUNT(*) FROM members");
        $active   = (int)$db->fetchColumn("SELECT COUNT(*) FROM members WHERE status = 'active'");
        $inactive = (int)$db->fetchColumn("SELECT COUNT(*) FROM members WHERE status = 'inactive'");
        $trainers = (int)$db->fetchColumn("SELECT COUNT(*) FROM trainers");

        $activeMem  = (int)$db->fetchColumn("SELECT COUNT(*) FROM memberships WHERE status = 'active' AND end_date >= CURDATE()");
        $expiredMem = (int)$db->fetchColumn("SELECT COUNT(*) FROM memberships WHERE status = 'expired' OR end_date < CURDATE()");

        return [
            'totalMembers'       => $total,
            'activeMembers'      => $active,
            'inactiveMembers'    => $inactive,
            'totalTrainers'      => $trainers,
            'activeMemberships'  => $activeMem,
            'expiredMemberships' => $expiredMem,
        ];
    }

    /**
     * Get member-specific summary statistics for admin member management.
     *
     * @return array<string, int>
     */
    public function getMemberStats(): array
    {
        $db = Database::getInstance();

        $total   = (int)$db->fetchColumn("SELECT COUNT(*) FROM members");
        $active  = (int)$db->fetchColumn("SELECT COUNT(*) FROM members WHERE status = 'active'");
        $pending = (int)$db->fetchColumn("SELECT COUNT(*) FROM members WHERE status = 'inactive'");

        $newThisMonth = (int)$db->fetchColumn(
            "SELECT COUNT(*) FROM members m
             JOIN users u ON u.id = m.user_id
             WHERE YEAR(u.created_at) = YEAR(NOW())
               AND MONTH(u.created_at) = MONTH(NOW())"
        );

        return [
            'total'          => $total,
            'active'         => $active,
            'pending'        => $pending,
            'new_this_month' => $newThisMonth,
        ];
    }

    /**
     * Get filtered members list.
     *
     * @param array{search?: string, duration?: string} $filters
     * @return array<int, array<string, mixed>>
     */
    public function getMembers(array $filters = []): array
    {
        $db = Database::getInstance();
        $where = '1=1';
        $params = [];

        if (!empty($filters['search'])) {
            $where .= ' AND (m.full_name LIKE ? OR u.email LIKE ? OR u.username LIKE ?)';
            $s = '%' . trim($filters['search']) . '%';
            $params = array_merge($params, [$s, $s, $s]);
        }

        if (!empty($filters['duration'])) {
            $where .= ' AND ms.plan = ?';
            $params[] = trim($filters['duration']);
        }

        return $db->fetchAll(
            "SELECT m.user_id AS id, m.full_name, u.email, m.phone, ms.plan, ms.start_date, ms.end_date,
                    ms.status AS membershipStatus, m.status
             FROM members m
             JOIN users u ON u.id = m.user_id
             LEFT JOIN memberships ms ON ms.member_id = m.user_id
                 AND ms.id = (SELECT MAX(id) FROM memberships WHERE member_id = m.user_id)
             WHERE $where
             ORDER BY m.full_name",
            $params
        );
    }

    /**
     * Update member status (explicit status or toggle).
     *
     * @param int|string $rawMemberId
     * @param string|null $requestedStatus
     * @return array<string, mixed>
     */
    public function updateMemberStatus(int|string $rawMemberId, ?string $requestedStatus = null): array
    {
        $id = (int)ltrim((string)$rawMemberId, 'Mm');
        if ($id <= 0) {
            json_error('Invalid member_id — must be a positive integer or formatted ID like "M002"');
        }

        if ($requestedStatus !== null) {
            $requestedStatus = strtolower(trim($requestedStatus));
            if (!in_array($requestedStatus, ['active', 'inactive'], true)) {
                json_error('Invalid status value — must be "active" or "inactive"');
            }
        }

        $db = Database::getInstance();
        $memberRow = $db->fetchOne(
            'SELECT m.status FROM members m JOIN users u ON u.id = m.user_id WHERE m.user_id = ? AND u.role = ?',
            [$id, 'member']
        );

        if (!$memberRow) {
            http_response_code(404);
            echo json_encode([
                'ok'      => false,
                'error'   => 'Member not found (id=' . $id . ')',
                'status'  => 'error',
                'message' => 'Member not found (id=' . $id . ')',
            ]);
            exit;
        }

        $currentStatus = $memberRow['status'];
        $newStatus = $requestedStatus ?? ($currentStatus === 'active' ? 'inactive' : 'active');

        if ($newStatus === $currentStatus) {
            return [
                'ok'      => true,
                'data'    => ['new_status' => $newStatus],
                'status'  => 'success',
                'message' => 'Status unchanged (already ' . $newStatus . ')',
            ];
        }

        $db->execute('UPDATE members SET status = ? WHERE user_id = ?', [$newStatus, $id]);

        return [
            'ok'      => true,
            'data'    => ['new_status' => $newStatus],
            'status'  => 'success',
            'message' => 'Member status updated to ' . $newStatus,
        ];
    }

    /**
     * Delete a member and cascade delete related rows.
     *
     * @param int|string $rawMemberId
     * @return bool
     */
    public function deleteMember(int|string $rawMemberId): bool
    {
        $id = (int)ltrim((string)$rawMemberId, 'Mm');
        if ($id <= 0) {
            json_error('Invalid member_id — must be a positive integer or formatted ID like "M002"');
        }

        $db = Database::getInstance();
        $exists = $db->fetchOne('SELECT id FROM users WHERE id = ? AND role = ?', [$id, 'member']);

        if (!$exists) {
            http_response_code(404);
            echo json_encode([
                'ok'      => false,
                'error'   => 'Member not found (id=' . $id . ')',
                'status'  => 'error',
                'message' => 'Member not found (id=' . $id . ')',
            ]);
            exit;
        }

        return $db->execute('DELETE FROM users WHERE id = ? AND role = ?', [$id, 'member']);
    }

    /**
     * Get trainers list with assigned member counts and monthly growth metrics.
     *
     * @param array{search?: string, status?: string} $filters
     * @return array<string, mixed>
     */
    public function getTrainers(array $filters = []): array
    {
        $db = Database::getInstance();
        $where = '1=1';
        $params = [];

        if (!empty($filters['search'])) {
            $s = '%' . trim($filters['search']) . '%';
            $where .= ' AND (t.full_name LIKE ? OR u.email LIKE ?)';
            $params = [$s, $s];
        }

        if (!empty($filters['status'])) {
            $where .= ' AND t.status = ?';
            $params[] = trim($filters['status']);
        }

        $trainersList = $db->fetchAll(
            "SELECT t.user_id AS id, t.full_name, u.email, t.phone, t.specialization, t.status,
                    (SELECT COUNT(*) FROM trainer_assignments WHERE trainer_id = t.user_id) AS assignedMembers
             FROM trainers t JOIN users u ON u.id = t.user_id
             WHERE $where ORDER BY t.full_name",
            $params
        );

        // Growth metrics
        $curMonth = (int)$db->fetchColumn(
            "SELECT COUNT(*) FROM users WHERE role = 'trainer' AND YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE())"
        );

        $prevMonth = (int)$db->fetchColumn(
            "SELECT COUNT(*) FROM users WHERE role = 'trainer' AND YEAR(created_at) = YEAR(CURRENT_DATE - INTERVAL 1 MONTH) AND MONTH(created_at) = MONTH(CURRENT_DATE - INTERVAL 1 MONTH)"
        );

        if ($prevMonth === 0) {
            $growth = $curMonth > 0 ? 100 : 0;
        } else {
            $growth = (int)round((($curMonth - $prevMonth) / $prevMonth) * 100);
        }

        return [
            'trainers'         => $trainersList,
            'newTrainersCount' => $curMonth,
            'growthPercentage' => $growth,
        ];
    }

    /**
     * Create a new trainer account.
     *
     * @param array<string, mixed> $body
     * @return int New trainer user ID
     */
    public function createTrainer(array $body): int
    {
        $phone = isset($body['phone']) ? trim((string)$body['phone']) : '';
        if (strlen($phone) !== 10) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid phone number length']);
            exit;
        }

        if (User::existsByUsername($body['username'])) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Username already taken']);
            exit;
        }

        $email = isset($body['email']) ? trim((string)$body['email']) : '';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid email format']);
            exit;
        }

        $db = Database::getInstance();
        $db->beginTransaction();

        try {
            $user = new User('trainer', $body['username'], '', $email);
            $user->setPlainPassword($body['password']);
            $user->save();
            $uid = (int)$user->getId();

            $db->execute(
                'INSERT INTO trainers (user_id, full_name, phone, specialization, status) VALUES (?, ?, ?, ?, ?)',
                [
                    $uid,
                    $body['fullName'],
                    $phone,
                    $body['specialization'] ?? '',
                    $body['status'] ?? 'active',
                ]
            );

            $db->commit();
            return $uid;
        } catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /**
     * Update an existing trainer's profile.
     *
     * @param int $id
     * @param array<string, mixed> $data
     * @return bool
     */
    public function updateTrainer(int $id, array $data): bool
    {
        $trainer = Trainer::findById($id);
        if (!$trainer) {
            json_error('Trainer not found', 404);
        }
        return $trainer->updateDetails($data);
    }

    /**
     * Delete a trainer.
     *
     * @param int $id
     * @return bool
     */
    public function deleteTrainer(int $id): bool
    {
        $user = User::findById($id);
        if (!$user || $user->getRole() !== 'trainer') {
            json_error('Trainer not found', 404);
        }
        return $user->delete();
    }

    /**
     * Get trainer assignments data (active members and active trainers).
     *
     * @return array{members: array, trainers: array}
     */
    public function getAssignments(): array
    {
        $db = Database::getInstance();

        $members = $db->fetchAll(
            "SELECT m.user_id AS id, m.full_name,
                    (SELECT trainer_id FROM trainer_assignments WHERE member_id = m.user_id) AS trainer_id,
                    (SELECT plan FROM memberships WHERE member_id = m.user_id ORDER BY id DESC LIMIT 1) AS plan
             FROM members m WHERE m.status = 'active' ORDER BY m.full_name"
        );

        $trainers = $db->fetchAll(
            "SELECT t.user_id AS id, t.full_name, t.specialization
             FROM trainers t WHERE t.status = 'active' ORDER BY t.full_name"
        );

        return ['members' => $members, 'trainers' => $trainers];
    }

    /**
     * Assign a trainer to a member.
     *
     * @param int $memberId
     * @param int $trainerId
     * @return bool
     */
    public function assignTrainer(int $memberId, int $trainerId): bool
    {
        $db = Database::getInstance();
        return $db->execute(
            'INSERT INTO trainer_assignments (member_id, trainer_id) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE trainer_id = VALUES(trainer_id), assigned_at = CURRENT_TIMESTAMP',
            [$memberId, $trainerId]
        );
    }

    /**
     * Unassign a trainer from a member.
     *
     * @param int $memberId
     * @return bool
     */
    public function removeAssignment(int $memberId): bool
    {
        $db = Database::getInstance();
        return $db->execute('DELETE FROM trainer_assignments WHERE member_id = ?', [$memberId]);
    }

    /**
     * Get memberships list with optional filtering.
     *
     * @param array{search?: string, plan?: string, status?: string} $filters
     * @return array<int, array<string, mixed>>
     */
    public function getMemberships(array $filters = []): array
    {
        $db = Database::getInstance();
        $where = '1=1';
        $params = [];

        if (!empty($filters['search'])) {
            $where .= ' AND m.full_name LIKE ?';
            $params[] = '%' . trim($filters['search']) . '%';
        }

        if (!empty($filters['plan'])) {
            $where .= ' AND ms.plan = ?';
            $params[] = trim($filters['plan']);
        }

        if (!empty($filters['status'])) {
            $where .= ' AND ms.status = ?';
            $params[] = trim($filters['status']);
        }

        return $db->fetchAll(
            "SELECT ms.id, m.full_name, ms.plan, ms.start_date, ms.end_date, ms.status
             FROM memberships ms
             JOIN members m ON ms.member_id = m.user_id
             WHERE $where
             ORDER BY ms.id DESC",
            $params
        );
    }

    /**
     * Delete a membership record by ID.
     *
     * @param int $id
     * @return bool
     */
    public function deleteMembership(int $id): bool
    {
        return Membership::deleteById($id);
    }

    /**
     * Get settings and admin profile.
     *
     * @param int $adminUserId
     * @return array{settings: array<string, mixed>, admin: array<string, mixed>|null}
     */
    public function getSettings(int $adminUserId): array
    {
        $settings = GymSettings::getAll();
        $db = Database::getInstance();
        $admin = $db->fetchOne('SELECT username, email FROM users WHERE id = ?', [$adminUserId]);

        return ['settings' => $settings, 'admin' => $admin];
    }

    /**
     * Update configuration settings by section.
     *
     * @param string $section
     * @param array<string, mixed> $body
     * @param int $adminUserId
     * @return void
     */
    public function updateSettings(string $section, array $body, int $adminUserId): void
    {
        $db = Database::getInstance();

        if ($section === 'gym') {
            $contactPhone = isset($body['gym_phone']) ? trim((string)$body['gym_phone']) : '';
            if (!preg_match("/^[0-9]{10}$/", $contactPhone)) {
                http_response_code(400);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Invalid phone number format']);
                exit;
            }

            $keys = ['gym_name', 'gym_address', 'gym_email', 'gym_phone'];
            foreach ($keys as $key) {
                if (isset($body[$key])) {
                    GymSettings::set($key, (string)$body[$key]);
                }
            }
            return;
        }

        if ($section === 'profile') {
            if (!empty($body['username'])) {
                $db->execute(
                    'UPDATE users SET username = ?, email = ? WHERE id = ?',
                    [$body['username'], $body['email'] ?? '', $adminUserId]
                );
            }
            return;
        }

        if ($section === 'password') {
            body_require($body, 'currentPassword', 'newPassword');
            $user = User::findById($adminUserId);
            if (!$user || !$user->verifyPassword($body['currentPassword'])) {
                json_error('Current password is incorrect', 401);
            }

            $user->setPlainPassword($body['newPassword']);
            $user->save();
            return;
        }

        if ($section === 'system') {
            $keys = ['allow_registrations', 'max_members'];
            foreach ($keys as $key) {
                if (isset($body[$key])) {
                    GymSettings::set($key, (string)$body[$key]);
                }
            }
            return;
        }

        json_error('Unknown section');
    }
}
