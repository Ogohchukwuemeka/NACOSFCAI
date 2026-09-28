<?php
require __DIR__ . '/../config/bootstrap.php';

$auth = new Auth(Database::getInstance());
$ok = $auth->login('admin@nacosfcaishiagu.edu', 'Admin@12345', 'admin');

if ($ok) {
    echo 'LOGIN_OK' . PHP_EOL;
    echo json_encode([
        'user_role' => $_SESSION['user_role'] ?? null,
        'user_name' => $_SESSION['user_name'] ?? null,
        'user_id' => $_SESSION['user_id'] ?? null,
    ], JSON_UNESCAPED_SLASHES) . PHP_EOL;
} else {
    echo 'LOGIN_FAIL' . PHP_EOL;
}
