<?php
require_once dirname(__DIR__) . '/config/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
}

$auth = new Auth(Database::getInstance());
$auth->requireLogin('admin');

try {
    verify_csrf();
    $portal = new Portal(Database::getInstance());
    if (($_POST['action'] ?? '') === 'create_announcement') {
        $portal->createAnnouncement([
            'title' => $_POST['title'] ?? '',
            'body' => $_POST['body'] ?? '',
            'category' => $_POST['category'] ?? '',
        ]);
        $_SESSION['flash'] = 'Announcement published successfully.';
    }
} catch (Exception $e) {
    $_SESSION['flash'] = $e->getMessage();
}

redirect('admin/dashboard.php');
