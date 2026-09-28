<?php
require_once dirname(__DIR__) . '/config/bootstrap.php';
header('Content-Type: application/json');
try {
    $auth = new Auth(Database::getInstance());
    $auth->requireLogin('student');
    $user = $auth->currentUser();
    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }

    $portal = new Portal(Database::getInstance());
    $studentId = (int)($user['id'] ?? 0);
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $posts = $portal->getRecentCommunityPosts(50, $studentId);
        echo json_encode([
            'ok' => true,
            'posts' => $posts,
            'currentStudentId' => $studentId,
            'username' => $portal->getCommunityUsername($studentId),
        ]);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $payload = json_decode(file_get_contents('php://input'), true);
        if (!is_array($payload)) {
            $payload = $_POST;
        }

        if (($payload['action'] ?? '') === 'set_username') {
            $username = trim((string)($payload['username'] ?? ''));
            if (!preg_match('/^[A-Za-z0-9_]{3,20}$/', $username) || preg_match('/^_|_$/', $username)) {
                http_response_code(422);
                echo json_encode(['error' => 'Username must be 3–20 characters using letters, numbers, and underscores.']);
                exit;
            }
            $reserved = ['admin', 'community', 'support', 'nacos', 'staff', 'system', 'root'];
            if (in_array(strtolower($username), $reserved, true)) {
                http_response_code(422);
                echo json_encode(['error' => 'This username is reserved. Please choose another.']);
                exit;
            }
            try {
                $portal->setCommunityUsername($studentId, $username);
            } catch (PDOException $e) {
                if ((int)($e->errorInfo[1] ?? 0) === 1062) {
                    http_response_code(409);
                    echo json_encode(['error' => 'That username is already in use. Please choose another.']);
                    exit;
                }
                throw $e;
            }
            echo json_encode(['ok' => true, 'username' => $username]);
            exit;
        }

        $action = (string)($payload['action'] ?? '');
        $postId = (int)($payload['id'] ?? 0);
        if (in_array($action, ['edit', 'delete_for_me', 'delete_for_everyone', 'react'], true) && $postId < 1) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid message selected.']);
            exit;
        }
        if ($action === 'edit') {
            $content = trim((string)($payload['content'] ?? ''));
            if ($content === '' || strlen($content) > 5000) {
                http_response_code(422);
                echo json_encode(['error' => 'Message must contain 1 to 5000 characters.']);
                exit;
            }
            if (!$portal->updateCommunityPost($postId, $studentId, $content)) {
                http_response_code(403);
                echo json_encode(['error' => 'You can only edit your own messages.']);
                exit;
            }
            echo json_encode(['ok' => true, 'id' => $postId, 'content' => $content]);
            exit;
        }
        if ($action === 'delete_for_me') {
            $portal->hideCommunityPostForStudent($postId, $studentId);
            echo json_encode(['ok' => true, 'id' => $postId]);
            exit;
        }
        if ($action === 'delete_for_everyone') {
            if (!$portal->deleteCommunityPostForEveryone($postId, $studentId)) {
                http_response_code(403);
                echo json_encode(['error' => 'Delete for everyone is available only for your message within 3 minutes.']);
                exit;
            }
            echo json_encode(['ok' => true, 'id' => $postId]);
            exit;
        }
        if ($action === 'delete') {
            if (!$portal->deleteCommunityPost($postId, $studentId)) {
                http_response_code(403);
                echo json_encode(['error' => 'You can only delete your own messages.']);
                exit;
            }
            echo json_encode(['ok' => true, 'id' => $postId]);
            exit;
        }
        if ($action === 'react') {
            $reaction = (string)($payload['reaction'] ?? '');
            if (!in_array($reaction, ['❤️', '😂', '👍', '😮', '👏', '🎉'], true)) {
                http_response_code(422);
                echo json_encode(['error' => 'Unsupported reaction.']);
                exit;
            }
            echo json_encode(['ok' => true, 'id' => $postId, 'reactions' => $portal->toggleCommunityReaction($postId, $studentId, $reaction)]);
            exit;
        }

        $content = trim((string)($payload['content'] ?? ''));
        if ($content === '') {
            http_response_code(400);
            echo json_encode(['error' => 'Message content is required']);
            exit;
        }

        $sender = $portal->getCommunityUsername($studentId);
        if (!$sender) {
            $sender = 'Student';
        }

        $postId = $portal->createCommunityPost($studentId, $content, $sender);

        echo json_encode(['ok' => true, 'id' => $postId]);
        exit;
    }

    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
