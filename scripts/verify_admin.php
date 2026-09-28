<?php
$pdo = new PDO('mysql:host=localhost;dbname=nacosfcai;charset=utf8mb4', 'root', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

$email = 'admin@nacosfcaishiagu.edu';
$matric = 'ADMIN001';
$passwordHash = password_hash('Admin@12345', PASSWORD_BCRYPT);

$stmt = $pdo->prepare('SELECT id, email, role, status FROM users WHERE email = :email OR matric_number = :matric LIMIT 1');
$stmt->execute(['email' => $email, 'matric' => $matric]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user) {
    $pdo->prepare('UPDATE users SET full_name = :full_name, email = :email, matric_number = :matric, password_hash = :password_hash, role = :role, status = :status WHERE id = :id')->execute([
        'full_name' => 'System Administrator',
        'email' => $email,
        'matric' => $matric,
        'password_hash' => $passwordHash,
        'role' => 'admin',
        'status' => 'active',
        'id' => $user['id'],
    ]);
    echo "updated\n";
} else {
    $pdo->prepare('INSERT INTO users (full_name, email, matric_number, department, level, password_hash, role, status, created_at) VALUES (:full_name, :email, :matric_number, :department, :level, :password_hash, :role, :status, NOW())')->execute([
        'full_name' => 'System Administrator',
        'email' => $email,
        'matric_number' => $matric,
        'department' => 'ICT',
        'level' => '100',
        'password_hash' => $passwordHash,
        'role' => 'admin',
        'status' => 'active',
    ]);
    echo "inserted\n";
}

$stmt = $pdo->prepare('SELECT id, email, matric_number, role, status FROM users WHERE email = :email LIMIT 1');
$stmt->execute(['email' => $email]);
print_r($stmt->fetch(PDO::FETCH_ASSOC));
