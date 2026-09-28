<?php
require_once dirname(__DIR__) . '/config/bootstrap.php';

$auth = new Auth(Database::getInstance());
$auth->requireLogin('student');
$user = $auth->currentUser();
$resourceId = (int)($_GET['id'] ?? 0);
$resource = $resourceId > 0 ? (new Portal(Database::getInstance()))->getStudentResource($resourceId, (string)($user['level'] ?? '')) : null;

if (!$resource || empty($resource['file_path'])) {
    http_response_code(404);
    exit('Resource not found or unavailable for your level.');
}

$baseDirectory = realpath(APP_ROOT . '/assets/uploads/resources');
$storedPath = ltrim((string)$resource['file_path'], '/');
$storedPath = str_replace('\\', '/', $storedPath);
$storedPath = preg_replace('#^uploads/resources/#', '', $storedPath);
$filePath = realpath($baseDirectory . '/' . $storedPath);
if (!$baseDirectory || !$filePath || !str_starts_with($filePath, $baseDirectory . DIRECTORY_SEPARATOR) || !is_file($filePath)) {
    http_response_code(404);
    exit('Resource file not found.');
}

$download = isset($_GET['download']);
$mimeType = mime_content_type($filePath) ?: 'application/octet-stream';
header('Content-Type: ' . $mimeType);
header('Content-Length: ' . (string)filesize($filePath));
header('Content-Disposition: ' . ($download ? 'attachment' : 'inline') . '; filename="' . basename($filePath) . '"');
readfile($filePath);