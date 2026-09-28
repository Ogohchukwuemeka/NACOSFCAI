<?php
require_once dirname(__DIR__) . '/config/bootstrap.php';

$auth = new Auth(Database::getInstance());
$auth->requireLogin('admin');
verify_csrf();

$targetDir = APP_ROOT . '/assets/uploads/resources';
$result = uploadFile($_FILES['file'] ?? [], $targetDir);
header('Content-Type: application/json');
echo json_encode($result);
