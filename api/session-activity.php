<?php
require_once dirname(__DIR__) . '/config/bootstrap.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$role = (string)($_SESSION['user_role'] ?? '');
if (!in_array($role, ['student', 'admin'], true)) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthenticated']);
    exit;
}

try {
    verify_csrf();
    $auth = new Auth(Database::getInstance());
    $auth->requireLogin($role);
    echo json_encode(['ok' => true]);
} catch (Throwable $e) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Session activity could not be recorded']);
}
