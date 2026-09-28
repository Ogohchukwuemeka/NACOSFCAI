<?php
require_once dirname(__DIR__) . '/config/bootstrap.php';
$auth = new Auth(Database::getInstance());
$auth->logout();
redirect('index.php');
