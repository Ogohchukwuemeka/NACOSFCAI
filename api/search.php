<?php
require_once dirname(__DIR__) . '/config/bootstrap.php';

$portal = new Portal(Database::getInstance());
$query = $_GET['q'] ?? '';
$scope = $_GET['scope'] ?? 'admin';
if ($scope === 'admin') {
	$auth = new Auth(Database::getInstance());
	$auth->requireLogin('admin');
	$results = $portal->search($query, 'admin');
} elseif ($scope === 'student') {
	$auth = new Auth(Database::getInstance());
	$auth->requireLogin('student');
	$user = $auth->currentUser();
	$results = $portal->search($query, 'student', (string)($user['level'] ?? ''));
} else {
	http_response_code(400);
	$results = ['error' => 'Invalid search scope.'];
}
header('Content-Type: application/json');
echo json_encode($results);
