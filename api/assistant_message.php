<?php
require_once dirname(__DIR__) . '/config/bootstrap.php';
header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
        exit;
    }

    $payload = json_decode((string)file_get_contents('php://input'), true);
    if (!is_array($payload)) {
        $payload = $_POST;
    }

    $name = trim((string)($payload['name'] ?? 'Guest'));
    $email = trim((string)($payload['email'] ?? ''));
    $message = trim((string)($payload['message'] ?? ''));

    if ($message === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Message is required']);
        exit;
    }

    $portal = new Portal(Database::getInstance());
    $reply = $portal->resolveAssistantReply($message);

    $portal->createVisitorMessage([
        'name' => $name !== '' ? $name : 'Guest',
        'email' => $email,
        'message' => $message,
        'status' => 'new',
    ]);

    echo json_encode([
        'ok' => true,
        'reply' => $reply,
        'message' => $message,
        'source' => 'knowledge-base',
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
