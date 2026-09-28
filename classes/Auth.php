<?php

class Auth
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function login(string $identifier, string $password, string $role): bool
    {
        $stmt = $this->db->getConnection()->prepare('SELECT * FROM users WHERE (email = :email OR matric_number = :matric_number) LIMIT 1');
        $stmt->execute(['email' => $identifier, 'matric_number' => $identifier]);
        $user = $stmt->fetch();
        if (!$user || !password_verify($password, $user['password_hash'])) {
            return false;
        }

        if ($role === 'student' && ($user['status'] ?? 'pending') !== 'active') {
            $_SESSION['login_error'] = ($user['status'] ?? '') === 'pending'
                ? 'Your registration is awaiting admin approval.'
                : 'Your student account is not active. Please contact an administrator.';
            return false;
        }

        $userRole = $user['role'] ?? 'student';
        if ($role === 'admin' && $userRole !== 'admin') {
            return false;
        }

        session_regenerate_id(true);
        $_SESSION['last_activity'] = time();
        $_SESSION['account_user_id'] = (int)$user['id'];

        if ($userRole === 'admin') {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_role'] = 'admin';
            $_SESSION['user_name'] = $user['full_name'] ?? 'Administrator';
        } elseif ($userRole === 'student') {
            $stmt2 = $this->db->getConnection()->prepare('SELECT * FROM students WHERE user_id = :user_id LIMIT 1');
            $stmt2->execute(['user_id' => $user['id']]);
            $student = $stmt2->fetch();
            if ($student) {
                $_SESSION['user_id'] = $student['id'];
            } else {
                $_SESSION['user_id'] = $user['id'];
            }
            $_SESSION['user_role'] = 'student';
            $_SESSION['user_name'] = $user['full_name'] ?? ($student['full_name'] ?? 'Student');
        } else {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_role'] = $userRole;
            $_SESSION['user_name'] = $user['full_name'] ?? 'User';
        }

        try {
            $email = $user['email'] ?? '';
            $fullName = $user['full_name'] ?? 'Student';

            if (!empty($email)) {
                $mailer = new Mailer($this->db);
                $message = sprintf(
                    '<p>Hello %s,</p><p>Your NACOS account was successfully accessed. If this was not you, please change your password immediately.</p><p>Login Time: %s</p>',
                    htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8'),
                    date('Y-m-d H:i:s')
                );

                $sent = $mailer->send($email, 'Login Alert', $message, [], $fullName, true);
                if (!$sent) {
                    $pdo = $this->db->getConnection();
                    $stmt = $pdo->prepare('INSERT INTO email_logs (recipient, subject, message, status, created_at) VALUES (:recipient, :subject, :message, :status, NOW())');
                    $payload = json_encode([
                        'message' => 'Your NACOS account was successfully accessed. If this was not you, please change your password immediately.',
                        'full_name' => $fullName,
                        'email' => $email,
                    ]);
                    $stmt->execute([
                        'recipient' => $email,
                        'subject' => 'Login Alert',
                        'message' => $payload,
                        'status' => 'pending',
                    ]);
                }
            }
        } catch (Throwable $e) {
            error_log('Failed to send or queue login alert: ' . $e->getMessage());
        }
        return true;
    }

    public function registerStudent(array $data): int
    {
        $pdo = $this->db->getConnection();
        $allowedLevels = ['ND I', 'ND II', 'HND I', 'HND II'];
        $data['level'] = trim((string)($data['level'] ?? ''));
        if (!in_array($data['level'], $allowedLevels, true)) {
            throw new Exception('Please select a valid academic level.');
        }
        $pdo->beginTransaction();
        try {
            $academicSession = $data['academic_session_id'] ?? null;
            if ($academicSession === '' || $academicSession === '0') {
                $academicSession = null;
            }

            $gender = $this->normalizeGender($data['gender'] ?? null);

            // Prevent duplicate registrations for the same email address.
            $existingUserStmt = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
            $existingUserStmt->execute(['email' => $data['email']]);
            if ($existingUserStmt->fetch()) {
                throw new Exception('An account with that email address already exists. Please use a different email or login instead.');
            }

            // Insert into users table
            $stmt = $pdo->prepare('INSERT INTO users (full_name, email, matric_number, department, level, password_hash, role, status, created_at) VALUES (:full_name, :email, :matric_number, :department, :level, :password_hash, :role, :status, NOW())');
            $stmt->execute([
                'full_name' => $data['full_name'],
                'email' => $data['email'],
                'matric_number' => $data['matric_number'] ?? null,
                'department' => $data['department'] ?? null,
                'level' => $data['level'] ?? null,
                'password_hash' => password_hash($data['password'], PASSWORD_BCRYPT),
                'role' => 'student',
                'status' => 'pending',
            ]);
            $userId = (int)$pdo->lastInsertId();

            // Insert into students table
            // Include gender if the column exists in students table
            $hasGender = (bool)$pdo->query("SHOW COLUMNS FROM students LIKE 'gender'")->fetch();
            if ($hasGender) {
                $stmt2 = $pdo->prepare('INSERT INTO students (user_id, registration_number, course, semester, phone, address, emergency_contact, blood_group, gender, profile_photo, session, created_at) VALUES (:user_id, :registration_number, :course, :semester, :phone, :address, :emergency_contact, :blood_group, :gender, :profile_photo, :session, NOW())');
                $stmt2->execute([
                    'user_id' => $userId,
                    'registration_number' => $data['matric_number'] ?? null,
                    'course' => $data['course_of_study'] ?? null,
                    'semester' => $data['semester'] ?? null,
                    'phone' => $data['phone'] ?? null,
                    'address' => $data['address'] ?? null,
                    'emergency_contact' => $data['emergency_contact'] ?? null,
                    'blood_group' => $data['blood_group'] ?? null,
                    'gender' => $gender,
                    'profile_photo' => $data['profile_photo'] ?? null,
                    'session' => $academicSession,
                ]);
            } else {
                $stmt2 = $pdo->prepare('INSERT INTO students (user_id, registration_number, course, semester, phone, address, emergency_contact, blood_group, profile_photo, session, created_at) VALUES (:user_id, :registration_number, :course, :semester, :phone, :address, :emergency_contact, :blood_group, :profile_photo, :session, NOW())');
                $stmt2->execute([
                    'user_id' => $userId,
                    'registration_number' => $data['matric_number'] ?? null,
                    'course' => $data['course_of_study'] ?? null,
                    'semester' => $data['semester'] ?? null,
                    'phone' => $data['phone'] ?? null,
                    'address' => $data['address'] ?? null,
                    'emergency_contact' => $data['emergency_contact'] ?? null,
                    'blood_group' => $data['blood_group'] ?? null,
                    'profile_photo' => $data['profile_photo'] ?? null,
                    'session' => $academicSession,
                ]);
            }

            $studentId = (int)$pdo->lastInsertId();

            $pdo->commit();

            // Attempt to send registration confirmation immediately and queue only if email delivery fails.
            $studentPayload = [
                'full_name' => $data['full_name'],
                'matric_number' => $data['matric_number'] ?? null,
                'email' => $data['email'],
                'department' => $data['department'] ?? null,
                'course_of_study' => $data['course_of_study'] ?? null,
                'level' => $data['level'] ?? null,
                'created_at' => date('Y-m-d H:i:s'),
            ];

            try {
                $mailer = new Mailer($this->db);
                $sent = $mailer->sendRegistrationEmail($studentPayload);

                if ($sent) {
                    $_SESSION['registration_email_status'] = 'sent';
                } else {
                    $stmtEmail = $pdo->prepare('INSERT INTO email_logs (recipient, subject, message, status, created_at) VALUES (:recipient, :subject, :message, :status, NOW())');
                    $payload = json_encode($studentPayload);
                    $stmtEmail->execute([
                        'recipient' => $data['email'],
                        'subject' => 'NACOS Registration Confirmation',
                        'message' => $payload,
                        'status' => 'pending',
                    ]);
                    $_SESSION['registration_email_status'] = 'queued';
                }
            } catch (Throwable $e) {
                error_log('Fail to queue registration email: ' . $e->getMessage());
                $_SESSION['registration_email_status'] = 'failed';
            }

            return $studentId;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    private function normalizeGender(?string $gender): ?string
    {
        if ($gender === null) {
            return null;
        }

        $value = trim(strtolower($gender));
        if ($value === 'male') {
            return 'Male';
        }
        if ($value === 'female') {
            return 'Female';
        }
        if ($value === 'other') {
            return 'Other';
        }
        if ($value === 'prefer_not' || $value === 'prefer not' || $value === 'prefer not to say') {
            return 'Prefer not to say';
        }

        return null;
    }

    private function ensureEmailLogsTableExists(PDO $pdo): void
    {
        $check = $pdo->query("SHOW TABLES LIKE 'email_logs'")->fetchColumn();
        if (!$check) {
            $pdo->exec(<<<'SQL'
CREATE TABLE email_logs (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  recipient VARCHAR(255) NOT NULL,
  subject VARCHAR(255) NOT NULL,
  message TEXT NOT NULL,
  status ENUM('pending','sent','failed') NOT NULL DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
SQL
            );
        }
    }
    public function logout(): void
    {
        session_unset();
        session_destroy();
    }

    public function requestPasswordReset(string $email): bool
    {
        $stmt = $this->db->getConnection()->prepare('SELECT u.id AS user_id, s.id AS student_id, u.full_name, u.email FROM users u LEFT JOIN students s ON s.user_id = u.id WHERE u.email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        $record = $stmt->fetch();
        if (!$record || empty($record['student_id'])) {
            // Do not disclose whether the email exists. Always return success for UI purposes.
            return true;
        }

        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', time() + 3600);
        $stmt = $this->db->getConnection()->prepare('INSERT INTO password_resets (student_id, token, expires_at, created_at) VALUES (:student_id, :token, :expires_at, NOW())');
        $stmt->execute(['student_id' => $record['student_id'], 'token' => $token, 'expires_at' => $expiresAt]);

        $resetUrl = (defined('APP_URL') ? APP_URL : '') . '/student/reset-password.php?token=' . urlencode($token);
        $name = $record['full_name'] ?? 'Student';
        $mailer = new Mailer($this->db);
        $mailer->sendPasswordResetEmail($record['email'], $name, $resetUrl);

        return true;
    }

    public function validatePasswordResetToken(string $token): bool
    {
        if (trim($token) === '') {
            return false;
        }

        $stmt = $this->db->getConnection()->prepare('SELECT 1 FROM password_resets pr JOIN students s ON s.id = pr.student_id WHERE pr.token = :token AND pr.used_at IS NULL AND pr.expires_at > NOW() LIMIT 1');
        $stmt->execute(['token' => $token]);
        return (bool)$stmt->fetchColumn();
    }

    public function resetPassword(string $token, string $password): bool
    {
        $stmt = $this->db->getConnection()->prepare('SELECT pr.student_id, s.user_id FROM password_resets pr JOIN students s ON s.id = pr.student_id WHERE pr.token = :token AND pr.used_at IS NULL AND pr.expires_at > NOW() LIMIT 1');
        $stmt->execute(['token' => $token]);
        $record = $stmt->fetch();
        if (!$record) {
            return false;
        }
        $userId = (int)$record['user_id'];
        $this->db->getConnection()->prepare('UPDATE users SET password_hash = :hash WHERE id = :id')->execute(['hash' => password_hash($password, PASSWORD_BCRYPT), 'id' => $userId]);
        $this->db->getConnection()->prepare('UPDATE password_resets SET used_at = NOW() WHERE token = :token')->execute(['token' => $token]);
        return true;
    }

    public function requireLogin(string $role = 'student'): void
    {
        if (empty($_SESSION['user_id']) || $_SESSION['user_role'] !== $role) {
            if (!empty($_SESSION['session_expired'])) {
                unset($_SESSION['session_expired']);
                $_SESSION['login_error'] = 'Your session expired. Please log in again.';
            }
            $loginPage = $role === 'admin' ? 'admin-login.php' : 'login.php';
            header('Location: ' . APP_URL . '/auth/' . $loginPage);
            exit;
        }

        $_SESSION['last_activity'] = time();
    }

    public function currentUser(): ?array
    {
        if (empty($_SESSION['user_id'])) {
            return null;
        }
        $role = $_SESSION['user_role'] ?? 'student';
        if ($role === 'admin') {
            $stmt = $this->db->getConnection()->prepare('SELECT * FROM users WHERE id = :id AND role = :role LIMIT 1');
            $stmt->execute(['id' => $_SESSION['user_id'], 'role' => 'admin']);
            return $stmt->fetch() ?: null;
        }

        $stmt = $this->db->getConnection()->prepare('SELECT s.*, u.full_name, u.email, u.level AS level, u.department, u.matric_number, u.status AS account_status FROM students s JOIN users u ON u.id = s.user_id WHERE s.id = :id LIMIT 1');
        $stmt->execute(['id' => $_SESSION['user_id']]);
        return $stmt->fetch() ?: null;
    }
}
