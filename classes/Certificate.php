<?php

class Certificate
{
    private Database $db;
    private Mailer $mailer;
    private ?bool $legacyCertificateSchema = null;

    public function __construct(Database $database, ?Mailer $mailer = null)
    {
        $this->db = $database;
        $this->mailer = $mailer ?? new Mailer($database);
    }

    public function usesLegacyCertificatesSchema(): bool
    {
        if ($this->legacyCertificateSchema !== null) {
            return $this->legacyCertificateSchema;
        }

        $stmt = $this->db->getConnection()->query("SHOW COLUMNS FROM certificates LIKE 'event_id'");
        $this->legacyCertificateSchema = !$stmt->fetch();
        return $this->legacyCertificateSchema;
    }

    // ===== CERTIFICATE MANAGEMENT =====

    public function registerEventParticipant(array $data): int
    {
        $pdo = $this->db->getConnection();
        $stmt = $pdo->prepare('INSERT INTO event_participants (event_id, student_id, full_name, email, phone_number, department, level, payment_status) VALUES (:event_id, :student_id, :full_name, :email, :phone_number, :department, :level, :payment_status)');
        $stmt->execute([
            'event_id' => $data['event_id'] ?? 0,
            'student_id' => $data['student_id'] ?? 0,
            'full_name' => $data['full_name'] ?? '',
            'email' => $data['email'] ?? '',
            'phone_number' => $data['phone_number'] ?? '',
            'department' => $data['department'] ?? '',
            'level' => $data['level'] ?? '',
            'payment_status' => $data['payment_status'] ?? 'pending',
        ]);
        return (int)$pdo->lastInsertId();
    }

