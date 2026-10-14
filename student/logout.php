<?php
require_once dirname(__DIR__) . '/config/bootstrap.php';
$auth = new Auth(Database::getInstance());
$auth->logout();
redirect(($_GET['timeout'] ?? '') === '1' ? 'auth/login.php?timeout=1' : 'index.php');
