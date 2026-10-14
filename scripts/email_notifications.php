<?php
// Run: php scripts/email_notifications.php
// Cron-friendly script to send pre-election reminders, post-election results, and unsent certificates.

require_once __DIR__ . '/../config/bootstrap.php';
use Dompdf\Dompdf;
use Dompdf\Options;

$pdo = Database::getInstance()->getConnection();
$mailer = new Mailer(Database::getInstance());
$election = new Election(Database::getInstance());
$certificate = new Certificate(Database::getInstance(), $mailer);

$now = new DateTime('now', new DateTimeZone(date_default_timezone_get()));
$log = function($msg) use ($now) { echo "[" . (new DateTime())->format('Y-m-d H:i:s') . "] $msg\n"; };

// Helper: check if recipient already received an email with subject
$alreadySent = function(string $recipient, string $subject) use ($pdo) {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM email_logs WHERE recipient = :recipient AND subject = :subject AND status = "sent"');
    $stmt->execute(['recipient' => $recipient, 'subject' => $subject]);
    return (int)$stmt->fetchColumn() > 0;
};

// 1) Pre-election reminders: elections starting within next 24 hours
$log('Checking for upcoming elections to remind voters...');
try {
    $stmt = $pdo->prepare("SELECT * FROM elections WHERE start_time > NOW() AND start_time <= DATE_ADD(NOW(), INTERVAL 24 HOUR) AND status IN ('scheduled','active')");
    $stmt->execute();
    $upcoming = $stmt->fetchAll();
    foreach ($upcoming as $ele) {
        $subject = 'Upcoming Election: ' . ($ele['title'] ?? 'Election');
        $message = "Dear Student,\n\nThe election '" . ($ele['title'] ?? '') . "' is scheduled to start on " . date('M d, Y H:i', strtotime($ele['start_time'])) . ". Please be ready to vote.\n\nBest regards,\nNACOS FCAI";

        // get recipients: all active students
        $recips = $pdo->query("SELECT u.email, u.full_name FROM users u WHERE u.role = 'student' AND u.status = 'active'")->fetchAll();
        foreach ($recips as $r) {
            $email = $r['email'] ?? null;
            $name = $r['full_name'] ?? '';
            if (!$email) continue;
            if ($alreadySent($email, $subject)) continue;
            $sent = $mailer->send($email, $subject, $message, $name, true);
            $log('Pre-election reminder to ' . $email . ': ' . ($sent ? 'sent' : 'failed'));
        }
    }
} catch (Throwable $e) {
    $log('Pre-election check failed: ' . $e->getMessage());
}

// 2) Post-election results: elections that ended in the last 2 hours
$log('Checking for recently concluded elections for post results distribution...');
try {
    $election->refreshAllElectionStatuses();
    $stmt = $pdo->prepare("SELECT * FROM elections WHERE status = 'closed' AND end_time <= NOW() AND end_time >= DATE_SUB(NOW(), INTERVAL 2 HOUR)");
    $stmt->execute();
    $recent = $stmt->fetchAll();
    foreach ($recent as $ele) {
        $subject = 'Election Results: ' . ($ele['title'] ?? 'Election');
        $body = "Dear Student,\n\nThe election '" . ($ele['title'] ?? '') . "' has concluded. Please find the results attached.\n\nBest regards,\nNACOS FCAI";

        // Render results HTML using Election class
        $resultsData = $election->getElectionResults($ele['id']);
        ob_start();
        ?>
        <html><head><meta charset="utf-8"><style>body{font-family:Arial,Helvetica,sans-serif}h1{color:#14532d}table{width:100%;border-collapse:collapse}th,td{border:1px solid #ddd;padding:8px}</style></head><body>
        <h1>Election Results: <?= htmlspecialchars($ele['title']) ?></h1>
        <p><?= htmlspecialchars($ele['description'] ?? '') ?></p>
        <?php foreach ($resultsData as $pos): ?>
            <h2><?= htmlspecialchars($pos['position_name'] ?? 'Position') ?></h2>
            <table>
                <thead><tr><th>Candidate</th><th>Votes</th><th>Percent</th></tr></thead>
                <tbody>
                <?php foreach ($pos['candidates'] as $c): ?>
                    <tr>
                        <td><?= htmlspecialchars($c['full_name'] ?? '—') ?></td>
                        <td><?= (int)($c['vote_count'] ?? 0) ?></td>
                        <td><?= round((float)($c['vote_percentage'] ?? 0),1) ?>%</td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endforeach; ?>
        </body></html>
        <?php
        $html = ob_get_clean();

        // Generate PDF
        $options = new Options();
        $options->set('defaultFont', 'Helvetica');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $uploadDir = __DIR__ . '/../assets/uploads/results';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        $filename = 'results-' . $ele['id'] . '-' . time() . '.pdf';
        $filePath = $uploadDir . '/' . $filename;
        file_put_contents($filePath, $dompdf->output());
        $dbPath = 'assets/uploads/results/' . $filename;

        // Send to admins first
        $admins = $pdo->query("SELECT email, full_name FROM users WHERE role = 'admin' AND status = 'active'")->fetchAll();
        foreach ($admins as $a) {
            $email = $a['email'] ?? null;
            if (!$email) continue;
            if ($alreadySent($email, $subject)) continue;
            $sent = $mailer->send($email, $subject, $body, [$filePath], $a['full_name'] ?? '', true);
            $log('Post-results to admin ' . $email . ': ' . ($sent ? 'sent' : 'failed'));
        }

        // Send to students (optionally restrict to registered voters) — here we send to all active students
        $students = $pdo->query("SELECT email, full_name FROM users WHERE role = 'student' AND status = 'active'")->fetchAll();
        foreach ($students as $s) {
            $email = $s['email'] ?? null;
            if (!$email) continue;
            if ($alreadySent($email, $subject)) continue;
            $sent = $mailer->send($email, $subject, $body, [$filePath], $s['full_name'] ?? '', true);
            $log('Post-results to student ' . $email . ': ' . ($sent ? 'sent' : 'failed'));
        }
    }
} catch (Throwable $e) {
    $log('Post-election handling failed: ' . $e->getMessage());
}

// 3) Unsent certificates: send certificates with pdf_path and status != 'sent'
$log('Checking for generated certificates to send...');
try {
    $stmt = $pdo->prepare("SELECT id FROM certificates WHERE status IN ('generated','pending') AND pdf_path IS NOT NULL");
    $stmt->execute();
    $certs = $stmt->fetchAll();
    if (!empty($certs)) {
        $ids = array_map(function($r){ return (int)$r['id']; }, $certs);
        $results = $certificate->sendBatchCertificateEmails($ids);
        foreach ($results as $cid => $res) {
            $log('Certificate ' . $cid . ' email: ' . ($res ? 'sent' : 'failed'));
        }
    } else {
        $log('No generated certificates pending send.');
    }
} catch (Throwable $e) {
    $log('Certificate send failed: ' . $e->getMessage());
}

$log('Notification run complete.');