    public function getEventParticipant(int $participantId): ?array
    {
        $stmt = $this->db->getConnection()->prepare('SELECT * FROM event_participants WHERE id = :id');
        $stmt->execute(['id' => $participantId]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function listEventParticipants(int $eventId, ?string $paymentStatus = null): array
    {
        $sql = 'SELECT * FROM event_participants WHERE event_id = :event_id';
        $params = ['event_id' => $eventId];
        if ($paymentStatus !== null) {
            $sql .= ' AND payment_status = :payment_status';
            $params['payment_status'] = $paymentStatus;
        }
        $sql .= ' ORDER BY registered_at DESC';
        $stmt = $this->db->getConnection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function updateParticipantStatus(int $participantId, string $paymentStatus): bool
    {
        $stmt = $this->db->getConnection()->prepare('UPDATE event_participants SET payment_status = :payment_status WHERE id = :id');
        return $stmt->execute(['payment_status' => $paymentStatus, 'id' => $participantId]);
    }

    // ===== CERTIFICATE GENERATION =====

    public function generateCertificate(int $eventId, int $studentId, int $participantId): int
    {
        $pdo = $this->db->getConnection();
        $certificateNumber = 'CERT-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));
        
        $stmt = $pdo->prepare('INSERT INTO certificates (event_id, student_id, event_participant_id, certificate_number, status) VALUES (:event_id, :student_id, :event_participant_id, :certificate_number, :status)');
        $stmt->execute([
            'event_id' => $eventId,
            'student_id' => $studentId,
            'event_participant_id' => $participantId,
            'certificate_number' => $certificateNumber,
            'status' => 'pending',
        ]);
        return (int)$pdo->lastInsertId();
    }

    public function generateBatchCertificates(int $eventId): int
    {
        $pdo = $this->db->getConnection();
        $event = $pdo->query("SELECT * FROM events WHERE id = $eventId")->fetch();
        $participants = $this->listEventParticipants($eventId, 'verified');
        
        $count = 0;
        foreach ($participants as $participant) {
            // Check if certificate already exists
            $existing = $pdo->query("SELECT id FROM certificates WHERE event_id = $eventId AND student_id = {$participant['student_id']}")->fetch();
            if ($existing) {
                continue; // Skip if already created
            }
            
            $certificateId = $this->generateCertificate($eventId, (int)$participant['student_id'], (int)$participant['id']);
            
            // Generate PDF for this certificate
            if ($this->generateCertificatePdf($certificateId)) {
                $count++;
            }
        }
        return $count;
    }

    public function generateCertificatePdf(int $certificateId): bool
    {
        try {
            $html = $this->generateCertificateHtml($certificateId);
            if (!$html) {
                return false;
            }

            // Create upload directory
            $uploadDir = dirname(__DIR__) . '/assets/uploads/certificates';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            // Generate unique filename
            $filename = 'cert-' . str_pad($certificateId, 6, '0', STR_PAD_LEFT) . '-' . uniqid() . '.pdf';
            $filepath = $uploadDir . '/' . $filename;
            $dbPath = 'assets/uploads/certificates/' . $filename;

            // Initialize DomPDF
            $options = new \Dompdf\Options();
            $options->set('defaultFont', 'Garamond');
            $options->set('isHtml5ParserEnabled', true);
            $options->set('isPhpEnabled', false);

            $dompdf = new \Dompdf\Dompdf($options);
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'landscape');
            $dompdf->render();

            // Save PDF
            $output = $dompdf->output();
            file_put_contents($filepath, $output);

            if (!file_exists($filepath)) {
                return false;
            }

            // Update certificate with PDF path
            return $this->updateCertificatePath($certificateId, $dbPath);
        } catch (Exception $e) {
            error_log("Certificate PDF generation error: " . $e->getMessage());
            return false;
        }
    }

    public function getCertificate(int $certificateId): ?array
    {
        if ($this->usesLegacyCertificatesSchema()) {
            $stmt = $this->db->getConnection()->prepare(
                'SELECT c.*, c.certificate_path AS pdf_path, cr.certificate_number, cr.programme AS event_title, cr.department, cr.session, cr.status AS request_status, u.full_name, u.email, u.matric_number
                 FROM certificates c
                 LEFT JOIN certificate_requests cr ON cr.id = c.request_id
                 LEFT JOIN users u ON u.id = cr.user_id
                 WHERE c.id = :id'
            );
            $stmt->execute(['id' => $certificateId]);
            $result = $stmt->fetch();
            if (!$result) {
                return null;
            }
            $result['event_date'] = null;
            $result['venue'] = null;
            $result['status'] = $result['request_status'] ?? 'issued';
            unset($result['request_status']);
            return $result;
        }

        $stmt = $this->db->getConnection()->prepare('SELECT c.*, e.title AS event_title, e.event_date, e.venue, u.full_name, u.email FROM certificates c LEFT JOIN events e ON e.id = c.event_id LEFT JOIN users u ON u.id = c.student_id WHERE c.id = :id');
        $stmt->execute(['id' => $certificateId]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function listCertificates(int $eventId, ?string $status = null): array
    {
        $sql = 'SELECT c.*, e.title AS event_title, u.full_name, u.email FROM certificates c LEFT JOIN events e ON e.id = c.event_id LEFT JOIN users u ON u.id = c.student_id WHERE c.event_id = :event_id';
        $params = ['event_id' => $eventId];
        if ($status !== null) {
            $sql .= ' AND c.status = :status';
            $params['status'] = $status;
        }
        $sql .= ' ORDER BY c.created_at DESC';
        $stmt = $this->db->getConnection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function listAllCertificates(): array
    {
        if ($this->usesLegacyCertificatesSchema()) {
            $stmt = $this->db->getConnection()->prepare(
                'SELECT c.id, c.request_id, c.certificate_path AS pdf_path, c.issued_at AS created_at, cr.certificate_number, cr.programme AS event_title, cr.department, cr.session, cr.status AS request_status, u.full_name, u.email, COALESCE(u.matric_number, s.registration_number, "N/A") AS matric_number, "issued" AS status
                 FROM certificates c
                 LEFT JOIN certificate_requests cr ON cr.id = c.request_id
                 LEFT JOIN users u ON u.id = cr.user_id
                 LEFT JOIN students s ON s.user_id = u.id
                 ORDER BY c.issued_at DESC'
            );
            $stmt->execute();
            return $stmt->fetchAll();
        }

        $stmt = $this->db->getConnection()->prepare(
            'SELECT c.*, e.title AS event_title, u.full_name, u.email, COALESCE(u.matric_number, s.registration_number, "N/A") AS matric_number
             FROM certificates c
             LEFT JOIN events e ON e.id = c.event_id
             LEFT JOIN students s ON s.user_id = c.student_id
             LEFT JOIN users u ON u.id = c.student_id
             ORDER BY c.created_at DESC'
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function deleteCertificate(int $certificateId): bool
    {
        $pdo = $this->db->getConnection();
        $certificate = $this->getCertificate($certificateId);
        if (!$certificate) {
            return false;
        }

        // If a PDF exists, remove it from disk.
        if (!empty($certificate['pdf_path'])) {
            $pdfPath = $certificate['pdf_path'];
            if (file_exists($pdfPath)) {
                @unlink($pdfPath);
            }
        }

        $stmt = $pdo->prepare('DELETE FROM certificates WHERE id = :id');
        return $stmt->execute(['id' => $certificateId]);
    }

    public function updateCertificateStatus(int $certificateId, string $status): bool
    {
        $column = match($status) {
            'sent' => 'sent_at',
            'viewed' => 'viewed_at',
            default => null,
        };
        
        $sql = 'UPDATE certificates SET status = :status';
        $params = ['status' => $status, 'id' => $certificateId];
        
        if ($column !== null) {
            $sql .= ", $column = NOW()";
        }
        
        $sql .= ' WHERE id = :id';
        $stmt = $this->db->getConnection()->prepare($sql);
        return $stmt->execute($params);
    }

    public function updateCertificatePath(int $certificateId, string $pdfPath): bool
    {
        $stmt = $this->db->getConnection()->prepare('UPDATE certificates SET pdf_path = :pdf_path, status = :status, generated_at = NOW() WHERE id = :id');
        return $stmt->execute(['pdf_path' => $pdfPath, 'status' => 'generated', 'id' => $certificateId]);
    }

    // ===== CERTIFICATE CONTENT GENERATION =====

    public function generateCertificateHtml(int $certificateId): string
    {
        $cert = $this->getCertificate($certificateId);
        if (!$cert) {
            return '';
        }

        $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { margin: 0; padding: 40px; font-family: 'Garamond', serif; background: white; }
        .certificate { border: 3px solid #14532d; padding: 60px; text-align: center; position: relative; background: linear-gradient(to right, rgba(20, 83, 45, 0.05), rgba(22, 163, 74, 0.05)); }
        .header { margin-bottom: 40px; }
        .title { font-size: 36px; color: #14532d; font-weight: bold; margin: 20px 0; }
        .subtitle { font-size: 24px; color: #16a34a; margin: 15px 0; }
        .content { font-size: 14px; line-height: 1.8; color: #333; margin: 30px 0; }
        .awarded { font-size: 16px; margin: 20px 0; font-weight: bold; }
        .student-name { font-size: 24px; color: #14532d; font-weight: bold; margin: 10px 0; }
        .details { font-size: 12px; color: #666; margin: 20px 0; line-height: 1.6; }
        .signature-line { border-top: 2px solid #14532d; display: inline-block; width: 200px; margin-top: 40px; margin-bottom: 5px; }
        .signature { margin-top: 50px; display: flex; justify-content: space-around; }
        .sig-block { text-align: center; }
        .number { font-size: 12px; color: #999; margin-top: 20px; }
        .border-decoration { border: 2px dashed #16a34a; padding: 50px 40px; margin: 20px 0; }
    </style>
</head>
<body>
    <div class="certificate">
        <div class="header">
            <div class="title">Certificate of Participation</div>
            <div class="subtitle">NACOS FCAIshiagu</div>
        </div>

        <div class="border-decoration">
            <div class="content">
                This is to certify that
            </div>
            <div class="student-name">
                {$cert['full_name']}
            </div>
            <div class="awarded">
                has successfully participated in
            </div>
            <div class="subtitle">
                {$cert['event_title']}
            </div>
            <div class="details">
                <p>Event Date: {$cert['event_date']}</p>
                <p>Venue: {$cert['venue']}</p>
                <p>Certificate Number: {$cert['certificate_number']}</p>
            </div>
        </div>

        <div class="content">
            In recognition of active participation and commitment to academic excellence and community engagement.
        </div>

        <div class="signature">
            <div class="sig-block">
                <div class="signature-line"></div>
                <div>President, NACOS</div>
            </div>
            <div class="sig-block">
                <div class="signature-line"></div>
                <div>Authorized Representative</div>
            </div>
        </div>

        <div class="number">
            Certificate #{$cert['certificate_number']}
        </div>
    </div>
</body>
</html>
HTML;

        return $html;
    }

    // ===== EMAIL NOTIFICATIONS =====

    public function sendCertificateEmail(int $certificateId): bool
    {
        $cert = $this->getCertificate($certificateId);
        if (!$cert || !$cert['email']) {
            return false;
        }

        $subject = 'Your Certificate - ' . $cert['event_title'];
        $body = <<<BODY
Dear {$cert['full_name']},

We are pleased to provide you with your certificate for participating in the {$cert['event_title']} event held on {$cert['event_date']} at {$cert['venue']}.

Your certificate has been generated and is attached to this email. Please keep it for your records.

Certificate Number: {$cert['certificate_number']}

Best regards,
NACOS FCAIshiagu Administration
BODY;

        $attachments = [];
        if (!empty($cert['pdf_path'])) {
            $pdfPath = $cert['pdf_path'];
            if (!is_file($pdfPath)) {
                $pdfPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ltrim($pdfPath, '/\\'));
            }
            if (is_file($pdfPath)) {
                $attachments[] = $pdfPath;
            }
        }

        $result = $this->mailer->send($cert['email'], $subject, $body, $attachments);
        
        if ($result) {
            $this->updateCertificateStatus($certificateId, 'sent');
        }

        return $result;
    }

    public function sendBatchCertificateEmails(array $certificateIds): array
    {
        $results = [];
        foreach ($certificateIds as $certificateId) {
            $results[$certificateId] = $this->sendCertificateEmail($certificateId);
        }
        return $results;
    }

    public function getCertificateStats(int $eventId): array
    {
        $pdo = $this->db->getConnection();
        $total = (int)$pdo->query("SELECT COUNT(*) FROM certificates WHERE event_id = $eventId")->fetchColumn();
        $generated = (int)$pdo->query("SELECT COUNT(*) FROM certificates WHERE event_id = $eventId AND status IN ('generated', 'sent', 'viewed')")->fetchColumn();
        $sent = (int)$pdo->query("SELECT COUNT(*) FROM certificates WHERE event_id = $eventId AND status IN ('sent', 'viewed')")->fetchColumn();
        $viewed = (int)$pdo->query("SELECT COUNT(*) FROM certificates WHERE event_id = $eventId AND status = 'viewed'")->fetchColumn();

        return [
            'total' => $total,
            'generated' => $generated,
            'sent' => $sent,
            'viewed' => $viewed,
            'pending' => $total - $generated,
        ];
    }
}
