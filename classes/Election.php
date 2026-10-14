<?php

class Election
{
    private Database $db;

    public function __construct(Database $database)
    {
        $this->db = $database;
    }

    // ===== ELECTION MANAGEMENT =====

    public function createElection(array $data): int
    {
        $pdo = $this->db->getConnection();
        $this->ensureElectionSchema($pdo);
        $startTime = (string)($data['start_time'] ?? date('Y-m-d H:i:s'));
        $endTime = (string)($data['end_time'] ?? date('Y-m-d H:i:s', strtotime('+7 days')));
        $status = (string)($data['status'] ?? 'draft');
        if (strtotime($startTime) === false || strtotime($endTime) === false || strtotime($startTime) >= strtotime($endTime)) {
            throw new InvalidArgumentException('Election start and end times are invalid.');
        }
        if (!in_array($status, ['draft', 'scheduled', 'active'], true)) {
            throw new InvalidArgumentException('Election status is invalid.');
        }
        if ($status === 'active' && (strtotime($startTime) > time() || strtotime($endTime) <= time())) {
            throw new InvalidArgumentException('Voting can only be opened within the scheduled election period.');
        }
        $stmt = $pdo->prepare('INSERT INTO elections (title, description, start_time, end_time, status, created_by) VALUES (:title, :description, :start_time, :end_time, :status, :created_by)');
        $stmt->execute([
            'title' => $data['title'] ?? '',
            'description' => $data['description'] ?? '',
            'start_time' => $startTime,
            'end_time' => $endTime,
            'status' => $status,
            'created_by' => $data['created_by'] ?? null,
        ]);
        $id = (int)$pdo->lastInsertId();
        if ($status === 'active') {
            $this->snapshotEligibleVoters($id);
        }
        return $id;
    }

