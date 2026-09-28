<?php

class Portal
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function getSettings(): array
    {
        $pdo = $this->db->getConnection();
        try {
            $stmt = $pdo->prepare('SELECT * FROM settings ORDER BY id DESC LIMIT 1');
            $stmt->execute();
            $row = $stmt->fetch();
            if ($row) {
                return $row;
            }
        } catch (Throwable $e) {
            // ignore and try fallback
        }

        // Fallback: read legacy system_settings (key/value) table if present
        try {
            $stmt = $pdo->prepare('SELECT key_name, value_text FROM system_settings');
            $stmt->execute();
            $rows = $stmt->fetchAll();
            $settings = [];
            foreach ($rows as $r) {
                $settings[$r['key_name']] = $r['value_text'];
            }
            // map common legacy keys to expected keys
            $mapped = [];
            $mapped['contact_email'] = $settings['contact_email'] ?? ($settings['email'] ?? null);
            $mapped['contact_phone'] = $settings['contact_phone'] ?? ($settings['phone'] ?? null);
            $mapped['bank_name'] = $settings['bank_name'] ?? null;
            $mapped['account_name'] = $settings['account_name'] ?? null;
            $mapped['account_number'] = $settings['account_number'] ?? null;
            $mapped['payment_instructions'] = $settings['payment_instructions'] ?? null;
            $mapped['smtp_host'] = $settings['smtp_host'] ?? null;
            $mapped['smtp_port'] = $settings['smtp_port'] ?? null;
            $mapped['smtp_username'] = $settings['smtp_username'] ?? null;
            $mapped['smtp_password'] = $settings['smtp_password'] ?? null;
            $mapped['smtp_encryption'] = $settings['smtp_encryption'] ?? null;
            $mapped['department_name'] = $settings['department_name'] ?? null;
            $mapped['current_academic_session'] = $settings['current_academic_session'] ?? null;
            return $mapped;
        } catch (Throwable $e) {
            return [];
        }
    }

    public function saveSettings(array $data): void
    {
        $pdo = $this->db->getConnection();
        $stmt = $pdo->prepare('INSERT INTO settings (department_name, department_logo, address, contact_email, contact_phone, current_academic_session, registration_status, maintenance_mode, bank_name, account_name, account_number, payment_instructions, smtp_host, smtp_port, smtp_username, smtp_password, smtp_encryption, facebook, tiktok, x_handle, updated_at) VALUES (:department_name, :department_logo, :address, :contact_email, :contact_phone, :current_academic_session, :registration_status, :maintenance_mode, :bank_name, :account_name, :account_number, :payment_instructions, :smtp_host, :smtp_port, :smtp_username, :smtp_password, :smtp_encryption, :facebook, :tiktok, :x_handle, NOW())');
        $stmt->execute($data);
    }

    public function getDashboardStats(): array
    {
        $pdo = $this->db->getConnection();
        $stats = [];
        $stats['students'] = (int)$pdo->query('SELECT COUNT(*) FROM students')->fetchColumn();
        $stats['resources'] = (int)$pdo->query('SELECT COUNT(*) FROM resources')->fetchColumn();
        $stats['executives'] = (int)$pdo->query('SELECT COUNT(*) FROM executives')->fetchColumn();
        $stats['events'] = (int)$pdo->query('SELECT COUNT(*) FROM events')->fetchColumn();
        $stats['payments'] = (int)$pdo->query('SELECT COUNT(*) FROM payments')->fetchColumn();
        $stats['announcements'] = (int)$pdo->query('SELECT COUNT(*) FROM announcements')->fetchColumn();
        $stats['tickets'] = (int)$pdo->query('SELECT COUNT(*) FROM support_tickets')->fetchColumn();
        $stats['verified_payments'] = (int)$pdo->query("SELECT COUNT(*) FROM payments WHERE status = 'verified'")->fetchColumn();
        return $stats;
    }

    public function ensurePaymentSchema(): void
    {
        $pdo = $this->db->getConnection();
        $this->ensureColumn($pdo, 'bills', 'academic_session', "VARCHAR(80) DEFAULT NULL");
        $this->ensureColumn($pdo, 'payments', 'rejection_reason', 'TEXT DEFAULT NULL');
        $this->ensureColumn($pdo, 'payments', 'verified_by', 'INT UNSIGNED DEFAULT NULL');
        $this->ensureColumn($pdo, 'payments', 'verified_at', 'DATETIME DEFAULT NULL');
        $this->ensureColumn($pdo, 'payments', 'membership_number', 'VARCHAR(120) DEFAULT NULL');

        $statusColumn = $pdo->query("SHOW COLUMNS FROM payments LIKE 'status'")->fetch();
        if ($statusColumn) {
            $type = $statusColumn['Type'] ?? '';
            if (stripos($type, 'verified') === false) {
                $pdo->exec("ALTER TABLE payments MODIFY COLUMN status ENUM('pending','approved','rejected','verified') NOT NULL DEFAULT 'pending'");
            }
        }
    }

    private function ensureColumn(PDO $pdo, string $table, string $column, string $definition): void
    {
        $exists = $pdo->query("SHOW COLUMNS FROM `{$table}` LIKE '{$column}'")->fetch();
        if (!$exists) {
            $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
        }
    }

    public function listPaymentCategories(bool $activeOnly = false): array
    {
        $this->ensurePaymentSchema();
        $pdo = $this->db->getConnection();
        $sql = 'SELECT * FROM bills';
        if ($activeOnly) {
            $sql .= ' WHERE status = "active"';
        }
        $sql .= ' ORDER BY created_at DESC, id DESC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function createPaymentCategory(array $data): int
    {
        $this->ensurePaymentSchema();
        $pdo = $this->db->getConnection();
        $stmt = $pdo->prepare('INSERT INTO bills (title, category, amount, description, due_date, status, academic_session, created_at) VALUES (:title, :category, :amount, :description, :due_date, :status, :academic_session, NOW())');
        $stmt->execute([
            'title' => $data['title'] ?? '',
            'category' => $data['category'] ?? 'payment',
            'amount' => $data['amount'] ?? 0,
            'description' => $data['description'] ?? '',
            'due_date' => $data['due_date'] ?? null,
            'status' => $data['status'] ?? 'active',
            'academic_session' => $data['academic_session'] ?? '',
        ]);
        return (int)$pdo->lastInsertId();
    }

    public function updatePaymentCategory(int $id, array $data): void
    {
        $this->ensurePaymentSchema();
        $pdo = $this->db->getConnection();
        $stmt = $pdo->prepare('UPDATE bills SET title = :title, category = :category, amount = :amount, description = :description, due_date = :due_date, status = :status, academic_session = :academic_session WHERE id = :id');
        $stmt->execute([
            'title' => $data['title'] ?? '',
            'category' => $data['category'] ?? 'payment',
            'amount' => $data['amount'] ?? 0,
            'description' => $data['description'] ?? '',
            'due_date' => $data['due_date'] ?? null,
            'status' => $data['status'] ?? 'active',
            'academic_session' => $data['academic_session'] ?? '',
            'id' => $id,
        ]);
    }

    public function setPaymentCategoryStatus(int $id, string $status): void
    {
        $this->ensurePaymentSchema();
        $stmt = $this->db->getConnection()->prepare('UPDATE bills SET status = :status WHERE id = :id');
        $stmt->execute(['status' => $status, 'id' => $id]);
    }

    public function deletePaymentCategory(int $id): void
    {
        $this->ensurePaymentSchema();
        $stmt = $this->db->getConnection()->prepare('DELETE FROM bills WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public function logAudit(int $userId, string $action, string $details): void
    {
        $stmt = $this->db->getConnection()->prepare('INSERT INTO audit_logs (user_id, action, details, created_at) VALUES (:user_id, :action, :details, NOW())');
        $stmt->execute(['user_id' => $userId, 'action' => $action, 'details' => $details]);
    }

    public function getPaymentDashboardStats(): array
    {
        $this->ensurePaymentSchema();
        $pdo = $this->db->getConnection();
        return [
            'categories' => (int)$pdo->query('SELECT COUNT(*) FROM bills')->fetchColumn(),
            'pending' => (int)$pdo->query("SELECT COUNT(*) FROM payments WHERE status = 'pending'")->fetchColumn(),
            'verified' => (int)$pdo->query("SELECT COUNT(*) FROM payments WHERE status = 'verified'")->fetchColumn(),
            'rejected' => (int)$pdo->query("SELECT COUNT(*) FROM payments WHERE status = 'rejected'")->fetchColumn(),
            'revenue' => (float)$pdo->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'verified'")->fetchColumn(),
            'today' => (float)$pdo->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'verified' AND DATE(created_at) = CURDATE()")->fetchColumn(),
            'month' => (float)$pdo->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'verified' AND MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())")->fetchColumn(),
        ];
    }

    public function listPaymentsForAdmin(array $filters = []): array
    {
        $this->ensurePaymentSchema();
        $pdo = $this->db->getConnection();
        $where = [];
        $params = [];

        if (!empty($filters['session'])) {
            $where[] = 'p.session = :session';
            $params[':session'] = $filters['session'];
        }
        if (!empty($filters['payment_type'])) {
            $where[] = 'p.payment_type = :payment_type';
            $params[':payment_type'] = $filters['payment_type'];
        }
        if (!empty($filters['status'])) {
            $where[] = 'p.status = :status';
            $params[':status'] = $filters['status'];
        }
        if (!empty($filters['student_name'])) {
            $where[] = 'u.full_name LIKE :student_name';
            $params[':student_name'] = '%' . $filters['student_name'] . '%';
        }
        if (!empty($filters['search'])) {
            $where[] = '(u.full_name LIKE :search OR p.payment_type LIKE :search OR p.payment_reference LIKE :search OR p.session LIKE :search)';
            $params[':search'] = '%' . $filters['search'] . '%';
        }
        if (!empty($filters['date_from'])) {
            $where[] = 'DATE(p.created_at) >= :date_from';
            $params[':date_from'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[] = 'DATE(p.created_at) <= :date_to';
            $params[':date_to'] = $filters['date_to'];
        }

        $sql = 'SELECT p.*, u.full_name, u.email FROM payments p LEFT JOIN users u ON u.id = p.user_id';
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY p.created_at DESC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function verifyPaymentSubmission(int $paymentId, int $adminId): void
    {
        $this->ensurePaymentSchema();
        $pdo = $this->db->getConnection();

        $stmt = $pdo->prepare('SELECT membership_number FROM payments WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $paymentId]);
        $existingMembershipNumber = $stmt->fetchColumn();
        if (empty($existingMembershipNumber)) {
            $existingMembershipNumber = generateMembershipNumber($pdo);
        }

        $pdo->prepare('UPDATE payments SET status = :status, rejection_reason = NULL, verified_by = :verified_by, verified_at = NOW(), membership_number = :membership_number WHERE id = :id')
            ->execute(['status' => 'verified', 'verified_by' => $adminId, 'membership_number' => $existingMembershipNumber, 'id' => $paymentId]);
        $this->logAudit($adminId, 'verify_payment', 'Verified payment ID ' . $paymentId . ' and assigned membership number ' . $existingMembershipNumber);
    }

    public function rejectPaymentSubmission(int $paymentId, string $reason, int $adminId): void
    {
        $this->ensurePaymentSchema();
        $pdo = $this->db->getConnection();
        $pdo->prepare('UPDATE payments SET status = :status, rejection_reason = :reason, verified_by = :verified_by, verified_at = NOW() WHERE id = :id')
            ->execute(['status' => 'rejected', 'reason' => $reason, 'verified_by' => $adminId, 'id' => $paymentId]);
        $this->logAudit($adminId, 'reject_payment', 'Rejected payment ID ' . $paymentId . ': ' . $reason);
    }

    public function deletePaymentSubmission(int $paymentId, int $adminId): void
    {
        $this->ensurePaymentSchema();
        $pdo = $this->db->getConnection();
        $pdo->prepare('DELETE FROM payments WHERE id = :id')->execute(['id' => $paymentId]);
        $this->logAudit($adminId, 'delete_payment', 'Deleted payment ID ' . $paymentId);
    }

    public function listAnnouncements(int $limit = 8, int $offset = 0): array
    {
        $pdo = $this->db->getConnection();
        $hasCategoryColumn = (bool)$pdo->query("SHOW COLUMNS FROM announcements LIKE 'category'")->fetch();
        $select = $hasCategoryColumn ? 'SELECT id, title, body, category, created_at' : 'SELECT id, title, body, "" AS category, created_at';
        $stmt = $pdo->prepare($select . ' FROM announcements ORDER BY created_at DESC LIMIT :limit OFFSET :offset');
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function createAnnouncement(array $data): int
    {
        $pdo = $this->db->getConnection();
        $hasCategoryColumn = (bool)$pdo->query("SHOW COLUMNS FROM announcements LIKE 'category'")->fetch();

        if ($hasCategoryColumn) {
            $stmt = $pdo->prepare('INSERT INTO announcements (title, body, category, created_at) VALUES (:title, :body, :category, NOW())');
            $stmt->execute($data);
        } else {
            $stmt = $pdo->prepare('INSERT INTO announcements (title, body, created_at) VALUES (:title, :body, NOW())');
            $stmt->execute([
                'title' => $data['title'] ?? '',
                'body' => $data['body'] ?? '',
            ]);
        }

        return (int)$pdo->lastInsertId();
    }

    public function markAnnouncementRead(int $announcementId, int $userId): void
    {
        $stmt = $this->db->getConnection()->prepare('INSERT INTO notifications (user_id, message, is_read, created_at) VALUES (:user_id, :message, 1, NOW())');
        $stmt->execute([
            'user_id' => $userId,
            'message' => 'Announcement Read: You marked an announcement as read.',
        ]);
    }

    public function search(string $query, string $scope = 'admin', ?string $studentLevel = null): array
    {
        $q = '%' . trim($query) . '%';
        if ($scope === 'student') {
            $stmt = $this->db->getConnection()->prepare('SELECT id, title AS label, "resource" AS type FROM resources WHERE title LIKE :q AND level = :level UNION SELECT id, title AS label, "event" AS type FROM events WHERE title LIKE :event_q ORDER BY label LIMIT 10');
            $stmt->execute(['q' => $q, 'level' => $studentLevel ?? '', 'event_q' => $q]);
            $results = [];
            foreach ($stmt->fetchAll() as $result) {
                if ($result['type'] !== 'resource' || $this->getStudentResource((int)$result['id'], (string)$studentLevel)) {
                    $results[] = $result;
                }
            }
            return $results;
        }

        $stmt = $this->db->getConnection()->prepare('SELECT s.id, u.full_name AS label, "student" AS type FROM students s JOIN users u ON u.id = s.user_id WHERE u.full_name LIKE :q UNION SELECT id, full_name AS label, "executive" AS type FROM executives WHERE full_name LIKE :q UNION SELECT id, title AS label, "resource" AS type FROM resources WHERE title LIKE :q UNION SELECT id, title AS label, "event" AS type FROM events WHERE title LIKE :q UNION SELECT id, title AS label, "announcement" AS type FROM announcements WHERE title LIKE :q UNION SELECT id, subject AS label, "ticket" AS type FROM support_tickets WHERE subject LIKE :q LIMIT 20');
        $stmt->execute(['q' => $q]);
        return $stmt->fetchAll();
    }

    public function createEvent(array $data): int
    {
        $pdo = $this->db->getConnection();
        $hasVenueColumn = (bool)$pdo->query("SHOW COLUMNS FROM events LIKE 'venue'")->fetch();
        $hasTimeColumn = (bool)$pdo->query("SHOW COLUMNS FROM events LIKE 'event_time'")->fetch();
        $hasRegistrationLinkColumn = (bool)$pdo->query("SHOW COLUMNS FROM events LIKE 'registration_link'")->fetch();
        $hasPosterColumn = (bool)$pdo->query("SHOW COLUMNS FROM events LIKE 'poster_path'")->fetch();

        if ($hasVenueColumn) {
            $stmt = $pdo->prepare('INSERT INTO events (title, venue, event_date, event_time, description, registration_link, poster_path, is_upcoming, created_at) VALUES (:title, :venue, :event_date, :event_time, :description, :registration_link, :poster_path, :is_upcoming, NOW())');
            $stmt->execute([
                'title' => $data['title'] ?? '',
                'venue' => $data['venue'] ?? '',
                'event_date' => $data['event_date'] ?? null,
                'event_time' => $data['event_time'] ?? null,
                'description' => $data['description'] ?? '',
                'registration_link' => $data['registration_link'] ?? null,
                'poster_path' => $data['poster_path'] ?? null,
                'is_upcoming' => $data['is_upcoming'] ?? 1,
            ]);
        } else {
            $stmt = $pdo->prepare('INSERT INTO events (title, description, event_date, location, created_at) VALUES (:title, :description, :event_date, :location, NOW())');
            $stmt->execute([
                'title' => $data['title'] ?? '',
                'description' => $data['description'] ?? '',
                'event_date' => $data['event_date'] ?? null,
                'location' => $data['venue'] ?? '',
            ]);
        }

        return (int)$pdo->lastInsertId();
    }

    public function listEvents(int $limit = 6, int $offset = 0): array
    {
        $stmt = $this->db->getConnection()->prepare('SELECT * FROM events ORDER BY event_date DESC LIMIT :limit OFFSET :offset');
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function createResource(array $data): int
    {
        $pdo = $this->db->getConnection();
        $semester = $this->normalizeSemesterLabel($data['semester'] ?? '');
        $hasProgrammeColumn = (bool)$pdo->query("SHOW COLUMNS FROM resources LIKE 'programme'")->fetch();

        if ($hasProgrammeColumn) {
            $stmt = $pdo->prepare('INSERT INTO resources (title, programme, level, semester, course, file_path, file_type, uploaded_by, created_at) VALUES (:title, :programme, :level, :semester, :course, :file_path, :file_type, :uploaded_by, NOW())');
            $stmt->execute([
                'title' => $data['title'] ?? '',
                'programme' => $data['programme'] ?? '',
                'level' => $data['level'] ?? '',
                'semester' => $semester,
                'course' => $data['course'] ?? '',
                'file_path' => $data['file_path'] ?? '',
                'file_type' => $data['file_type'] ?? 'file',
                'uploaded_by' => $data['uploaded_by'] ?? 0,
            ]);
        } else {
            $data['semester'] = $semester;
            $stmt = $pdo->prepare('INSERT INTO resources (title, description, file_name, file_type, level, semester, course, file_path, uploaded_by, created_at) VALUES (:title, :description, :file_name, :file_type, :level, :semester, :course, :file_path, :uploaded_by, NOW())');
            $stmt->execute($data);
        }

        return (int)$pdo->lastInsertId();
    }

    public function listResources(int $limit = 8, int $offset = 0): array
    {
        $stmt = $this->db->getConnection()->prepare('SELECT * FROM resources ORDER BY created_at DESC LIMIT :limit OFFSET :offset');
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function listResourcesByLevel(string $level, string $semester, int $limit = 20): array
    {
        $semester = $this->normalizeSemesterLabel($semester);
        $alternateSemester = $semester === 'First Semester' ? 'First' : ($semester === 'Second Semester' ? 'Second' : $semester);

        $stmt = $this->db->getConnection()->prepare('SELECT * FROM resources WHERE level = :level AND semester IN (:semester, :alternate_semester) ORDER BY created_at DESC LIMIT :limit');
        $stmt->bindValue('level', $level);
        $stmt->bindValue('semester', $semester);
        $stmt->bindValue('alternate_semester', $alternateSemester);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $resources = [];
        foreach ($stmt->fetchAll() as $resource) {
            $metadata = $this->getResourceMetadata((int)$resource['id']);
            if (($metadata['visibility'] ?? 'hidden') === 'visible') {
                $resources[] = $resource;
            }
        }
        return $resources;
    }

    public function getStudentResource(int $resourceId, string $studentLevel): ?array
    {
        $stmt = $this->db->getConnection()->prepare('SELECT * FROM resources WHERE id = :id AND level = :level LIMIT 1');
        $stmt->execute(['id' => $resourceId, 'level' => $studentLevel]);
        $resource = $stmt->fetch();
        if (!$resource) {
            return null;
        }
        $resource['metadata'] = $this->getResourceMetadata($resourceId);
        if (($resource['metadata']['visibility'] ?? 'hidden') !== 'visible') {
            return null;
        }
        return $resource;
    }

    private function getResourceMetadata(int $resourceId): array
    {
        $path = APP_ROOT . '/assets/uploads/resources/_meta/' . $resourceId . '.json';
        if (!is_file($path)) {
            return [];
        }
        $metadata = json_decode((string)file_get_contents($path), true);
        return is_array($metadata) ? $metadata : [];
    }

    private function normalizeSemesterLabel(string $semester): string
    {
        $semester = trim($semester);
        if ($semester === '') {
            return '';
        }

        $normalized = strtolower(preg_replace('/[^a-z]/', '', $semester));
        if ($normalized === 'first' || $normalized === 'firstsemester') {
            return 'First Semester';
        }
        if ($normalized === 'second' || $normalized === 'secondsemester') {
            return 'Second Semester';
        }

        return $semester;
    }

    public function getAcademicSessions(): array
    {
        $pdo = $this->db->getConnection();
        $tableExists = (bool)$pdo->query("SHOW TABLES LIKE 'academic_sessions'")->fetchColumn();
        if ($tableExists) {
            $stmt = $pdo->prepare('SELECT * FROM academic_sessions ORDER BY session_name DESC');
            $stmt->execute();
            return $stmt->fetchAll();
        }

        $sessions = [];
        $currentYear = (int)date('Y');
        for ($offset = -3; $offset <= 5; $offset++) {
            $year = $currentYear + $offset;
            $sessions[] = ['id' => $year, 'session_name' => sprintf('%d/%d', $year, $year + 1)];
        }
        return $sessions;
    }

    private function resolveUserId(int $value): int
    {
        if ($value <= 0) {
            return 0;
        }

        $pdo = $this->db->getConnection();
        $stmt = $pdo->prepare('SELECT id FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $value]);
        if ($stmt->fetch()) {
            return $value;
        }

        $stmt = $pdo->prepare('SELECT user_id FROM students WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $value]);
        $student = $stmt->fetch();
        return (int)($student['user_id'] ?? 0);
    }

    public function getStudentPayments(int $studentId): array
    {
        $userId = $this->resolveUserId($studentId);
        $stmt = $this->db->getConnection()->prepare('SELECT * FROM payments WHERE user_id = :user_id ORDER BY created_at DESC');
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    }

    public function createPayment(array $data): int
    {
        $paymentReference = $data['payment_reference'] ?? sprintf('PAY-%s-%s', strtoupper(bin2hex(random_bytes(4))), time());
        $paymentProof = $data['payment_proof'] ?? null;
        $paymentAccountId = $data['payment_account_id'] ?? null;
        if ($paymentAccountId === null) {
            $account = $this->getActivePaymentAccount();
            $paymentAccountId = $account['id'] ?? null;
        }

        $userId = $this->resolveUserId((int)($data['user_id'] ?? 0));
        if ($userId <= 0) {
            throw new RuntimeException('A valid user account is required to submit a payment.');
        }

        $stmt = $this->db->getConnection()->prepare('INSERT INTO payments (user_id, payment_account_id, payment_type, session, amount, payment_reference, payment_proof, status, created_at) VALUES (:user_id, :payment_account_id, :payment_type, :session, :amount, :payment_reference, :payment_proof, :status, NOW())');
        $stmt->execute([
            'user_id' => $userId,
            'payment_account_id' => $paymentAccountId,
            'payment_type' => $data['payment_type'] ?? 'NACOS Dues',
            'session' => $data['session'] ?? '',
            'amount' => $data['amount'] ?? 0,
            'payment_reference' => $paymentReference,
            'payment_proof' => $paymentProof,
            'status' => $data['status'] ?? 'pending',
        ]);
        return (int)$this->db->getConnection()->lastInsertId();
    }

    public function getStudentNotifications(int $studentId): array
    {
        $userId = $this->resolveUserId($studentId);
        $stmt = $this->db->getConnection()->prepare('SELECT * FROM notifications WHERE user_id = :user_id ORDER BY created_at DESC');
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    }

    public function getRecentCommunityPosts(int $limit = 50, int $viewerStudentId = 0): array
    {
        $this->ensureCommunityPostsSchema();
        $pdo = $this->db->getConnection();
        $sql = 'SELECT id, student_id, sender, content, created_at FROM community_posts';
        if ($viewerStudentId > 0) {
            $sql .= ' WHERE NOT EXISTS (SELECT 1 FROM community_post_hidden WHERE post_id = community_posts.id AND student_id = :viewer_student_id)';
        }
        $sql .= ' ORDER BY created_at ASC LIMIT :limit';
        $stmt = $pdo->prepare($sql);
        if ($viewerStudentId > 0) {
            $stmt->bindValue(':viewer_student_id', $viewerStudentId, PDO::PARAM_INT);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $posts = [];
        foreach ($stmt->fetchAll() as $row) {
            $posts[] = [
                'id' => (int)$row['id'],
                'studentId' => (int)$row['student_id'],
                'sender' => $row['sender'] ?? 'Student',
                'text' => $row['content'] ?? '',
                'timestamp' => !empty($row['created_at']) ? date('g:i A', strtotime($row['created_at'])) : '',
                'createdAt' => !empty($row['created_at']) ? strtotime($row['created_at']) : 0,
                'reactions' => $this->getCommunityReactionCounts((int)$row['id']),
            ];
        }
        return $posts;
    }

    public function getCommunityPost(int $postId): ?array
    {
        $this->ensureCommunityPostsSchema();
        $pdo = $this->db->getConnection();
        $stmt = $pdo->prepare('SELECT id, student_id, sender, content, created_at FROM community_posts WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $postId]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }
        return [
            'id' => (int)$row['id'],
            'studentId' => (int)$row['student_id'],
            'sender' => $row['sender'] ?? 'Student',
            'text' => $row['content'] ?? '',
            'timestamp' => !empty($row['created_at']) ? date('g:i A', strtotime($row['created_at'])) : '',
        ];
    }

    public function createCommunityPost(int $studentId, string $content, string $sender): int
    {
        $this->ensureCommunityPostsSchema();
        $pdo = $this->db->getConnection();
        $stmt = $pdo->prepare('INSERT INTO community_posts (student_id, sender, content, created_at, updated_at) VALUES (:student_id, :sender, :content, NOW(), NOW())');
        $stmt->execute([
            'student_id' => $studentId,
            'sender' => $sender,
            'content' => $content,
        ]);
        return (int)$pdo->lastInsertId();
    }

    public function updateCommunityPost(int $postId, int $studentId, string $content): bool
    {
        $this->ensureCommunityPostsSchema();
        $stmt = $this->db->getConnection()->prepare("UPDATE community_posts SET content = :content, updated_at = NOW() WHERE id = :id AND student_id = :student_id AND created_at >= DATE_SUB(NOW(), INTERVAL 3 MINUTE)");
        return $stmt->execute(['content' => $content, 'id' => $postId, 'student_id' => $studentId]) && $stmt->rowCount() > 0;
    }

    public function deleteCommunityPost(int $postId, int $studentId): bool
    {
        $this->ensureCommunityPostsSchema();
        $stmt = $this->db->getConnection()->prepare('DELETE FROM community_posts WHERE id = :id AND student_id = :student_id');
        return $stmt->execute(['id' => $postId, 'student_id' => $studentId]) && $stmt->rowCount() > 0;
    }

    public function hideCommunityPostForStudent(int $postId, int $studentId): bool
    {
        $this->ensureCommunityPostsSchema();
        $stmt = $this->db->getConnection()->prepare('INSERT IGNORE INTO community_post_hidden (post_id, student_id) VALUES (:post_id, :student_id)');
        return $stmt->execute(['post_id' => $postId, 'student_id' => $studentId]);
    }

    public function deleteCommunityPostForEveryone(int $postId, int $studentId): bool
    {
        $this->ensureCommunityPostsSchema();
        $stmt = $this->db->getConnection()->prepare("DELETE FROM community_posts WHERE id = :id AND student_id = :student_id AND created_at >= DATE_SUB(NOW(), INTERVAL 3 MINUTE)");
        return $stmt->execute(['id' => $postId, 'student_id' => $studentId]) && $stmt->rowCount() > 0;
    }

    public function toggleCommunityReaction(int $postId, int $studentId, string $reaction): array
    {
        $this->ensureCommunityPostsSchema();
        $pdo = $this->db->getConnection();
        $find = $pdo->prepare('SELECT reaction FROM community_post_reactions WHERE post_id = :post_id AND student_id = :student_id LIMIT 1');
        $find->execute(['post_id' => $postId, 'student_id' => $studentId]);
        $existing = $find->fetchColumn();
        if ($existing === $reaction) {
            $stmt = $pdo->prepare('DELETE FROM community_post_reactions WHERE post_id = :post_id AND student_id = :student_id');
            $stmt->execute(['post_id' => $postId, 'student_id' => $studentId]);
        } elseif ($existing !== false) {
            $stmt = $pdo->prepare('UPDATE community_post_reactions SET reaction = :reaction WHERE post_id = :post_id AND student_id = :student_id');
            $stmt->execute(['reaction' => $reaction, 'post_id' => $postId, 'student_id' => $studentId]);
        } else {
            $stmt = $pdo->prepare('INSERT INTO community_post_reactions (post_id, student_id, reaction) VALUES (:post_id, :student_id, :reaction)');
            $stmt->execute(['post_id' => $postId, 'student_id' => $studentId, 'reaction' => $reaction]);
        }
        return $this->getCommunityReactionCounts($postId);
    }

    private function getCommunityReactionCounts(int $postId): array
    {
        $stmt = $this->db->getConnection()->prepare('SELECT reaction, COUNT(*) AS reaction_count FROM community_post_reactions WHERE post_id = :post_id GROUP BY reaction');
        $stmt->execute(['post_id' => $postId]);
        $counts = [];
        foreach ($stmt->fetchAll() as $row) {
            $counts[] = ['emoji' => $row['reaction'], 'count' => (int)$row['reaction_count']];
        }
        return $counts;
    }

    public function getCommunityUsername(int $studentId): ?string
    {
        $this->ensureCommunityPostsSchema();
        $stmt = $this->db->getConnection()->prepare('SELECT community_username FROM students WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $studentId]);
        $username = $stmt->fetchColumn();
        return $username === false || $username === null ? null : (string)$username;
    }

    public function setCommunityUsername(int $studentId, string $username): bool
    {
        $this->ensureCommunityPostsSchema();
        $stmt = $this->db->getConnection()->prepare('UPDATE students SET community_username = :username WHERE id = :id');
        return $stmt->execute(['username' => $username, 'id' => $studentId]);
    }

    public function ensureCommunityPostsSchema(): void
    {
        $pdo = $this->db->getConnection();
        $exists = $pdo->query("SHOW COLUMNS FROM community_posts LIKE 'sender'")->fetch();
        if (!$exists) {
            $pdo->exec("ALTER TABLE community_posts ADD COLUMN sender VARCHAR(80) NOT NULL DEFAULT 'Student' AFTER student_id");
        }
        $usernameColumn = $pdo->query("SHOW COLUMNS FROM students LIKE 'community_username'")->fetch();
        if (!$usernameColumn) {
            $pdo->exec("ALTER TABLE students ADD COLUMN community_username VARCHAR(20) NULL AFTER user_id");
        }
        $usernameIndex = $pdo->query("SHOW INDEX FROM students WHERE Key_name = 'unique_community_username'")->fetch();
        if (!$usernameIndex) {
            $pdo->exec('CREATE UNIQUE INDEX unique_community_username ON students (community_username)');
        }
        $reactionTable = $pdo->query("SHOW TABLES LIKE 'community_post_reactions'")->fetchColumn();
        if (!$reactionTable) {
            $pdo->exec("CREATE TABLE community_post_reactions (
                post_id INT UNSIGNED NOT NULL,
                student_id INT UNSIGNED NOT NULL,
                reaction VARCHAR(16) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (post_id, student_id),
                FOREIGN KEY (post_id) REFERENCES community_posts(id) ON DELETE CASCADE,
                FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        }
        $hiddenTable = $pdo->query("SHOW TABLES LIKE 'community_post_hidden'")->fetchColumn();
        if (!$hiddenTable) {
            $pdo->exec("CREATE TABLE community_post_hidden (
                post_id INT UNSIGNED NOT NULL,
                student_id INT UNSIGNED NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (post_id, student_id),
                FOREIGN KEY (post_id) REFERENCES community_posts(id) ON DELETE CASCADE,
                FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        }
    }

    public function createNotification(int $userId, string $title, string $message): void
    {
        $stmt = $this->db->getConnection()->prepare('INSERT INTO notifications (user_id, message, is_read, created_at) VALUES (:user_id, :message, 0, NOW())');
        $stmt->execute(['user_id' => $userId, 'message' => $title . ': ' . $message]);
    }

    public function ensureSupportSchema(): void
    {
        $pdo = $this->db->getConnection();

        $this->ensureSupportColumn($pdo, 'support_tickets', 'priority', "ENUM('low','medium','high','critical') NOT NULL DEFAULT 'medium'");
        $this->ensureSupportColumn($pdo, 'support_tickets', 'assigned_to', 'INT UNSIGNED DEFAULT NULL');
        $this->ensureSupportColumn($pdo, 'support_tickets', 'assigned_at', 'TIMESTAMP NULL DEFAULT NULL');
        $this->ensureSupportColumn($pdo, 'support_tickets', 'last_updated_by', 'INT UNSIGNED DEFAULT NULL');
        $this->ensureSupportColumn($pdo, 'support_tickets', 'updated_at', 'TIMESTAMP NULL DEFAULT NULL');
        $this->ensureSupportColumn($pdo, 'support_tickets', 'closed_at', 'TIMESTAMP NULL DEFAULT NULL');
        $this->ensureSupportColumn($pdo, 'support_tickets', 'reopened_at', 'TIMESTAMP NULL DEFAULT NULL');

        $this->ensureSupportTable($pdo, 'support_ticket_events', <<<'SQL'
CREATE TABLE support_ticket_events (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ticket_id INT UNSIGNED NOT NULL,
  event_type VARCHAR(60) NOT NULL,
  actor_id INT UNSIGNED DEFAULT NULL,
  details TEXT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_support_ticket_events_ticket (ticket_id)
) ENGINE=InnoDB
SQL
        );

        $this->ensureSupportTable($pdo, 'support_ticket_attachments', <<<'SQL'
CREATE TABLE support_ticket_attachments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ticket_id INT UNSIGNED NOT NULL,
  message_id INT UNSIGNED DEFAULT NULL,
  file_name VARCHAR(255) NOT NULL,
  file_path VARCHAR(255) NOT NULL,
  file_type VARCHAR(80) DEFAULT NULL,
  uploaded_by INT UNSIGNED DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_support_ticket_attachments_ticket (ticket_id)
) ENGINE=InnoDB
SQL
        );
    }

    private function ensureSupportColumn(PDO $pdo, string $table, string $column, string $definition): void
    {
        $exists = $pdo->query("SHOW COLUMNS FROM `{$table}` LIKE '{$column}'")->fetch();
        if (!$exists) {
            $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
        }
    }

    private function ensureSupportTable(PDO $pdo, string $table, string $definition): void
    {
        $exists = $pdo->query("SHOW TABLES LIKE '{$table}'")->fetchColumn();
        if (!$exists) {
            $pdo->exec($definition);
        }
    }

    public function ensureVisitorMessagesSchema(): void
    {
        $pdo = $this->db->getConnection();
        $this->ensureSupportTable($pdo, 'visitor_messages', <<<'SQL'
CREATE TABLE visitor_messages (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  email VARCHAR(150) DEFAULT NULL,
  message TEXT NOT NULL,
  admin_reply TEXT DEFAULT NULL,
  status ENUM('new','pending','resolved') NOT NULL DEFAULT 'new',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_visitor_messages_status (status),
  INDEX idx_visitor_messages_email (email)
) ENGINE=InnoDB
SQL
        );
    }

    public function createVisitorMessage(array $data): int
    {
        $this->ensureVisitorMessagesSchema();
        $pdo = $this->db->getConnection();
        $stmt = $pdo->prepare('INSERT INTO visitor_messages (name, email, message, status, created_at) VALUES (:name, :email, :message, :status, NOW())');
        $stmt->execute([
            'name' => $data['name'] ?? 'Visitor',
            'email' => $data['email'] ?? '',
            'message' => $data['message'] ?? '',
            'status' => $data['status'] ?? 'new',
        ]);
        return (int)$pdo->lastInsertId();
    }

    public function listVisitorMessages(array $filters = []): array
    {
        $this->ensureVisitorMessagesSchema();
        $pdo = $this->db->getConnection();
        $sql = 'SELECT * FROM visitor_messages';
        $params = [];
        $where = [];
        if (!empty($filters['status'])) {
            $where[] = 'status = :status';
            $params[':status'] = $filters['status'];
        }
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY created_at DESC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function createSupportTicket(array $data): int
    {
        $this->ensureSupportSchema();
        $pdo = $this->db->getConnection();
        $userId = $this->resolveUserId((int)($data['student_id'] ?? $data['user_id'] ?? 0));
        $stmt = $pdo->prepare('INSERT INTO support_tickets (user_id, subject, category, message, status, priority, created_at) VALUES (:user_id, :subject, :category, :message, :status, :priority, NOW())');
        $stmt->execute([
            'user_id' => $userId,
            'subject' => $data['subject'] ?? '',
            'category' => $data['category'] ?? 'Others',
            'message' => $data['message'] ?? '',
            'status' => $data['status'] ?? 'open',
            'priority' => $data['priority'] ?? 'medium',
        ]);
        return (int)$pdo->lastInsertId();
    }

    public function getStudentTickets(int $studentId): array
    {
        $this->ensureSupportSchema();
        $userId = $this->resolveUserId($studentId);
        $stmt = $this->db->getConnection()->prepare('SELECT * FROM support_tickets WHERE user_id = :user_id ORDER BY created_at DESC');
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    }

    public function listSupportTickets(array $filters = []): array
    {
        $this->ensureSupportSchema();
        $pdo = $this->db->getConnection();
        $where = [];
        $params = [];

        if (!empty($filters['search'])) {
            $where[] = '(u.full_name LIKE :search OR u.matric_number LIKE :search OR u.email LIKE :search OR t.id LIKE :search OR t.subject LIKE :search)';
            $params[':search'] = '%' . $filters['search'] . '%';
        }
        if (!empty($filters['status'])) {
            $where[] = 't.status = :status';
            $params[':status'] = $filters['status'];
        }
        if (!empty($filters['priority'])) {
            $where[] = 't.priority = :priority';
            $params[':priority'] = $filters['priority'];
        }
        if (!empty($filters['category'])) {
            $where[] = 't.category = :category';
            $params[':category'] = $filters['category'];
        }
        if (!empty($filters['assigned_to'])) {
            $where[] = 't.assigned_to = :assigned_to';
            $params[':assigned_to'] = (int)$filters['assigned_to'];
        }
        if (!empty($filters['date_from'])) {
            $where[] = 'DATE(t.created_at) >= :date_from';
            $params[':date_from'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[] = 'DATE(t.created_at) <= :date_to';
            $params[':date_to'] = $filters['date_to'];
        }

        $sql = 'SELECT t.*, u.full_name, u.email, u.matric_number, a.full_name AS assigned_admin_name FROM support_tickets t LEFT JOIN users u ON u.id = t.user_id LEFT JOIN users a ON a.id = t.assigned_to';
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY t.created_at DESC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getSupportStats(): array
    {
        $this->ensureSupportSchema();
        $pdo = $this->db->getConnection();
        $stats = [];
        $stats['total'] = (int)$pdo->query('SELECT COUNT(*) FROM support_tickets')->fetchColumn();
        $stats['open'] = (int)$pdo->query("SELECT COUNT(*) FROM support_tickets WHERE status = 'open'")->fetchColumn();
        $stats['pending'] = (int)$pdo->query("SELECT COUNT(*) FROM support_tickets WHERE status = 'pending'")->fetchColumn();
        $stats['in_progress'] = (int)$pdo->query("SELECT COUNT(*) FROM support_tickets WHERE status = 'in_progress'")->fetchColumn();
        $stats['resolved'] = (int)$pdo->query("SELECT COUNT(*) FROM support_tickets WHERE status = 'resolved'")->fetchColumn();
        $stats['closed'] = (int)$pdo->query("SELECT COUNT(*) FROM support_tickets WHERE status = 'closed'")->fetchColumn();
        $stats['high_priority'] = (int)$pdo->query("SELECT COUNT(*) FROM support_tickets WHERE priority = 'high' OR priority = 'critical'")->fetchColumn();
        $stats['today'] = (int)$pdo->query("SELECT COUNT(*) FROM support_tickets WHERE DATE(created_at) = CURDATE()")->fetchColumn();
        return $stats;
    }

    public function getSupportTicketDetails(int $ticketId): array
    {
        $this->ensureSupportSchema();
        $stmt = $this->db->getConnection()->prepare('SELECT t.*, u.full_name, u.email, u.matric_number, u.department, u.level, s.course, s.phone, s.profile_photo, a.full_name AS assigned_admin_name FROM support_tickets t LEFT JOIN users u ON u.id = t.user_id LEFT JOIN students s ON s.user_id = u.id LEFT JOIN users a ON a.id = t.assigned_to WHERE t.id = :ticket_id LIMIT 1');
        $stmt->execute(['ticket_id' => $ticketId]);
        return $stmt->fetch() ?: [];
    }

    public function createSupportReply(int $ticketId, int $senderId, string $message, string $status = 'in_progress', array $attachments = [], bool $notify = true): int
    {
        $this->ensureSupportSchema();
        $pdo = $this->db->getConnection();
        $stmt = $pdo->prepare('INSERT INTO support_messages (ticket_id, sender_id, message, created_at) VALUES (:ticket_id, :sender_id, :message, NOW())');
        $stmt->execute(['ticket_id' => $ticketId, 'sender_id' => $senderId, 'message' => $message]);
        $messageId = (int)$pdo->lastInsertId();

        $pdo->prepare('UPDATE support_tickets SET status = :status, updated_at = NOW(), last_updated_by = :last_updated_by WHERE id = :ticket_id')->execute(['status' => $status, 'last_updated_by' => $senderId, 'ticket_id' => $ticketId]);
        $this->logSupportEvent($ticketId, 'replied', $senderId, 'Reply sent by administrator.');

        foreach ($attachments as $attachment) {
            if (empty($attachment['path'])) {
                continue;
            }
            $stmt = $pdo->prepare('INSERT INTO support_ticket_attachments (ticket_id, message_id, file_name, file_path, file_type, uploaded_by, created_at) VALUES (:ticket_id, :message_id, :file_name, :file_path, :file_type, :uploaded_by, NOW())');
            $stmt->execute([
                'ticket_id' => $ticketId,
                'message_id' => $messageId,
                'file_name' => $attachment['name'] ?? basename($attachment['path']),
                'file_path' => $attachment['path'],
                'file_type' => $attachment['type'] ?? null,
                'uploaded_by' => $senderId,
            ]);
        }

        $owner = $pdo->prepare('SELECT u.id, u.email, u.full_name FROM support_tickets t JOIN users u ON u.id = t.user_id WHERE t.id = :ticket_id LIMIT 1');
        $owner->execute(['ticket_id' => $ticketId]);
        $ownerRecord = $owner->fetch();
        if ($notify && $ownerRecord) {
            $this->createNotification((int)$ownerRecord['id'], 'Support Update', 'An administrator replied to your ticket.');
            $mailer = new Mailer($this->db);
            $mailer->send($ownerRecord['email'], 'Support Update', $message, $ownerRecord['full_name'] ?? 'Student');
        }

        return $messageId;
    }

    public function updateSupportTicketStatus(int $ticketId, string $status, int $actorId): void
    {
        $this->ensureSupportSchema();
        $pdo = $this->db->getConnection();
        $current = $pdo->query('SELECT status FROM support_tickets WHERE id = ' . (int)$ticketId)->fetchColumn();
        $updates = ['status' => $status, 'updated_at' => date('Y-m-d H:i:s'), 'last_updated_by' => $actorId];
        if ($status === 'closed') {
            $updates['closed_at'] = date('Y-m-d H:i:s');
        } elseif ($current === 'closed' && $status !== 'closed') {
            $updates['reopened_at'] = date('Y-m-d H:i:s');
        }
        $sql = 'UPDATE support_tickets SET status = :status, updated_at = :updated_at, last_updated_by = :last_updated_by';
        if (array_key_exists('closed_at', $updates)) {
            $sql .= ', closed_at = :closed_at';
        }
        if (array_key_exists('reopened_at', $updates)) {
            $sql .= ', reopened_at = :reopened_at';
        }
        $sql .= ' WHERE id = :ticket_id';
        $stmt = $pdo->prepare($sql);
        $params = ['status' => $status, 'updated_at' => $updates['updated_at'], 'last_updated_by' => $actorId, 'ticket_id' => $ticketId];
        if (array_key_exists('closed_at', $updates)) {
            $params['closed_at'] = $updates['closed_at'];
        }
        if (array_key_exists('reopened_at', $updates)) {
            $params['reopened_at'] = $updates['reopened_at'];
        }
        $stmt->execute($params);
        $this->logSupportEvent($ticketId, 'status_changed', $actorId, 'Status changed to ' . ucfirst($status));
        $this->notifyTicketOwner($ticketId, 'Support Status Update', 'The administrator updated the ticket status.');
    }

    public function updateSupportTicketPriority(int $ticketId, string $priority, int $actorId): void
    {
        $this->ensureSupportSchema();
        $pdo = $this->db->getConnection();
        $pdo->prepare('UPDATE support_tickets SET priority = :priority, updated_at = NOW(), last_updated_by = :actor_id WHERE id = :ticket_id')->execute(['priority' => $priority, 'actor_id' => $actorId, 'ticket_id' => $ticketId]);
        $this->logSupportEvent($ticketId, 'priority_changed', $actorId, 'Priority changed to ' . ucfirst($priority));
        $this->notifyTicketOwner($ticketId, 'Support Priority Update', 'The administrator updated the ticket priority.');
    }

    public function assignSupportTicket(int $ticketId, int $assignedTo, int $actorId): void
    {
        $this->ensureSupportSchema();
        $pdo = $this->db->getConnection();
        $pdo->prepare('UPDATE support_tickets SET assigned_to = :assigned_to, assigned_at = NOW(), updated_at = NOW(), last_updated_by = :actor_id WHERE id = :ticket_id')->execute(['assigned_to' => $assignedTo, 'actor_id' => $actorId, 'ticket_id' => $ticketId]);
        $this->logSupportEvent($ticketId, 'assigned', $actorId, 'Ticket assigned to administrator ID ' . $assignedTo);
        $this->notifyTicketOwner($ticketId, 'Support Assignment', 'An administrator was assigned to your ticket.');
    }

    public function deleteSupportTicket(int $ticketId): void
    {
        $this->ensureSupportSchema();
        $pdo = $this->db->getConnection();
        $pdo->prepare('DELETE FROM support_ticket_attachments WHERE ticket_id = :ticket_id')->execute(['ticket_id' => $ticketId]);
        $pdo->prepare('DELETE FROM support_messages WHERE ticket_id = :ticket_id')->execute(['ticket_id' => $ticketId]);
        $pdo->prepare('DELETE FROM support_ticket_events WHERE ticket_id = :ticket_id')->execute(['ticket_id' => $ticketId]);
        $pdo->prepare('DELETE FROM support_tickets WHERE id = :ticket_id')->execute(['ticket_id' => $ticketId]);
    }

    public function listSupportTicketAttachments(int $ticketId): array
    {
        $this->ensureSupportSchema();
        $stmt = $this->db->getConnection()->prepare('SELECT * FROM support_ticket_attachments WHERE ticket_id = :ticket_id ORDER BY created_at DESC');
        $stmt->execute(['ticket_id' => $ticketId]);
        return $stmt->fetchAll();
    }

    public function listSupportTicketEvents(int $ticketId): array
    {
        $this->ensureSupportSchema();
        $stmt = $this->db->getConnection()->prepare('SELECT e.*, u.full_name AS actor_name FROM support_ticket_events e LEFT JOIN users u ON u.id = e.actor_id WHERE e.ticket_id = :ticket_id ORDER BY e.created_at ASC');
        $stmt->execute(['ticket_id' => $ticketId]);
        return $stmt->fetchAll();
    }

    public function getSupportTicketMessages(int $ticketId): array
    {
        $this->ensureSupportSchema();
        $stmt = $this->db->getConnection()->prepare('SELECT m.*, u.full_name FROM support_messages m LEFT JOIN users u ON u.id = m.sender_id WHERE m.ticket_id = :ticket_id ORDER BY m.created_at ASC');
        $stmt->execute(['ticket_id' => $ticketId]);
        return $stmt->fetchAll();
    }

    private function logSupportEvent(int $ticketId, string $eventType, int $actorId, string $details): void
    {
        $stmt = $this->db->getConnection()->prepare('INSERT INTO support_ticket_events (ticket_id, event_type, actor_id, details, created_at) VALUES (:ticket_id, :event_type, :actor_id, :details, NOW())');
        $stmt->execute(['ticket_id' => $ticketId, 'event_type' => $eventType, 'actor_id' => $actorId, 'details' => $details]);
    }

    private function notifyTicketOwner(int $ticketId, string $title, string $message): void
    {
        $pdo = $this->db->getConnection();
        $owner = $pdo->prepare('SELECT u.id, u.email, u.full_name FROM support_tickets t JOIN users u ON u.id = t.user_id WHERE t.id = :ticket_id LIMIT 1');
        $owner->execute(['ticket_id' => $ticketId]);
        $ownerRecord = $owner->fetch();
        if ($ownerRecord) {
            $this->createNotification((int)$ownerRecord['id'], $title, $message);
            $mailer = new Mailer($this->db);
            $mailer->send($ownerRecord['email'], $title, $message, $ownerRecord['full_name'] ?? 'Student');
        }
    }

    public function listProgrammes(): array
    {
        return [
            ['slug' => 'computer-science', 'title' => 'Computer Science', 'image' => asset('images/CSc.jpeg'), 'description' => 'Build strong foundations in algorithms, systems, and software design.'],
            ['slug' => 'software-web-development', 'title' => 'Software & Web Development', 'image' => asset('images/SWD.jpeg'), 'description' => 'Learn modern web engineering, product development, and scalable digital solutions.'],
            ['slug' => 'artificial-intelligence', 'title' => 'Artificial Intelligence', 'image' => asset('images/AI.png'), 'description' => 'Explore AI systems, machine learning, and intelligent automation.']
        ];
    }

    public function getProgramme(string $slug): ?array
    {
        $programmes = $this->listProgrammes();
        foreach ($programmes as $programme) {
            if ($programme['slug'] === $slug) {
                return $programme;
            }
        }
        return null;
    }

    public function listExecutives(): array
    {
        $pdo = $this->db->getConnection();
        $this->ensureExecutiveSchema($pdo);
        $stmt = $pdo->prepare('SELECT e.id, e.user_id, e.full_name AS name, e.position, e.department, e.academic_level, e.biography, e.contact, e.passport_path, e.tenure_id, t.name AS tenure_name, e.created_at FROM executives e LEFT JOIN executive_tenures t ON t.id = e.tenure_id ORDER BY t.is_current DESC, t.name DESC, e.id ASC');
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function listExecutiveTenures(): array
    {
        $pdo = $this->db->getConnection();
        $this->ensureExecutiveSchema($pdo);
        return $pdo->query('SELECT id, name, starts_on, ends_on, is_current FROM executive_tenures ORDER BY is_current DESC, name DESC')->fetchAll();
    }

    private function ensureExecutiveSchema(PDO $pdo): void
    {
        $pdo->exec("CREATE TABLE IF NOT EXISTS executive_tenures (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(120) NOT NULL UNIQUE, starts_on DATE DEFAULT NULL, ends_on DATE DEFAULT NULL, is_current TINYINT(1) NOT NULL DEFAULT 0, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB");
        $column = $pdo->query("SHOW COLUMNS FROM executives LIKE 'tenure_id'")->fetch();
        if (!$column) {
            $pdo->exec('ALTER TABLE executives ADD COLUMN tenure_id INT UNSIGNED DEFAULT NULL');
            $pdo->exec('ALTER TABLE executives ADD CONSTRAINT fk_executives_tenure FOREIGN KEY (tenure_id) REFERENCES executive_tenures(id) ON DELETE SET NULL');
        }
        $legacy = $pdo->query("SELECT id FROM executive_tenures WHERE name = 'Legacy' LIMIT 1")->fetchColumn();
        if (!$legacy && (int)$pdo->query('SELECT COUNT(*) FROM executives WHERE tenure_id IS NULL')->fetchColumn() > 0) {
            $pdo->exec("INSERT INTO executive_tenures (name, is_current) VALUES ('Legacy', 0)");
            $legacy = (int)$pdo->lastInsertId();
            $stmt = $pdo->prepare('UPDATE executives SET tenure_id = :tenure_id WHERE tenure_id IS NULL');
            $stmt->execute(['tenure_id' => $legacy]);
        }
    }

    public function listGalleryItems(string $category = ''): array
    {
        $pdo = $this->db->getConnection();
        $tableExists = (bool)$pdo->query("SHOW TABLES LIKE 'gallery'")->fetchColumn();
        if (!$tableExists) {
            return [];
        }

        $query = 'SELECT * FROM gallery';
        $params = [];
        if ($category !== '') {
            $query .= ' WHERE title LIKE :category';
            $params['category'] = '%' . $category . '%';
        }
        $query .= ' ORDER BY created_at DESC';
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $items = [];
        foreach ($stmt->fetchAll() as $item) {
            $metadataPath = APP_ROOT . '/assets/uploads/gallery/_meta/' . (int)$item['id'] . '.json';
            $metadata = is_file($metadataPath) ? json_decode((string)file_get_contents($metadataPath), true) : [];
            $metadata = is_array($metadata) ? $metadata : [];
            if (($metadata['visibility'] ?? 'visible') !== 'visible') {
                continue;
            }
            $item['display_order'] = (int)($metadata['display_order'] ?? 0);
            $items[] = $item;
        }
        usort($items, static function (array $left, array $right): int {
            if ($left['display_order'] === $right['display_order']) {
                return strcmp((string)$right['created_at'], (string)$left['created_at']);
            }
            return $left['display_order'] <=> $right['display_order'];
        });
        return $items;
    }

    public function getAnnouncementsList(int $limit = 10): array
    {
        $pdo = $this->db->getConnection();
        $hasCategoryColumn = (bool)$pdo->query("SHOW COLUMNS FROM announcements LIKE 'category'")->fetch();
        $select = $hasCategoryColumn ? 'SELECT id, title, body, category, created_at' : 'SELECT id, title, body, "" AS category, created_at';
        $stmt = $pdo->prepare($select . ' FROM announcements ORDER BY created_at DESC LIMIT :limit');
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function listPayments(int $limit = 10, int $offset = 0): array
    {
        $stmt = $this->db->getConnection()->prepare('SELECT p.*, u.full_name FROM payments p LEFT JOIN users u ON u.id = p.user_id ORDER BY p.created_at DESC LIMIT :limit OFFSET :offset');
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function verifyPayment(int $paymentId, string $membershipNo): void
    {
        $pdo = $this->db->getConnection();
        $stmt = $pdo->prepare('UPDATE payments SET status = :status, membership_number = :membership_number, paid_at = NOW() WHERE id = :id');
        $stmt->execute(['status' => 'verified', 'membership_number' => $membershipNo, 'id' => $paymentId]);
    }

    public function getActivePaymentAccount(): array
    {
        $stmt = $this->db->getConnection()->prepare('SELECT * FROM payment_accounts WHERE is_active = 1 ORDER BY id DESC LIMIT 1');
        $stmt->execute();
        return $stmt->fetch() ?: [];
    }

    public function getPaymentCategories(): array
    {
        $stmt = $this->db->getConnection()->prepare('SELECT * FROM bills WHERE status = "active" ORDER BY id ASC');
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function updateStudentProfile(int $studentId, array $data): void
    {
        $stmt = $this->db->getConnection()->prepare('SELECT user_id FROM students WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $studentId]);
        $student = $stmt->fetch();
        if (!$student) {
            throw new RuntimeException('Student record not found.');
        }

        $userUpdates = [];
        $userParams = ['user_id' => $student['user_id']];
        if (array_key_exists('email', $data)) {
            $userUpdates[] = 'email = :email';
            $userParams['email'] = trim((string)($data['email'] ?? ''));
        }

        if (array_key_exists('full_name', $data)) {
            $userUpdates[] = 'full_name = :full_name';
            $userParams['full_name'] = trim((string)($data['full_name'] ?? ''));
        }

        if (array_key_exists('department', $data)) {
            $userUpdates[] = 'department = :department';
            $userParams['department'] = trim((string)($data['department'] ?? ''));
        }

        if ($userUpdates) {
            $stmt = $this->db->getConnection()->prepare('UPDATE users SET ' . implode(', ', $userUpdates) . ' WHERE id = :user_id');
            $stmt->execute($userParams);
        }

        $studentUpdates = [];
        $params = ['id' => $studentId];
        if (array_key_exists('phone', $data)) {
            $studentUpdates[] = 'phone = :phone';
            $params['phone'] = trim((string)($data['phone'] ?? ''));
        }
        if (array_key_exists('course', $data)) {
            $studentUpdates[] = 'course = :course';
            $params['course'] = trim((string)($data['course'] ?? ''));
        }
        if (array_key_exists('profile_photo', $data)) {
            $studentUpdates[] = 'profile_photo = :profile_photo';
            $params['profile_photo'] = trim((string)($data['profile_photo'] ?? ''));
        }

        if (!empty($studentUpdates)) {
            $stmt = $this->db->getConnection()->prepare('UPDATE students SET ' . implode(', ', $studentUpdates) . ' WHERE id = :id');
            $stmt->execute($params);
        }
    }

    public function getStudentByUserId(int $userId): array
    {
        $stmt = $this->db->getConnection()->prepare('SELECT s.* FROM students s WHERE s.user_id = :user_id LIMIT 1');
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetch() ?: [];
    }

    public function getStudentPaymentSummary(int $userId): array
    {
        $userId = $this->resolveUserId($userId);
        $pdo = $this->db->getConnection();
        $stmt = $pdo->prepare('SELECT
            SUM(CASE WHEN status IN ("approved","verified") THEN 1 ELSE 0 END) AS verified_count,
            SUM(CASE WHEN status = "pending" THEN 1 ELSE 0 END) AS pending_count,
            SUM(CASE WHEN status = "rejected" THEN 1 ELSE 0 END) AS rejected_count,
            COALESCE(SUM(CASE WHEN status IN ("approved","verified") THEN amount ELSE 0 END), 0) AS total_paid,
            COALESCE(SUM(CASE WHEN status = "pending" THEN amount ELSE 0 END), 0) AS total_pending,
            COALESCE(SUM(CASE WHEN status = "rejected" THEN amount ELSE 0 END), 0) AS total_rejected,
            MAX(CASE WHEN status IN ("approved","verified") THEN updated_at ELSE NULL END) AS last_verified_at
            FROM payments WHERE user_id = :user_id');
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetch() ?: [];
    }

    public function countResourcesByLevelAndSemester(string $level, string $semester): int
    {
        $stmt = $this->db->getConnection()->prepare('SELECT COUNT(*) FROM resources WHERE level = :level AND semester = :semester');
        $stmt->execute(['level' => $level, 'semester' => $semester]);
        return (int)$stmt->fetchColumn();
    }

    public function getStudentSupportSummary(int $userId): array
    {
        $userId = $this->resolveUserId($userId);
        $stmt = $this->db->getConnection()->prepare('SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN status = "open" THEN 1 ELSE 0 END) AS open_count,
            SUM(CASE WHEN status = "in_progress" THEN 1 ELSE 0 END) AS in_progress_count,
            SUM(CASE WHEN status = "closed" THEN 1 ELSE 0 END) AS closed_count
            FROM support_tickets WHERE user_id = :user_id');
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetch() ?: [];
    }

    public function getStudentActivityLog(int $userId, int $limit = 6): array
    {
        $stmt = $this->db->getConnection()->prepare('SELECT * FROM activity_logs WHERE user_id = :user_id ORDER BY created_at DESC LIMIT :limit');
        $stmt->bindValue('user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getStudentUnreadNotificationCount(int $userId): int
    {
        $userId = $this->resolveUserId($userId);
        $stmt = $this->db->getConnection()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = :user_id AND is_read = 0');
        $stmt->execute(['user_id' => $userId]);
        return (int)$stmt->fetchColumn();
    }

    public function getUpcomingEvents(int $limit = 4): array
    {
        $stmt = $this->db->getConnection()->prepare('SELECT * FROM events WHERE event_date >= CURDATE() ORDER BY event_date ASC LIMIT :limit');
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getRecentAnnouncements(int $limit = 4): array
    {
        $stmt = $this->db->getConnection()->prepare('SELECT id, title, body, created_at FROM announcements ORDER BY created_at DESC LIMIT :limit');
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    // ===== GENDER FIELD HELPERS =====

    public function getStudentGender(int $userId): ?string
    {
        $pdo = $this->db->getConnection();
        $hasColumn = (bool)$pdo->query("SHOW COLUMNS FROM students LIKE 'gender'")->fetch();
        if (!$hasColumn) {
            return null;
        }
        
        $stmt = $pdo->prepare('SELECT gender FROM students WHERE user_id = :user_id');
        $stmt->execute(['user_id' => $userId]);
        $result = $stmt->fetchColumn();
        return $result ?: null;
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

    public function updateStudentGender(int $userId, string $gender): bool
    {
        $pdo = $this->db->getConnection();
        $hasColumn = (bool)$pdo->query("SHOW COLUMNS FROM students LIKE 'gender'")->fetch();
        if (!$hasColumn) {
            return false;
        }
        
        $stmt = $pdo->prepare('UPDATE students SET gender = :gender WHERE user_id = :user_id');
        return $stmt->execute(['gender' => $gender, 'user_id' => $userId]);
    }

    public function getGenderDistribution(): array
    {
        $pdo = $this->db->getConnection();
        $hasColumn = (bool)$pdo->query("SHOW COLUMNS FROM students LIKE 'gender'")->fetch();
        if (!$hasColumn) {
            return [];
        }
        
        $stmt = $pdo->query("SELECT gender, COUNT(*) AS count FROM students WHERE user_id IN (SELECT id FROM users WHERE role = 'student') GROUP BY gender");
        $results = [];
        foreach ($stmt->fetchAll() as $row) {
            $results[$row['gender'] ?? 'Unspecified'] = (int)$row['count'];
        }
        return $results;
    }

    // ===== CERTIFICATE HELPERS =====

    public function getStudentCertificates(int $userId): array
    {
        $stmt = $this->db->getConnection()->prepare('SELECT c.*, e.title AS event_title, e.event_date, e.venue FROM certificates c LEFT JOIN events e ON e.id = c.event_id WHERE c.student_id = :student_id ORDER BY c.created_at DESC');
        $stmt->execute(['student_id' => $userId]);
        return $stmt->fetchAll();
    }

    public function getCertificateDownloadUrl(int $certificateId): ?string
    {
        $stmt = $this->db->getConnection()->prepare('SELECT pdf_path FROM certificates WHERE id = :id');
        $stmt->execute(['id' => $certificateId]);
        $result = $stmt->fetchColumn();
        return $result ?: null;
    }

    public function markCertificateViewed(int $certificateId): bool
    {
        $stmt = $this->db->getConnection()->prepare('UPDATE certificates SET status = :status, viewed_at = NOW() WHERE id = :id');
        return $stmt->execute(['status' => 'viewed', 'id' => $certificateId]);
    }

    public function getEventCertificateStats(int $eventId): array
    {
        $pdo = $this->db->getConnection();
        $total = (int)$pdo->query("SELECT COUNT(*) FROM certificates WHERE event_id = $eventId")->fetchColumn();
        $generated = (int)$pdo->query("SELECT COUNT(*) FROM certificates WHERE event_id = $eventId AND status IN ('generated', 'sent', 'viewed')")->fetchColumn();
        $sent = (int)$pdo->query("SELECT COUNT(*) FROM certificates WHERE event_id = $eventId AND status IN ('sent', 'viewed')")->fetchColumn();
        
        return [
            'total' => $total,
            'generated' => $generated,
            'sent' => $sent,
            'pending' => $total - $generated,
        ];
    }
}
