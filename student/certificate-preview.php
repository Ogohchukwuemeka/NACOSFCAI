<?php
require_once dirname(__DIR__) . '/config/bootstrap.php';

$auth = new Auth(Database::getInstance());
$auth->requireLogin('student');
$user = $auth->currentUser();
$portal = new Portal(Database::getInstance());
$payments = $portal->getStudentPayments((int)($user['id'] ?? 0));

$certificateAccess = false;
$certificatePayment = null;
foreach ($payments as $payment) {
    $status = strtolower($payment['status'] ?? '');
    $paymentType = strtolower($payment['payment_type'] ?? $payment['category'] ?? '');
    if ((str_contains($paymentType, 'dues') || str_contains($paymentType, 'membership')) && in_array($status, ['approved', 'verified'], true)) {
        $certificateAccess = true;
        $certificatePayment = $payment;
        break;
    }
}

if (!$certificateAccess) {
    http_response_code(403);
    echo '<p>Certificate preview is unavailable until your dues payment is verified.</p>';
    exit;
}

if (empty($certificatePayment['membership_number'])) {
    try {
        $pdo = Database::getInstance()->getConnection();
        $generatedNumber = generateMembershipNumber($pdo);
        $stmt = $pdo->prepare('UPDATE payments SET membership_number = :mn WHERE id = :id');
        $stmt->execute(['mn' => $generatedNumber, 'id' => $certificatePayment['id']]);
        $certificatePayment['membership_number'] = $generatedNumber;
    } catch (Throwable $e) {
        http_response_code(500);
        echo '<p>Unable to assign a membership number for certificate preview. Please refresh or contact support.</p>';
        exit;
    }
}

header('Content-Type: text/html; charset=UTF-8');

echo generateMembershipCertificateHtml($user, $certificatePayment, true);
