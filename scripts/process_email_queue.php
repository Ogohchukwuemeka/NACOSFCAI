<?php
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/classes/Portal.php';
require_once dirname(__DIR__) . '/classes/Mailer.php';

$pdo = Database::getInstance()->getConnection();

// fetch pending items (only those pending)
$queue = $pdo->query("SELECT * FROM email_logs WHERE status = 'pending' ORDER BY created_at ASC LIMIT 20")->fetchAll();
if (!$queue) {
    echo "No pending emails to process." . PHP_EOL;
    exit(0);
}

$mailer = new Mailer(Database::getInstance());
$updated = 0;
$logFile = __DIR__ . '/../logs/email_queue.log';
foreach ($queue as $item) {
    try {
        $payload = json_decode($item['message'], true);
        if (!is_array($payload)) {
            $payload = ['message' => $item['message']];
        }
        $subject = $item['subject'];
        $recipient = $item['recipient'];

        if (isset($payload['full_name']) && isset($payload['email'])) {
            $message = $mailer->formatRegistrationEmail($payload);
            $sent = $mailer->send($recipient, $subject, $message, $payload['full_name'], true);
        } else {
            $message = $payload['message'] ?? $item['message'];
            $sent = $mailer->send($recipient, $subject, $message, $payload['full_name'] ?? 'Student');
        }

        $status = $sent ? 'sent' : 'failed';
        $stmt = $pdo->prepare('UPDATE email_logs SET status = :status WHERE id = :id');
        $stmt->execute(['status' => $status, 'id' => $item['id']]);
        $msg = sprintf("%s - Email %d %s to %s\n", date('Y-m-d H:i:s'), $item['id'], $status, $recipient);
        file_put_contents($logFile, $msg, FILE_APPEND | LOCK_EX);
        echo $msg;
        $updated++;
    } catch (Throwable $e) {
        $err = sprintf("%s - Error processing email id=%d: %s\n", date('Y-m-d H:i:s'), $item['id'] ?? 0, $e->getMessage());
        file_put_contents($logFile, $err, FILE_APPEND | LOCK_EX);
        // mark as failed so the queue doesn't infinite-loop; manual inspection can be done from logs
        try {
            $stmt = $pdo->prepare('UPDATE email_logs SET status = :status WHERE id = :id');
            $stmt->execute(['status' => 'failed', 'id' => $item['id'] ?? 0]);
        } catch (Throwable $e2) {
            file_put_contents($logFile, date('Y-m-d H:i:s') . " - Failed to mark email as failed: " . $e2->getMessage() . "\n", FILE_APPEND | LOCK_EX);
        }
    }
}

echo sprintf("Processed %d email(s).\n", $updated);
