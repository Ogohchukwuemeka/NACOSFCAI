<?php
/**
 * Certificate PDF Generation API
 * Generates PDF certificates from HTML templates using DomPDF
 * 
 * Usage: POST /api/generate-certificate-pdf.php
 * Parameters: certificate_id (int)
 */

require_once dirname(__DIR__) . '/config/bootstrap.php';

header('Content-Type: application/json');

use Dompdf\Dompdf;
use Dompdf\Options;

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$pdo = Database::getInstance()->getConnection();
$userRole = $_SESSION['user_role'] ?? null;
$userId = (int)($_SESSION['user_id'] ?? 0);

if (!in_array($userRole, ['student', 'admin'], true)) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}
$auth = new Auth(Database::getInstance());
$auth->requireLogin($userRole);

try {
    verify_csrf();
} catch (Exception $e) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid CSRF token']);
    exit;
}

if ($userRole === 'student') {
    $stmt = $pdo->prepare('SELECT user_id FROM students WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $userId]);
    $resolvedUserId = (int)$stmt->fetchColumn();
    if ($resolvedUserId <= 0) {
        http_response_code(403);
        echo json_encode(['error' => 'Unauthorized certificate access']);
        exit;
    }
    $userId = $resolvedUserId;
}

// Get certificate ID
$certificateId = (int)($_POST['certificate_id'] ?? 0);

if (!$certificateId) {
    http_response_code(400);
    echo json_encode(['error' => 'Certificate ID required']);
    exit;
}

try {
    $pdo = Database::getInstance()->getConnection();
    $certificate = new Certificate(Database::getInstance());
    
    // Fetch certificate details
    $stmt = $pdo->prepare('SELECT * FROM certificates WHERE id = :id');
    $stmt->execute(['id' => $certificateId]);
    $cert = $stmt->fetch();
    
    if (!$cert) {
        http_response_code(404);
        echo json_encode(['error' => 'Certificate not found']);
        exit;
    }

    if ($userRole === 'student' && (int)$cert['student_id'] !== $userId) {
        http_response_code(403);
        echo json_encode(['error' => 'Unauthorized certificate access']);
        exit;
    }
    
    // Generate HTML
    $html = $certificate->generateCertificateHtml($certificateId);
    
    if (!$html) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to generate certificate HTML']);
        exit;
    }
    
    // Create upload directory if needed
    $uploadDir = dirname(__DIR__) . '/assets/uploads/certificates';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Generate unique filename
    $filename = 'cert-' . str_pad($certificateId, 6, '0', STR_PAD_LEFT) . '-' . uniqid() . '.pdf';
    $filepath = $uploadDir . '/' . $filename;
    $dbPath = 'assets/uploads/certificates/' . $filename;
    
    // Initialize DomPDF
    $options = new Options();
    $options->set('defaultFont', 'Garamond');
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isPhpEnabled', false);
    
    $dompdf = new Dompdf($options);
    
    // Load HTML into DomPDF
    $dompdf->loadHtml($html);
    
    // Set paper size and orientation
    $dompdf->setPaper('A4', 'landscape');
    
    // Render the PDF
    $dompdf->render();
    
    // Save to file
    $output = $dompdf->output();
    file_put_contents($filepath, $output);
    
    if (!file_exists($filepath)) {
        throw new Exception('Failed to save PDF file');
    }
    
    // Update certificate record with PDF path
    $stmt = $pdo->prepare("
        UPDATE certificates 
        SET pdf_path = :path, status = 'generated'
        WHERE id = :id
    ");
    $stmt->execute([
        ':path' => $dbPath,
        ':id' => $certificateId
    ]);
    
    // Return success
    echo json_encode([
        'success' => true,
        'message' => 'Certificate PDF generated successfully',
        'certificate_id' => $certificateId,
        'pdf_path' => $dbPath
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Failed to generate PDF: ' . $e->getMessage()
    ]);
    exit;
}
