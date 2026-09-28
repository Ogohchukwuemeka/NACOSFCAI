<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class Mailer
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function send(string $to, string $subject, string $message, $attachmentsOrName = [], string $name = '', bool $isHtml = false): bool
    {
        // Backwards-compatible handler: fourth arg may be $name (string) or $attachments (array)
        $attachments = [];
        if (is_array($attachmentsOrName)) {
            $attachments = $attachmentsOrName;
        } else {
            $name = (string)$attachmentsOrName;
        }

        $settings = (new Portal($this->db))->getSettings();
        if (!is_array($settings)) {
            $settings = [];
        }

        $smtpHost = $this->normalizeMailSetting(getenv('SMTP_HOST') ?: ($settings['smtp_host'] ?? ''));
        $smtpUsername = $this->normalizeMailSetting(getenv('SMTP_USERNAME') ?: ($settings['smtp_username'] ?? ''));
        $smtpPassword = $this->normalizeMailSetting(getenv('SMTP_PASSWORD') ?: ($settings['smtp_password'] ?? ''));
        $smtpEncryption = $this->normalizeMailSetting(getenv('SMTP_ENCRYPTION') ?: ($settings['smtp_encryption'] ?? 'tls'));
        $smtpPort = (int)(getenv('SMTP_PORT') ?: ($settings['smtp_port'] ?? 587));
        $fromAddress = $this->normalizeMailSetting(getenv('MAIL_FROM_ADDRESS') ?: $smtpUsername ?: ($settings['contact_email'] ?? 'nacosfcaishiagu@gmail.com'));
        $body = ($isHtml || $this->looksLikeHtml($message)) ? $message : $this->buildTemplate($message, $name);

        if (!empty($smtpHost) && !empty($smtpUsername) && !empty($smtpPassword) && class_exists(PHPMailer::class)) {
            $timeout = (int)(getenv('SMTP_TIMEOUT') ?: 30);
            $mail = new PHPMailer(true);
            try {
                $this->configureSmtp($mail, $smtpHost, $smtpPort, $smtpEncryption, $smtpUsername, $smtpPassword, $timeout);
                $this->prepareMessage($mail, $fromAddress, $to, $name, $subject, $body, $attachments);
                $mail->send();
                $this->logEmail($to, $subject, $message, 'sent');
                return true;
            } catch (Throwable $e) {
                $msg = sprintf("%s - PHPMailer SMTP send failed for %s using %s:%d (%s): %s\n", date('Y-m-d H:i:s'), $to, $smtpHost, $smtpPort, $smtpEncryption ?: 'tls', $e->getMessage());
                @file_put_contents(APP_ROOT . '/logs/email_errors.log', $msg, FILE_APPEND | LOCK_EX);
                error_log($e->getMessage());

                $alternateHost = $smtpHost;
                $alternatePort = $smtpPort;
                $alternateEncryption = $smtpEncryption;
                if ($smtpHost && $smtpPort === 587 && strtolower($smtpEncryption) === 'tls') {
                    $alternatePort = 465;
                    $alternateEncryption = 'ssl';
                }
                if ($alternatePort !== $smtpPort || strtolower($alternateEncryption) !== strtolower($smtpEncryption)) {
                    try {
                        $altMail = new PHPMailer(true);
                        $this->configureSmtp($altMail, $alternateHost, $alternatePort, $alternateEncryption, $smtpUsername, $smtpPassword, $timeout);
                        $this->prepareMessage($altMail, $fromAddress, $to, $name, $subject, $body, $attachments);
                        $altMail->send();
                        $this->logEmail($to, $subject, $message, 'sent');
                        return true;
                    } catch (Throwable $altError) {
                        $altMsg = sprintf("%s - PHPMailer alternate SMTP send failed for %s using %s:%d (%s): %s\n", date('Y-m-d H:i:s'), $to, $alternateHost, $alternatePort, $alternateEncryption, $altError->getMessage());
                        @file_put_contents(APP_ROOT . '/logs/email_errors.log', $altMsg, FILE_APPEND | LOCK_EX);
                        error_log($altError->getMessage());
                    }
                }

                try {
                    $fallbackMail = new PHPMailer(true);
                    $fallbackMail->isMail();
                    $this->prepareMessage($fallbackMail, $fromAddress ?: 'nacosfcaishiagu@gmail.com', $to, $name, $subject, $body, $attachments);
                    $fallbackMail->send();
                    $this->logEmail($to, $subject, $message, 'sent');
                    return true;
                } catch (Throwable $fallbackError) {
                    $fallbackMsg = sprintf("%s - PHPMailer mail() fallback failed for %s: %s\n", date('Y-m-d H:i:s'), $to, $fallbackError->getMessage());
                    @file_put_contents(APP_ROOT . '/logs/email_errors.log', $fallbackMsg, FILE_APPEND | LOCK_EX);
                    error_log($fallbackError->getMessage());
                }
            }
        }

        if (class_exists(PHPMailer::class)) {
            $mail = new PHPMailer(true);
            try {
                $mail->isMail();
                $mail->setFrom($fromAddress ?: 'nacosfcaishiagu@gmail.com', 'NACOS FCAI');
                $mail->addAddress($to, $name);
                $mail->isHTML(true);
                $mail->Subject = $subject;
                $mail->Body = $body;
                $mail->AltBody = strip_tags($body);
                // Attachments not supported for PHP mail() transport in this helper
                $mail->send();
                $this->logEmail($to, $subject, $message, 'sent');
                return true;
            } catch (Throwable $e) {
                $msg = sprintf("%s - PHPMailer mail() transport failed for %s: %s\n", date('Y-m-d H:i:s'), $to, $e->getMessage());
                @file_put_contents(APP_ROOT . '/logs/email_errors.log', $msg, FILE_APPEND | LOCK_EX);
                error_log($e->getMessage());
            }
        }

        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=UTF-8',
            'From: ' . ($fromAddress ?: 'nacosfcaishiagu@gmail.com'),
            'Reply-To: ' . ($fromAddress ?: 'nacosfcaishiagu@gmail.com'),
        ];
        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $sent = @mail($to, $encodedSubject, $body, implode("\r\n", $headers));
        if ($sent) {
            $this->logEmail($to, $subject, $message, 'sent');
            return true;
        }

        $this->logEmail($to, $subject, $message, 'failed');
        return false;
    }

    private function normalizeMailSetting(string $value): string
    {
        $value = trim($value);
        if ($value === '' || stripos($value, 'example.com') !== false || stripos($value, 'example') !== false || $value === 'password' || $value === 'localhost' || $value === '127.0.0.1') {
            return '';
        }

        return $value;
    }

    private function configureSmtp(PHPMailer $mail, string $smtpHost, int $smtpPort, string $smtpEncryption, string $smtpUsername, string $smtpPassword, int $timeout): void
    {
        $smtpPort = $smtpPort ?: 587;
        $smtpEncryption = $smtpEncryption ?: 'tls';
        $mail->isSMTP();
        $mail->Host = $smtpHost;
        $mail->SMTPAuth = true;
        $mail->Username = $smtpUsername;
        $mail->Password = $smtpPassword;
        $mail->SMTPSecure = $smtpEncryption;
        $mail->Port = $smtpPort;
        $mail->Timeout = $timeout;
        $mail->SMTPDebug = (int)(getenv('SMTP_DEBUG') ?: 0);
        $mail->SMTPKeepAlive = false;
        $mail->SMTPAutoTLS = false;
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
            ],
        ];
        $logFile = APP_ROOT . '/logs/email_errors.log';
        $mail->Debugoutput = function ($str, $level) use ($logFile) {
            $entry = sprintf("%s [SMTP-debug-%d] %s\n", date('Y-m-d H:i:s'), $level, $str);
            @file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
        };
    }

    private function prepareMessage(PHPMailer $mail, string $fromAddress, string $to, string $name, string $subject, string $body, array $attachments): void
    {
        $mail->setFrom($fromAddress ?: 'nacosfcaishiagu@gmail.com', 'NACOS FCAI');
        $mail->addAddress($to, $name);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->AltBody = strip_tags($body);
        if (!empty($attachments) && is_array($attachments)) {
            foreach ($attachments as $file) {
                if (is_file($file)) {
                    $mail->addAttachment($file);
                }
            }
        }
    }

    private function looksLikeHtml(string $message): bool
    {
        return preg_match('/<\s*(html|body|div|p|a|strong|img|h[1-6]|table|span|br)\b/i', $message) === 1;
    }

    public function sendRegistrationEmail(array $student): bool
    {
        $to = $student['email'] ?? '';
        $subject = 'NACOS Registration Confirmation';
        $body = $this->buildRegistrationTemplate($student);
        $name = $student['full_name'] ?? 'Student';
        $sent = $this->send($to, $subject, $body, [], $name, true);
        if (!$sent) {
            $this->logEmail($to, $subject, 'Registration confirmation email could not be delivered.', 'failed');
        }
        return $sent;
    }

    public function sendPasswordResetEmail(string $to, string $name, string $resetUrl): bool
    {
        $subject = 'NACOS Password Reset Request';
        $body = $this->buildPasswordResetTemplate($name, $resetUrl);
        $sent = $this->send($to, $subject, $body, [], $name, true);
        if (!$sent) {
            $this->logEmail($to, $subject, 'Password reset email could not be delivered.', 'failed');
        }
        return $sent;
    }

    public function sendPaymentVerifiedEmail(array $student, array $payment): bool
    {
        $to = $student['email'] ?? '';
        $name = $student['full_name'] ?? 'Student';
        $paymentType = htmlspecialchars($payment['payment_type'] ?? 'NACOS payment');
        $amount = htmlspecialchars(number_format((float)($payment['amount'] ?? 0), 2));
        $session = htmlspecialchars($payment['session'] ?? 'N/A');
        $reference = htmlspecialchars($payment['payment_reference'] ?? 'N/A');

        $content = sprintf('<div style="margin:0 0 16px;color:#334155;">Your payment for <strong>%s</strong> has been successfully verified. Thank you for completing your dues payment. You may now access the corresponding features in your student dashboard.</div>
            <div style="background:#ecfdf5;border:1px solid #bbf7d0;padding:18px;border-radius:16px;">
                <p style="margin:0 0 10px;"><strong>Payment Reference:</strong> %s</p>
                <p style="margin:0 0 10px;"><strong>Amount:</strong> ₦%s</p>
                <p style="margin:0 0 10px;"><strong>Academic Session:</strong> %s</p>
            </div>
            <p style="margin:20px 0 0;color:#334155;">If you have questions, please contact NACOS FCAI support.</p>', $paymentType, $reference, $amount, $session);

        $body = $this->buildTemplate($content, $name, 'Payment Verified');
        $sent = $this->send($to, 'Payment Verified', $body, [], $name, true);
        if (!$sent) {
            $this->logEmail($to, 'Payment Verified', 'Payment verification email could not be delivered.', 'failed');
        }
        return $sent;
    }

    public function sendPaymentRejectedEmail(array $student, string $reason, array $payment = []): bool
    {
        $to = $student['email'] ?? '';
        $name = $student['full_name'] ?? 'Student';
        $paymentType = htmlspecialchars($payment['payment_type'] ?? 'NACOS payment');
        $reference = htmlspecialchars($payment['payment_reference'] ?? 'N/A');

        $content = sprintf('<div style="margin:0 0 16px;color:#334155;">Unfortunately, your payment for <strong>%s</strong> was rejected. Please review the reason below, update your payment proof, and submit again.</div>
            <div style="background:#fff1f2;border:1px solid #fecdd3;padding:18px;border-radius:16px;">
                <p style="margin:0 0 10px;"><strong>Reason:</strong> %s</p>
                <p style="margin:0 0 10px;"><strong>Payment Reference:</strong> %s</p>
            </div>
            <p style="margin:20px 0 0;color:#334155;">Once you have corrected the submission, upload a new receipt through your student dashboard.</p>', $paymentType, htmlspecialchars($reason), $reference);

        $body = $this->buildTemplate($content, $name, 'Payment Rejected');
        $sent = $this->send($to, 'Payment Rejected', $body, [], $name, true);
        if (!$sent) {
            $this->logEmail($to, 'Payment Rejected', 'Payment rejection email could not be delivered.', 'failed');
        }
        return $sent;
    }

    public function formatRegistrationEmail(array $student): string
    {
        return $this->buildRegistrationTemplate($student);
    }

    private function buildTemplate(string $content, string $name, string $subject = 'NACOS FCAI Notification'): string
    {
        $settings = (new Portal($this->db))->getSettings();
        $baseUrl = defined('APP_URL') ? APP_URL : '';
        $logoUrl = $baseUrl . '/assets/images/logo/nacos-logo.jpeg';
        $contactEmail = htmlspecialchars($settings['contact_email'] ?? 'nacosfcaishiagu@gmail.com');
        $contactPhone = htmlspecialchars($settings['contact_phone'] ?? '+234 903 310 3319');
        $escapedName = htmlspecialchars($name ?: 'Student');

        return sprintf('<div style="font-family:Arial,sans-serif;max-width:720px;margin:auto;padding:20px;background:#f4f7f6;border:1px solid #d1fae9;border-radius:22px;">
            <div style="text-align:center;margin-bottom:22px;">
                <img src="%s" alt="nacos-Logo" style="max-width:120px;height:auto;display:block;margin:0 auto 16px;" />
                <h1 style="margin:0;font-size:26px;color:#14532d;">%s</h1>
            </div>
            <div style="background:#ffffff;border:1px solid #e2f3e8;border-radius:18px;padding:24px;color:#334155;line-height:1.8;">
                <p style="margin:0 0 16px;">Hello %s,</p>
                %s
                <p style="margin:24px 0 0;color:#475569;font-size:14px;">If you need support, contact NACOS FCAI at %s or call %s.</p>
            </div>
            <div style="text-align:center;margin-top:24px;color:#64748b;font-size:13px;line-height:1.7;">
                <p style="margin:0;"><strong>NACOS FCAI</strong> | %s | %s</p>
            </div>
        </div>', $logoUrl, htmlspecialchars($subject), $escapedName, $content, $contactEmail, $contactPhone, $contactEmail, $contactPhone);
    }

    private function buildRegistrationTemplate(array $student): string
    {
        $baseUrl = defined('APP_URL') ? APP_URL : '';
        $logoUrl = $baseUrl . '/assets/images/logo/nacos-logo.jpeg';
        $name = htmlspecialchars($student['full_name'] ?? 'Student');
        $matric = htmlspecialchars($student['matric_number'] ?? 'Pending');
        $department = htmlspecialchars($student['department'] ?? 'Not provided');
        $course = htmlspecialchars($student['course_of_study'] ?? 'Not provided');
        $level = htmlspecialchars($student['level'] ?? 'Not provided');
        $registeredAt = htmlspecialchars($student['created_at'] ?? date('Y-m-d H:i:s'));
        $loginUrl = $baseUrl . '/auth/login.php';

        $content = sprintf('<div style="background:#ffffff;border-radius:16px;padding:22px;border:1px solid #dbeafe;">
                <p style="margin:0 0 16px;color:#334155;">Thank you for joining NACOS FCAIshiagu. Your student account is now active and you can sign in to view your dashboard, announcements, and resources.</p>
                <div style="margin:18px 0;padding:18px;border-radius:16px;background:#f8fafc;border:1px solid #d1e7dd;">
                    <p style="margin:0 0 10px;color:#14532d;font-weight:bold;">Registration details</p>
                    <p style="margin:4px 0;color:#334155;"><strong>Matric Number:</strong> %s</p>
                    <p style="margin:4px 0;color:#334155;"><strong>Department:</strong> %s</p>
                    <p style="margin:4px 0;color:#334155;"><strong>Course of Study:</strong> %s</p>
                    <p style="margin:4px 0;color:#334155;"><strong>Level:</strong> %s</p>
                    <p style="margin:4px 0;color:#334155;"><strong>Registered On:</strong> %s</p>
                </div>
                <p style="margin:0 0 20px;color:#166534;">Your Membership Number will be generated automatically after your first NACOS dues payment is verified by the administrator.</p>
                <p style="margin:0;"><a href="%s" style="display:inline-block;padding:12px 22px;background:#14532d;color:#ffffff;text-decoration:none;border-radius:999px;font-weight:bold;">Login to your portal</a></p>
            </div>', $matric, $department, $course, $level, $registeredAt, $loginUrl);

        return $this->buildTemplate($content, $name, 'Registration Confirmation');
    }

    private function buildPasswordResetTemplate(string $name, string $resetUrl): string
    {
        $logoUrl = defined('APP_URL') ? APP_URL . '/assets/images/logo/nacos-logo.jpeg' : '';
        $escapedName = htmlspecialchars($name);
        $escapedUrl = htmlspecialchars($resetUrl);

        return sprintf('<div style="font-family:Arial,sans-serif;max-width:720px;margin:auto;padding:24px;border:1px solid #dbeafe;border-radius:18px;background:linear-gradient(135deg,#fefefe 0%%,#f0fdf4 100%%);">
            <div style="text-align:center;margin-bottom:20px;">
                <img src="%s" alt="nacos-Logo" style="max-width:120px;height:auto;border-radius:16px;" />
                <h2 style="color:#14532d;margin:12px 0 6px;">Password Reset Request</h2>
                <p style="margin:0;color:#475569;">Reset your NACOS portal password securely.</p>
            </div>
            <div style="background:#ffffff;border:1px solid #e2e8f0;border-radius:14px;padding:20px;">
                <p style="margin:0 0 12px;color:#0f172a;">Hello %s,</p>
                <p style="margin:0 0 12px;color:#334155;">We received a request to reset your NACOS portal password. Click the button below to set a new password. This link will expire in one hour.</p>
                <div style="margin:20px 0;text-align:center;">
                    <a href="%s" style="display:inline-block;padding:14px 24px;background:#14532d;color:#ffffff;text-decoration:none;border-radius:999px;font-weight:bold;">Reset Password</a>
                </div>
                <p style="margin:0;color:#475569;">If you did not request a password reset, you can safely ignore this message.</p>
            </div>
            <div style="margin-top:20px;padding-top:16px;border-top:1px solid #e2e8f0;color:#64748b;font-size:12px;line-height:1.6;">
                <p style="margin:0 0 6px;">NACOS FCAIshiagu<br/>Email: nacosfcaishiagu@gmail.com<br/>Phone: +234 903310 3319</p>
                <p style="margin:0;">This is an automated message. Please do not reply directly.</p>
            </div>
        </div>', $logoUrl, $escapedName, $escapedUrl);
    }

    private function logEmail(string $recipient, string $subject, string $message, string $status): void
    {
        try {
            $this->ensureEmailLogsTableExists();
            $stmt = $this->db->getConnection()->prepare('INSERT INTO email_logs (recipient, subject, message, status, created_at) VALUES (:recipient, :subject, :message, :status, NOW())');
            $stmt->execute([
                'recipient' => $recipient,
                'subject' => $subject,
                'message' => $message,
                'status' => $status,
            ]);
        } catch (Throwable $e) {
            error_log('Email log failed: ' . $e->getMessage());
        }
    }

    private function ensureEmailLogsTableExists(): void
    {
        try {
            $pdo = $this->db->getConnection();
            $check = $pdo->query("SHOW TABLES LIKE 'email_logs'")->fetchColumn();
            if (!$check) {
                $pdo->exec(<<<'SQL'
CREATE TABLE email_logs (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  recipient VARCHAR(255) NOT NULL,
  subject VARCHAR(255) NOT NULL,
  message TEXT NOT NULL,
  status ENUM('pending','sent','failed') NOT NULL DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
SQL
                );
            }
        } catch (Throwable $e) {
            error_log('Email log table check failed: ' . $e->getMessage());
        }
    }
}
