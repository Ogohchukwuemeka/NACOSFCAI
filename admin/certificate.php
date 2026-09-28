<?php
require_once dirname(__DIR__) . '/templates/header.php';
$auth = new Auth(Database::getInstance());
$auth->requireLogin('admin');
header('Location: ' . APP_URL . '/admin/dashboard.php');
exit;
