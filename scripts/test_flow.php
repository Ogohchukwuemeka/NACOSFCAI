<?php
require_once __DIR__ . '/../config/bootstrap.php';

$pdo = Database::getInstance()->getConnection();
echo "Starting test flow...\n";

try {
    // 1) Create sample event
    $title = 'Test Event ' . time();
    $venue = 'Main Hall';
    $eventDate = date('Y-m-d', strtotime('+7 days'));
    $desc = 'Automated test event for certificate generation.';
    $stmt = $pdo->prepare('INSERT INTO events (title, venue, event_date, description, is_upcoming, created_at) VALUES (:title, :venue, :event_date, :description, 1, NOW())');
    $stmt->execute(['title' => $title, 'venue' => $venue, 'event_date' => $eventDate, 'description' => $desc]);
    $eventId = (int)$pdo->lastInsertId();
    echo "Created event ID: $eventId\n";

    // 2) Create test user
    $timestamp = time();
    $email = "test+{$timestamp}@example.local";
    $fullName = 'Test Student ' . $timestamp;
    $matric = 'TEST/' . $timestamp;
    $passwordHash = password_hash('password123', PASSWORD_BCRYPT);

    $stmt = $pdo->prepare('INSERT INTO users (full_name, email, matric_number, department, level, password_hash, role, status, created_at) VALUES (:full_name, :email, :matric_number, :department, :level, :password_hash, "student", "active", NOW())');
    $stmt->execute(['full_name' => $fullName, 'email' => $email, 'matric_number' => $matric, 'department' => 'Computer Science', 'level' => 'ND I', 'password_hash' => $passwordHash]);
    $userId = (int)$pdo->lastInsertId();
    echo "Created user ID: $userId ($email)\n";

    // 3) Create students row (respect existing columns)
    $columns = [];
    $placeholders = [];
    $params = [];

    // Always set user_id and registration_number
    $columns[] = 'user_id'; $placeholders[] = ':user_id'; $params['user_id'] = $userId;
    $columns[] = 'registration_number'; $placeholders[] = ':registration_number'; $params['registration_number'] = $matric;
    $columns[] = 'course'; $placeholders[] = ':course'; $params['course'] = 'Computer Science';
    $columns[] = 'semester'; $placeholders[] = ':semester'; $params['semester'] = 'First Semester';
    $columns[] = 'phone'; $placeholders[] = ':phone'; $params['phone'] = '08000000000';
    $columns[] = 'address'; $placeholders[] = ':address'; $params['address'] = 'Campus';
    $columns[] = 'emergency_contact'; $placeholders[] = ':emergency_contact'; $params['emergency_contact'] = '08011111111';
    $columns[] = 'blood_group'; $placeholders[] = ':blood_group'; $params['blood_group'] = 'O+';
    $columns[] = 'session'; $placeholders[] = ':session'; $params['session'] = date('Y');

    // include gender if column exists
    $hasGender = (bool)$pdo->query("SHOW COLUMNS FROM students LIKE 'gender'")->fetch();
    if ($hasGender) {
        $columns[] = 'gender'; $placeholders[] = ':gender'; $params['gender'] = 'male';
    }

    $sql = 'INSERT INTO students (' . implode(', ', $columns) . ', created_at) VALUES (' . implode(', ', $placeholders) . ', NOW())';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $studentId = (int)$pdo->lastInsertId();
    echo "Created student ID: $studentId\n";

    // 4) Add event participant (verified)
    $stmt = $pdo->prepare('INSERT INTO event_participants (event_id, student_id, full_name, email, phone_number, department, level, payment_status, registered_at) VALUES (:event_id, :student_id, :full_name, :email, :phone, :department, :level, :payment_status, NOW())');
    $stmt->execute(['event_id' => $eventId, 'student_id' => $studentId, 'full_name' => $fullName, 'email' => $email, 'phone' => '08000000000', 'department' => 'Computer Science', 'level' => 'ND I', 'payment_status' => 'verified']);
    $participantId = (int)$pdo->lastInsertId();
    echo "Created participant ID: $participantId\n";

    // 5) Generate batch certificates
    $certificate = new Certificate(Database::getInstance());
    $count = $certificate->generateBatchCertificates($eventId);
    echo "Batch generation result: $count certificate(s) generated.\n";

    // List certificates for event
    $stmt = $pdo->prepare('SELECT id, certificate_number, pdf_path, status FROM certificates WHERE event_id = :event_id');
    $stmt->execute(['event_id' => $eventId]);
    $certs = $stmt->fetchAll();
    echo "Certificates for event $eventId:\n";
    foreach ($certs as $c) {
        echo " - ID: {$c['id']}, number: {$c['certificate_number']}, pdf: {$c['pdf_path']}, status: {$c['status']}\n";
    }

    echo "Test flow complete.\n";
} catch (Throwable $e) {
    echo 'Error: ' . $e->getMessage() . "\n";
}
