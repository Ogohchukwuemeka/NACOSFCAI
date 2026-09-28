<?php
require_once dirname(__DIR__) . '/config/bootstrap.php';
use Dompdf\Dompdf;

$auth = new Auth(Database::getInstance());
$auth->requireLogin('student');
$user = $auth->currentUser();
$portal = new Portal(Database::getInstance());
$payments = $portal->getStudentPayments((int)($user['id'] ?? 0));

$certificateAccess = false;
$verifiedPayment = null;
foreach ($payments as $payment) {
    $status = strtolower($payment['status'] ?? '');
    $paymentType = strtolower($payment['payment_type'] ?? $payment['category'] ?? '');
    if (in_array($status, ['approved', 'verified'], true) && (str_contains($paymentType, 'dues') || str_contains($paymentType, 'membership'))) {
        $certificateAccess = true;
        $verifiedPayment = $payment;
        break;
    }
}

if (!$certificateAccess) {
    header('Location: ' . APP_URL . '/student/certificate.php');
    exit;
}

// Ensure the verified payment has the official membership number assigned.
if (empty($verifiedPayment['membership_number'])) {
    // Membership number missing — generate and persist to avoid blocking student download.
    $pdo = Database::getInstance()->getConnection();
    try {
        $mn = generateMembershipNumber($pdo);
        $stmt = $pdo->prepare('UPDATE payments SET membership_number = :mn WHERE id = :id');
        $stmt->execute(['mn' => $mn, 'id' => $verifiedPayment['id']]);
        $verifiedPayment['membership_number'] = $mn;
    } catch (Throwable $e) {
        header('Location: ' . APP_URL . '/student/certificate.php?error=missing_membership_number');
        exit;
    }
}

// Pass the verified payment (which contains the system-generated membership number)
$html = generateMembershipCertificateHtml($user, $verifiedPayment ?: []);

$options = new \Dompdf\Options();
$options->set('defaultFont', 'Arial');
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="nacos-certificate-' . preg_replace('/[^a-zA-Z0-9_-]/', '', strtolower($user['full_name'] ?? 'student')) . '.pdf"');
echo $dompdf->output();
exit;