    public function getElection(int $electionId): ?array
    {
        $stmt = $this->db->getConnection()->prepare('SELECT * FROM elections WHERE id = :id');
        $stmt->execute(['id' => $electionId]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function listElections(string $status = '', int $limit = 50, int $offset = 0): array
    {
        $sql = 'SELECT * FROM elections';
        $params = [];
        if ($status !== '') {
            $sql .= ' WHERE status = :status';
            $params['status'] = $status;
        }
        $sql .= ' ORDER BY start_time DESC';
        if ($limit > 0) {
            $sql .= ' LIMIT :limit OFFSET :offset';
        }
        $stmt = $this->db->getConnection()->prepare($sql);
        if ($limit > 0) {
            $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
        }
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getActiveElections(): array
    {
        $this->refreshAllElectionStatuses();
        $stmt = $this->db->getConnection()->prepare('SELECT * FROM elections WHERE status = :status AND start_time <= NOW() AND end_time > NOW() ORDER BY start_time ASC');
        $stmt->execute(['status' => 'active']);
        return $stmt->fetchAll();
    }

    public function refreshElectionStatus(int $electionId): bool
    {
        $election = $this->getElection($electionId);
        if (!$election) {
            return false;
        }
        $now = time();
        $start = strtotime($election['start_time']);
        $end = strtotime($election['end_time']);
        $currentStatus = $election['status'];
        $nextStatus = $currentStatus;

        if ($end <= $now) {
            $nextStatus = 'closed';
        } elseif ($currentStatus === 'scheduled') {
            if ($now >= $start) {
                $nextStatus = 'active';
            }
        } elseif ($currentStatus === 'active') {
            if ($now < $start) {
                $nextStatus = 'scheduled';
            }
        }

        if ($nextStatus !== $currentStatus) {
            return $this->updateElection($electionId, ['status' => $nextStatus]);
        }
        return true;
    }

    public function refreshAllElectionStatuses(): void
    {
        $elections = $this->listElections('', 0, 0);
        foreach ($elections as $election) {
            $this->refreshElectionStatus((int)$election['id']);
        }
    }

    public function updateElection(int $electionId, array $data): bool
    {
        $current = $this->getElection($electionId);
        if (!$current) {
            return false;
        }
        $newStatus = isset($data['status']) ? (string)$data['status'] : null;
        if ($newStatus !== null && !in_array($newStatus, ['draft', 'scheduled', 'active', 'closed'], true)) {
            return false;
        }
        $now = time();
        $start = strtotime((string)($data['start_time'] ?? $current['start_time']));
        $end = strtotime((string)($data['end_time'] ?? $current['end_time']));
        if ($start === false || $end === false || $start >= $end) {
            return false;
        }
        $locked = $this->isBallotLocked($electionId);
        $hasNonStatusChanges = isset($data['title']) || isset($data['description']) || isset($data['start_time']) || isset($data['end_time']);
        if ($locked && $hasNonStatusChanges) {
            return false;
        }
        if ($locked && $newStatus !== null && $newStatus !== $current['status'] && $newStatus !== 'closed') {
            $openingOnSchedule = in_array($current['status'], ['draft', 'scheduled'], true)
                && $newStatus === 'active' && $start <= $now && $end > $now && !$this->hasElectionVotes($electionId);
            $reopeningWithinWindow = $current['status'] === 'closed' && $newStatus === 'active'
                && $start <= $now && $end > $now;
            $correctingPrematureActivation = $current['status'] === 'active' && $newStatus === 'scheduled'
                && $start > $now && !$this->hasElectionVotes($electionId);
            if (!$openingOnSchedule && !$reopeningWithinWindow && !$correctingPrematureActivation) {
                return false;
            }
        }
        if ($newStatus === 'active' && ($start > $now || $end <= $now)) {
            return false;
        }
        if ($newStatus === 'closed' && $current['status'] !== 'active' && $end > $now) {
            return false;
        }
        $updates = [];
        $params = ['id' => $electionId];
        if (isset($data['title'])) {
            $updates[] = 'title = :title';
            $params['title'] = $data['title'];
        }
        if (isset($data['description'])) {
            $updates[] = 'description = :description';
            $params['description'] = $data['description'];
        }
        if (isset($data['start_time'])) {
            $updates[] = 'start_time = :start_time';
            $params['start_time'] = $data['start_time'];
        }
        if (isset($data['end_time'])) {
            $updates[] = 'end_time = :end_time';
            $params['end_time'] = $data['end_time'];
        }
        if (isset($data['status'])) {
            $updates[] = 'status = :status';
            $params['status'] = $data['status'];
        }
        if (empty($updates)) {
            return true;
        }
        $sql = 'UPDATE elections SET ' . implode(', ', $updates) . ' WHERE id = :id';
        $stmt = $this->db->getConnection()->prepare($sql);
        $updated = $stmt->execute($params);
        if ($updated && $newStatus === 'active') {
            $this->snapshotEligibleVoters($electionId);
        }
        return $updated;
    }

    public function deleteElection(int $electionId): bool
    {
        if ($this->hasElectionVotes($electionId)) {
            return false;
        }
        $stmt = $this->db->getConnection()->prepare('DELETE FROM elections WHERE id = :id');
        $stmt->execute(['id' => $electionId]);
        return $stmt->rowCount() > 0;
    }

    public function hasElectionVotes(int $electionId): bool
    {
        $stmt = $this->db->getConnection()->prepare('SELECT 1 FROM votes WHERE election_id = :election_id LIMIT 1');
        $stmt->execute(['election_id' => $electionId]);
        return (bool)$stmt->fetchColumn();
    }

    public function hasCandidateVotes(int $candidateId): bool
    {
        $stmt = $this->db->getConnection()->prepare('SELECT 1 FROM votes WHERE candidate_id = :candidate_id LIMIT 1');
        $stmt->execute(['candidate_id' => $candidateId]);
        return (bool)$stmt->fetchColumn();
    }

    public function isBallotLocked(int $electionId): bool
    {
        $election = $this->getElection($electionId);
        if (!$election) {
            return true;
        }
        return in_array($election['status'], ['active', 'closed'], true)
            || strtotime((string)$election['start_time']) <= time()
            || $this->hasElectionVotes($electionId);
    }

    public function isElectionOpen(int $electionId): bool
    {
        $stmt = $this->db->getConnection()->prepare("SELECT 1 FROM elections WHERE id = :id AND status = 'active' AND start_time <= NOW() AND end_time > NOW() LIMIT 1");
        $stmt->execute(['id' => $electionId]);
        return (bool)$stmt->fetchColumn();
    }

    public function isResultsPublished(int $electionId): bool
    {
        $stmt = $this->db->getConnection()->prepare("SELECT 1 FROM elections WHERE id = :id AND status = 'closed' LIMIT 1");
        $stmt->execute(['id' => $electionId]);
        return (bool)$stmt->fetchColumn();
    }

    private function snapshotEligibleVoters(int $electionId): void
    {
        $pdo = $this->db->getConnection();
        $this->ensureColumn($pdo, 'elections', 'eligible_voter_count', 'INT UNSIGNED NULL DEFAULT NULL');
        $stmt = $pdo->prepare("UPDATE elections SET eligible_voter_count = (SELECT COUNT(*) FROM users WHERE role = 'student' AND status = 'active') WHERE id = :id AND eligible_voter_count IS NULL");
        $stmt->execute(['id' => $electionId]);
    }

    // ===== ELECTION POSITION MANAGEMENT =====

    public function createPosition(array $data): int
    {
        $pdo = $this->db->getConnection();
        $this->ensureElectionSchema($pdo);
        $electionId = (int)($data['election_id'] ?? 0);
        if ($electionId <= 0 || $this->isBallotLocked($electionId)) {
            throw new RuntimeException('Election positions cannot be changed after voting starts.');
        }
        $stmt = $pdo->prepare('INSERT INTO election_positions (election_id, position_name, description, display_order) VALUES (:election_id, :position_name, :description, :display_order)');
        $stmt->execute([
            'election_id' => $electionId,
            'position_name' => $data['position_name'] ?? '',
            'description' => $data['description'] ?? '',
            'display_order' => $data['display_order'] ?? 0,
        ]);
        return (int)$pdo->lastInsertId();
    }

    public function getPosition(int $positionId): ?array
    {
        $stmt = $this->db->getConnection()->prepare('SELECT * FROM election_positions WHERE id = :id');
        $stmt->execute(['id' => $positionId]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    private function ensureElectionSchema(PDO $pdo): void
    {
        $this->ensureTable($pdo, 'elections', "CREATE TABLE IF NOT EXISTS elections (
          id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          title VARCHAR(255) NOT NULL,
          description TEXT,
          start_time DATETIME NOT NULL,
          end_time DATETIME NOT NULL,
          status ENUM('draft','scheduled','active','closed') DEFAULT 'draft',
          created_by INT UNSIGNED,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
          INDEX idx_status (status),
          INDEX idx_dates (start_time, end_time)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
        $this->ensureTable($pdo, 'election_positions', "CREATE TABLE IF NOT EXISTS election_positions (
          id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          election_id INT UNSIGNED NOT NULL,
          position_name VARCHAR(100) NOT NULL,
          description TEXT,
          display_order INT DEFAULT 0,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          FOREIGN KEY (election_id) REFERENCES elections(id) ON DELETE CASCADE,
          UNIQUE KEY unique_position_per_election (election_id, position_name),
          INDEX idx_election (election_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
        $this->ensureTable($pdo, 'candidates', "CREATE TABLE IF NOT EXISTS candidates (
          id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          election_id INT UNSIGNED NOT NULL,
          position_id INT UNSIGNED NOT NULL,
          student_id INT UNSIGNED NOT NULL,
          manifesto TEXT,
          image_path VARCHAR(255),
          image_upload_date DATETIME,
          approved TINYINT(1) DEFAULT 0,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          FOREIGN KEY (election_id) REFERENCES elections(id) ON DELETE CASCADE,
          FOREIGN KEY (position_id) REFERENCES election_positions(id) ON DELETE CASCADE,
          FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
          UNIQUE KEY unique_candidate_per_position (position_id, student_id),
          INDEX idx_election (election_id),
          INDEX idx_position (position_id),
          INDEX idx_student (student_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
        $this->ensureColumn($pdo, 'election_positions', 'description', 'TEXT');
        $this->ensureColumn($pdo, 'election_positions', 'display_order', 'INT DEFAULT 0');
        $this->ensureColumn($pdo, 'elections', 'description', 'TEXT');
        $this->ensureColumn($pdo, 'elections', 'eligible_voter_count', 'INT UNSIGNED NULL DEFAULT NULL');
        $this->ensureColumn($pdo, 'candidates', 'image_path', 'VARCHAR(255)');
        $this->ensureColumn($pdo, 'candidates', 'image_upload_date', 'DATETIME');
        $this->ensureColumn($pdo, 'candidates', 'approved', 'TINYINT(1) DEFAULT 0');
        $this->ensureColumn($pdo, 'candidates', 'created_at', 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP');
    }

    private function ensureTable(PDO $pdo, string $table, string $definition): void
    {
        $tableExists = $pdo->query("SHOW TABLES LIKE '{$table}'")->fetchColumn();
        if (!$tableExists) {
            $pdo->exec($definition);
        }
    }

    private function ensureColumn(PDO $pdo, string $table, string $column, string $definition): void
    {
        $columnExists = $pdo->query("SHOW COLUMNS FROM `{$table}` LIKE '{$column}'")->fetchColumn();
        if (!$columnExists) {
            $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
        }
    }

    public function listPositions(int $electionId): array
    {
        $pdo = $this->db->getConnection();
        $tableColumns = $pdo->query("SHOW COLUMNS FROM election_positions LIKE 'display_order'")->fetchColumn();
        if ($tableColumns) {
            $stmt = $pdo->prepare('SELECT * FROM election_positions WHERE election_id = :election_id ORDER BY display_order ASC');
        } else {
            $stmt = $pdo->prepare('SELECT * FROM election_positions WHERE election_id = :election_id ORDER BY id ASC');
        }
        $stmt->execute(['election_id' => $electionId]);
        return $stmt->fetchAll();
    }

    public function updatePosition(int $positionId, array $data, ?int $electionId = null): bool
    {
        $position = $this->getPosition($positionId);
        if (!$position || ($electionId !== null && (int)$position['election_id'] !== $electionId) || $this->isBallotLocked((int)$position['election_id'])) {
            return false;
        }
        $updates = [];
        $params = ['id' => $positionId];
        $scope = '';
        if ($electionId !== null) {
            $scope = ' AND election_id = :election_id';
            $params['election_id'] = $electionId;
        }
        if (isset($data['position_name'])) {
            $updates[] = 'position_name = :position_name';
            $params['position_name'] = $data['position_name'];
        }
        if (isset($data['description'])) {
            $updates[] = 'description = :description';
            $params['description'] = $data['description'];
        }
        if (isset($data['display_order'])) {
            $updates[] = 'display_order = :display_order';
            $params['display_order'] = $data['display_order'];
        }
        if (empty($updates)) {
            return true;
        }
        $sql = 'UPDATE election_positions SET ' . implode(', ', $updates) . ' WHERE id = :id' . $scope;
        $stmt = $this->db->getConnection()->prepare($sql);
        return $stmt->execute($params);
    }

    public function deletePosition(int $positionId, ?int $electionId = null): bool
    {
        $position = $this->getPosition($positionId);
        if (!$position || ($electionId !== null && (int)$position['election_id'] !== $electionId) || $this->isBallotLocked((int)$position['election_id'])) {
            return false;
        }
        $sql = 'DELETE FROM election_positions WHERE id = :id';
        $params = ['id' => $positionId];
        if ($electionId !== null) {
            $sql .= ' AND election_id = :election_id';
            $params['election_id'] = $electionId;
        }
        $stmt = $this->db->getConnection()->prepare($sql);
        return $stmt->execute($params);
    }

    // ===== CANDIDATE MANAGEMENT =====

    public function createCandidate(array $data): int
    {
        $pdo = $this->db->getConnection();
        $this->ensureElectionSchema($pdo);
        $electionId = (int)($data['election_id'] ?? 0);
        if ($electionId <= 0 || $this->isBallotLocked($electionId)) {
            throw new RuntimeException('Candidates cannot be changed after voting starts.');
        }

        $positionId = 0;
        $positionName = trim((string)($data['position_name'] ?? ''));
        if ($positionName !== '') {
            $positionId = $this->getOrCreatePositionByName($electionId, $positionName);
        } elseif (!empty($data['position_id'])) {
            $positionId = (int)$data['position_id'];
        }

        $stmt = $pdo->prepare('INSERT INTO candidates (election_id, position_id, student_id, manifesto, image_path, approved) VALUES (:election_id, :position_id, :student_id, :manifesto, :image_path, :approved)');
        $stmt->execute([
            'election_id' => $electionId,
            'position_id' => $positionId,
            'student_id' => $data['student_id'] ?? 0,
            'manifesto' => $data['manifesto'] ?? '',
            'image_path' => $data['image_path'] ?? '',
            'approved' => $data['approved'] ?? 0,
        ]);
        return (int)$pdo->lastInsertId();
    }

    public function getCandidate(int $candidateId): ?array
    {
        $stmt = $this->db->getConnection()->prepare('SELECT c.*, u.full_name, u.matric_number, s.course AS programme, u.department, u.level, ep.position_name FROM candidates c LEFT JOIN users u ON u.id = c.student_id LEFT JOIN students s ON s.user_id = u.id LEFT JOIN election_positions ep ON ep.id = c.position_id WHERE c.id = :id');
        $stmt->execute(['id' => $candidateId]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function listCandidates(int $electionId, ?int $positionId = null, bool $approvedOnly = false): array
    {
        $sql = 'SELECT c.*, u.full_name, u.matric_number, s.course AS programme, u.department, u.level, ep.position_name FROM candidates c LEFT JOIN users u ON u.id = c.student_id LEFT JOIN students s ON s.user_id = u.id LEFT JOIN election_positions ep ON ep.id = c.position_id WHERE c.election_id = :election_id';
        $params = ['election_id' => $electionId];
        if ($positionId !== null) {
            $sql .= ' AND c.position_id = :position_id';
            $params['position_id'] = $positionId;
        }
        if ($approvedOnly) {
            $sql .= ' AND c.approved = 1';
        }
        $sql .= ' ORDER BY c.created_at ASC';
        $stmt = $this->db->getConnection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function updateCandidate(int $candidateId, array $data, ?int $electionId = null): bool
    {
        $candidate = $this->getCandidate($candidateId);
        if (!$candidate || ($electionId !== null && (int)$candidate['election_id'] !== $electionId) || $this->isBallotLocked((int)$candidate['election_id'])) {
            return false;
        }
        $updates = [];
        $params = ['id' => $candidateId];
        $scope = '';
        if ($electionId !== null) {
            $scope = ' AND election_id = :election_id';
            $params['election_id'] = $electionId;
        }
        if (isset($data['manifesto'])) {
            $updates[] = 'manifesto = :manifesto';
            $params['manifesto'] = $data['manifesto'];
        }
        if (isset($data['image_path'])) {
            $updates[] = 'image_path = :image_path';
            $params['image_path'] = $data['image_path'];
            $updates[] = 'image_upload_date = NOW()';
        }
        if (isset($data['approved'])) {
            $updates[] = 'approved = :approved';
            $params['approved'] = $data['approved'];
        }
        if (isset($data['position_id'])) {
            $updates[] = 'position_id = :position_id';
            $params['position_id'] = $data['position_id'];
        }
        if (empty($updates)) {
            return true;
        }
        $sql = 'UPDATE candidates SET ' . implode(', ', $updates) . ' WHERE id = :id' . $scope;
        $stmt = $this->db->getConnection()->prepare($sql);
        return $stmt->execute($params);
    }

    public function deleteCandidate(int $candidateId, ?int $electionId = null): bool
    {
        $candidate = $this->getCandidate($candidateId);
        if (!$candidate || ($electionId !== null && (int)$candidate['election_id'] !== $electionId)
            || $this->isBallotLocked((int)$candidate['election_id']) || $this->hasCandidateVotes($candidateId)) {
            return false;
        }
        $sql = 'DELETE FROM candidates WHERE id = :id';
        $params = ['id' => $candidateId];
        if ($electionId !== null) {
            $sql .= ' AND election_id = :election_id';
            $params['election_id'] = $electionId;
        }
        $stmt = $this->db->getConnection()->prepare($sql);
        return $stmt->execute($params);
    }

    public function getOrCreatePositionByName(int $electionId, string $positionName): int
    {
        if ($this->isBallotLocked($electionId)) {
            throw new RuntimeException('Election positions cannot be changed after voting starts.');
        }
        $pdo = $this->db->getConnection();
        $stmt = $pdo->prepare('SELECT id FROM election_positions WHERE election_id = :election_id AND position_name = :position_name LIMIT 1');
        $stmt->execute(['election_id' => $electionId, 'position_name' => $positionName]);
        $existingId = $stmt->fetchColumn();
        if ($existingId) {
            return (int)$existingId;
        }
        $stmt = $pdo->prepare('INSERT INTO election_positions (election_id, position_name, description) VALUES (:election_id, :position_name, :description)');
        $stmt->execute(['election_id' => $electionId, 'position_name' => $positionName, 'description' => '']);
        return (int)$pdo->lastInsertId();
    }

    // ===== VOTING OPERATIONS =====

    public function recordVote(int $electionId, int $positionId, int $candidateId, int $studentId): bool
    {
        $pdo = $this->db->getConnection();
        $ownsTransaction = !$pdo->inTransaction();
        try {
            if ($ownsTransaction) {
                $pdo->beginTransaction();
            }
            $electionLock = $pdo->prepare('SELECT id FROM elections WHERE id = :election_id FOR UPDATE');
            $electionLock->execute(['election_id' => $electionId]);
            if (!$electionLock->fetchColumn()) {
                if ($ownsTransaction) {
                    $pdo->rollBack();
                }
                return false;
            }
            $validation = $pdo->prepare(
                "SELECT e.id
                 FROM elections e
                 INNER JOIN election_positions ep ON ep.election_id = e.id AND ep.id = :position_id
                 INNER JOIN candidates c ON c.election_id = e.id AND c.position_id = ep.id AND c.id = :candidate_id AND c.approved = 1
                 INNER JOIN users u ON u.id = :student_id AND u.role = 'student' AND u.status = 'active'
                 WHERE e.id = :election_id AND e.status = 'active' AND e.start_time <= NOW() AND e.end_time > NOW()
                 LIMIT 1"
            );
            $validation->execute([
                'election_id' => $electionId,
                'position_id' => $positionId,
                'candidate_id' => $candidateId,
                'student_id' => $studentId,
            ]);
            if (!$validation->fetchColumn()) {
                if ($ownsTransaction) {
                    $pdo->rollBack();
                }
                return false;
            }

            $stmt = $pdo->prepare('INSERT INTO votes (election_id, position_id, candidate_id, student_id) VALUES (:election_id, :position_id, :candidate_id, :student_id)');
            $recorded = $stmt->execute([
                'election_id' => $electionId,
                'position_id' => $positionId,
                'candidate_id' => $candidateId,
                'student_id' => $studentId,
            ]);
            if ($ownsTransaction) {
                $pdo->commit();
            }
            return $recorded;
        } catch (PDOException $e) {
            // Unique constraint violation - student already voted for this position
            if ($ownsTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return false;
        }
    }

    public function hasStudentVoted(int $studentId, int $positionId, int $electionId): bool
    {
        $stmt = $this->db->getConnection()->prepare('SELECT COUNT(*) FROM votes WHERE student_id = :student_id AND position_id = :position_id AND election_id = :election_id');
        $stmt->execute(['student_id' => $studentId, 'position_id' => $positionId, 'election_id' => $electionId]);
        return (int)$stmt->fetchColumn() > 0;
    }

    public function getStudentVoteCandidateId(int $studentId, int $positionId, int $electionId): ?int
    {
        $stmt = $this->db->getConnection()->prepare('SELECT candidate_id FROM votes WHERE student_id = :student_id AND position_id = :position_id AND election_id = :election_id LIMIT 1');
        $stmt->execute(['student_id' => $studentId, 'position_id' => $positionId, 'election_id' => $electionId]);
        $candidateId = $stmt->fetchColumn();
        return $candidateId === false ? null : (int)$candidateId;
    }

    public function getCandidateVoteCount(int $candidateId): int
    {
        $stmt = $this->db->getConnection()->prepare('SELECT COUNT(*) FROM votes WHERE candidate_id = :candidate_id');
        $stmt->execute(['candidate_id' => $candidateId]);
        return (int)$stmt->fetchColumn();
    }

    public function getElectionResults(int $electionId, bool $approvedOnly = false): array
    {
        if (!$this->isResultsPublished($electionId)) {
            return [];
        }
        $results = [];
        $positions = $this->listPositions($electionId);
        foreach ($positions as $position) {
            // Keep legacy votes visible even if a candidate was later unapproved.
            $candidates = $this->listCandidates($electionId, (int)$position['id'], false);
            $candidateResults = [];
            $totalVotes = 0;
            foreach ($candidates as $candidate) {
                $voteCount = $this->getCandidateVoteCount((int)$candidate['id']);
                if ($approvedOnly && (int)$candidate['approved'] !== 1 && $voteCount === 0) {
                    continue;
                }
                $totalVotes += $voteCount;
                $candidateResults[] = [
                    'id' => $candidate['id'],
                    'full_name' => $candidate['full_name'],
                    'image_path' => $candidate['image_path'],
                    'votes' => $voteCount,
                    'vote_count' => $voteCount,
                    'vote_percentage' => 0,
                    'percentage' => 0,
                ];
            }
            if ($totalVotes > 0) {
                foreach ($candidateResults as &$result) {
                    $result['percentage'] = round(($result['votes'] / $totalVotes) * 100, 2);
                    $result['vote_percentage'] = $result['percentage'];
                }
                unset($result);
            }
            usort($candidateResults, static fn($a, $b) => $b['votes'] <=> $a['votes']);
            $results[$position['id']] = [
                'position' => $position['position_name'],
                'position_name' => $position['position_name'],
                'position_id' => $position['id'],
                'total_votes' => $totalVotes,
                'candidates' => $candidateResults,
            ];
        }
        return $results;
    }

    public function getElectionStats(int $electionId): array
    {
        $pdo = $this->db->getConnection();
        $this->ensureColumn($pdo, 'elections', 'eligible_voter_count', 'INT UNSIGNED NULL DEFAULT NULL');
        $electionStmt = $pdo->prepare('SELECT status, eligible_voter_count FROM elections WHERE id = :id');
        $electionStmt->execute(['id' => $electionId]);
        $election = $electionStmt->fetch();
        if (!$election) {
            return ['total_registered_voters' => 0, 'eligible_voters' => 0, 'total_votes_cast' => 0, 'unique_voters' => 0, 'voter_turnout_percentage' => 0];
        }
        if ($election['eligible_voter_count'] === null && in_array($election['status'], ['active', 'closed'], true)) {
            $this->snapshotEligibleVoters($electionId);
            $electionStmt->execute(['id' => $electionId]);
            $election = $electionStmt->fetch();
        }
        $eligibleVoters = (int)($election['eligible_voter_count'] ?? 0);
        $voteStmt = $pdo->prepare('SELECT COUNT(*) AS ballots, COUNT(DISTINCT student_id) AS voters FROM votes WHERE election_id = :election_id');
        $voteStmt->execute(['election_id' => $electionId]);
        $counts = $voteStmt->fetch() ?: ['ballots' => 0, 'voters' => 0];
        $uniqueVoters = (int)$counts['voters'];
        $ballots = (int)$counts['ballots'];
        return [
            'total_registered_voters' => $eligibleVoters,
            'eligible_voters' => $eligibleVoters,
            'total_votes_cast' => $ballots,
            'unique_voters' => $uniqueVoters,
            'voter_turnout_percentage' => $eligibleVoters > 0 ? round(($uniqueVoters / $eligibleVoters) * 100, 2) : 0,
        ];
    }

    public function buildElectionResultsHtml(int $electionId): string
    {
        $election = $this->getElection($electionId);
        if (!$election) {
            return '<p>Election not found.</p>';
        }
        if (!$this->isResultsPublished($electionId)) {
            return '<p>Results are not available until the election is closed.</p>';
        }

        $results = $this->getElectionResults($electionId);
        $stats = $this->getElectionStats($electionId);

        $html = '<h2>' . htmlspecialchars($election['title']) . '</h2>';
        $html .= '<p>' . nl2br(htmlspecialchars($election['description'])) . '</p>';
        $html .= '<p><strong>Status:</strong> ' . htmlspecialchars(ucfirst((string)$election['status'])) . '</p>';
        $html .= '<p><strong>Start:</strong> ' . htmlspecialchars((string)$election['start_time']) . ' | <strong>End:</strong> ' . htmlspecialchars((string)$election['end_time']) . '</p>';

        $html .= '<table border="1" cellpadding="8" cellspacing="0" width="100%">';
        $html .= '<tr><th>Position</th><th>Candidate</th><th>Votes</th><th>Percentage</th></tr>';
        foreach ($results as $position) {
            $positionName = htmlspecialchars((string)$position['position_name']);
            foreach ($position['candidates'] as $candidate) {
                $html .= '<tr>';
                $html .= '<td>' . $positionName . '</td>';
                $html .= '<td>' . htmlspecialchars((string)$candidate['full_name']) . '</td>';
                $html .= '<td>' . (int)$candidate['votes'] . '</td>';
                $html .= '<td>' . number_format((float)$candidate['percentage'], 2) . '%</td>';
                $html .= '</tr>';
            }
        }
        $html .= '</table>';
        $html .= '<p><strong>Ballots Cast:</strong> ' . (int)$stats['total_votes_cast'] . '</p>';
        return $html;
    }

    public function getActiveStudentEmails(): array
    {
        $stmt = $this->db->getConnection()->query("SELECT email, full_name FROM users WHERE role = 'student' AND status = 'active' AND email != ''");
        return $stmt->fetchAll();
    }

    public function sendElectionResultsEmail(int $electionId): bool
    {
        $mailer = new Mailer($this->db);
        $election = $this->getElection($electionId);
        if (!$election || !$this->isResultsPublished($electionId)) {
            return false;
        }

        $subject = 'Election Results: ' . ($election['title'] ?? 'Election');
        $message = $this->buildElectionResultsHtml($electionId);
        $recipients = $this->getActiveStudentEmails();
        $sentAny = false;

        foreach ($recipients as $recipient) {
            $to = $recipient['email'];
            $name = $recipient['full_name'] ?? 'Student';
            if ($mailer->send($to, $subject, $message, [], $name, true)) {
                $sentAny = true;
            }
        }

        return $sentAny;
    }

    public function generateResultsCsv(int $electionId): string
    {
        $results = $this->getElectionResults($electionId);
        $output = "Position, Candidate, Votes, Percentage\n";
        foreach ($results as $position) {
            foreach ($position['candidates'] as $candidate) {
                $line = [
                    str_replace('"', '""', $position['position_name']),
                    str_replace('"', '""', $candidate['full_name']),
                    (int)$candidate['votes'], 
                    number_format((float)$candidate['percentage'], 2),
                ];
                $output .= '"' . implode('","', $line) . '"\n';
            }
        }
        return $output;
    }

    public function generateResultsPdf(int $electionId): ?string
    {
        $html = '<html><head><meta charset="utf-8"><style>body{font-family:Arial, sans-serif;}table{width:100%;border-collapse:collapse;}th,td{border:1px solid #ccc;padding:8px;text-align:left;}th{background:#f5f5f5;}</style></head><body>';
        $html .= $this->buildElectionResultsHtml($electionId);
        $html .= '</body></html>';

        if (!class_exists('Dompdf\Dompdf')) {
            return null;
        }

        $dompdf = new Dompdf\Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        return $dompdf->output();
    }
}
