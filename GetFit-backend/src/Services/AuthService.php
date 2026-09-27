<?php

namespace App\Services;

use App\Core\Database;
use App\Models\User;
use App\Models\Member;
use App\Models\Membership;
use App\Models\ProgressEntry;
use App\Models\GymSettings;
use Exception;

/**
 * AuthService
 * Handles authentication, registration, session management, and authorization checks.
 */
class AuthService
{
    /**
     * Authenticate user credentials against role and status.
     *
     * @param string $username
     * @param string $password
     * @param string $role
     * @return array<string, mixed>
     */
    public function login(string $username, string $password, string $role): array
    {
        $user = User::findByUsernameAndRole($username, $role);

        if (!$user || !$user->verifyPassword($password)) {
            json_error('Invalid username or password', 401);
        }

        $db = Database::getInstance();

        // Check if trainer account is active
        if ($user->getRole() === 'trainer') {
            $status = $db->fetchColumn('SELECT status FROM trainers WHERE user_id = ?', [$user->getId()]);
            if ($status === 'inactive') {
                json_error('Your account has been deactivated. Contact admin.', 403);
            }
        }

        // Check if member account is active
        if ($user->getRole() === 'member') {
            $status = $db->fetchColumn('SELECT status FROM members WHERE user_id = ?', [$user->getId()]);
            if ($status === 'inactive') {
                json_error('Your account has been deactivated. Contact admin.', 403);
            }
        }

        // Start session if not started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $_SESSION['user_id'] = $user->getId();
        $_SESSION['role']    = $user->getRole();

        return [
            'id'       => $user->getId(),
            'role'     => $user->getRole(),
            'username' => $user->getUsername(),
            'email'    => $user->getEmail(),
        ];
    }

    /**
     * Terminate the user session.
     */
    public function logout(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    /**
     * Get the currently logged-in user session data.
     *
     * @return array{id: int, role: string}|null
     */
    public function getCurrentUser(): ?array
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (empty($_SESSION['user_id']) || empty($_SESSION['role'])) {
            return null;
        }
        return [
            'id'   => (int)$_SESSION['user_id'],
            'role' => (string)$_SESSION['role'],
        ];
    }

    /**
     * Authorize request by required role(s).
     *
     * @param string ...$roles
     * @return array{id: int, role: string}
     */
    public function requireRole(string ...$roles): array
    {
        $user = $this->getCurrentUser();
        if (!$user) {
            json_error('Not logged in', 401);
        }
        if (!in_array($user['role'], $roles, true)) {
            json_error('Forbidden', 403);
        }
        return $user;
    }

    /**
     * Register a new gym member.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function registerMember(array $data): array
    {
        $db = Database::getInstance();

        // 1. Username availability
        if (User::existsByUsername($data['username'])) {
            json_error('Username already taken');
        }

        // 2. Email format validation
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            json_error('Invalid email format');
        }

        // 3. Gym capacity and registration policy
        if (!GymSettings::isRegistrationAllowed()) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Registration is currently disabled by the administrator.']);
            exit;
        }

        if (GymSettings::isCapacityReached()) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'The gym has reached its maximum member capacity.']);
            exit;
        }

        $plan = $data['membershipPlan'] ?? 'monthly';
        $weight = isset($data['weight']) && $data['weight'] !== '' ? (float)$data['weight'] : null;
        $height = isset($data['height']) && $data['height'] !== '' ? (float)$data['height'] : null;
        $bmi = Member::calculateBmi($weight, $height);

        $db->beginTransaction();
        try {
            // Create user
            $user = new User('member', $data['username'], '', $data['email'] ?? '');
            $user->setPlainPassword($data['password']);
            $user->save();
            $uid = (int)$user->getId();

            $startDate = date('Y-m-d');

            // Insert member record
            $db->execute(
                'INSERT INTO members (user_id, full_name, age, gender, phone, height, weight, fitness_goal, registration_date, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $uid,
                    $data['fullName'] ?? '',
                    isset($data['age']) && $data['age'] !== '' ? (int)$data['age'] : null,
                    $data['gender'] ?? '',
                    $data['phone'] ?? '',
                    $height,
                    $weight,
                    $data['fitnessGoal'] ?? '',
                    $startDate,
                    'active',
                ]
            );

            // Create membership
            Membership::createForMember($uid, $plan, $startDate);

            // Initial progress entry
            if ($weight && $bmi) {
                ProgressEntry::log($uid, $startDate, $weight, $bmi, 'Initial weight at registration');
            }

            $db->commit();

            // Auto-login
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $_SESSION['user_id'] = $uid;
            $_SESSION['role']    = 'member';

            return [
                'id'       => $uid,
                'role'     => 'member',
                'username' => $data['username'],
            ];
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            json_error('Registration failed: ' . $e->getMessage(), 500);
        }
    }
}
