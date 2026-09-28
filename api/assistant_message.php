<?php
require_once dirname(__DIR__) . '/config/bootstrap.php';
header('Content-Type: application/json');
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        exit;
    }
    $payload = json_decode(file_get_contents('php://input'), true);
    if (!is_array($payload)) {
        $payload = $_POST;
    }
    $name = trim((string)($payload['name'] ?? 'Visitor'));
    $email = trim((string)($payload['email'] ?? ''));
    $message = trim((string)($payload['message'] ?? ''));
    if ($message === '') {
        http_response_code(400);
        echo json_encode(['error' => 'Message is required']);
        exit;
    }
    $portal = new Portal(Database::getInstance());
    $id = $portal->createVisitorMessage(['name' => $name, 'email' => $email, 'message' => $message]);
    echo json_encode(['ok' => true, 'id' => $id]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
