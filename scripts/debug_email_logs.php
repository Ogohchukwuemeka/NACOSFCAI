<?php
require_once dirname(__DIR__) . '/config/bootstrap.php';
$pdo = Database::getInstance()->getConnection();
$rows = $pdo->query("SELECT id, recipient, subject, status, created_at, LEFT(message, 300) AS message_preview FROM email_logs ORDER BY created_at DESC LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($rows, JSON_PRETTY_PRINT);
