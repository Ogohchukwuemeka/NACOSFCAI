<?php
require_once dirname(__DIR__) . '/templates/header.php';

$auth = new Auth(Database::getInstance());
$auth->requireLogin('admin');

$certificateId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($certificateId <= 0) {
    header('Location: ' . APP_URL . '/admin/certificates.php');
    exit;
}

$certificate = new Certificate(Database::getInstance());
$cert = $certificate->getCertificate($certificateId);
if (!$cert || empty($cert['pdf_path'])) {
    header('Location: ' . APP_URL . '/admin/certificates.php');
    exit;
}

$filepath = APP_ROOT . '/' . ltrim($cert['pdf_path'], '/');
if (!file_exists($filepath) || !is_readable($filepath)) {
    header('Location: ' . APP_URL . '/admin/certificates.php');
    exit;
}

$filename = 'nacos-certificate-' . preg_replace('/[^a-zA-Z0-9_-]/', '', strtolower($cert['full_name'] ?? 'student')) . '.pdf';

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($filepath));
readfile($filepath);
exit;
