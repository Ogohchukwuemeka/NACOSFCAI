<?php
require_once dirname(__DIR__) . '/config/bootstrap.php';

$auth = new Auth(Database::getInstance());
$auth->requireLogin('admin');

$paymentId = isset($_GET['payment_id']) ? (int)$_GET['payment_id'] : 0;
if ($paymentId <= 0) {
    header('Location: ' . APP_URL . '/admin/id-cards.php');
    exit;
}

$pdo = Database::getInstance()->getConnection();
$stmt = $pdo->prepare('SELECT p.*, u.full_name, u.email, u.department, COALESCE(u.matric_number, s.registration_number, "N/A") AS matric_number, COALESCE(s.phone, "") AS phone, s.course, s.profile_photo FROM payments p LEFT JOIN users u ON u.id = p.user_id LEFT JOIN students s ON s.user_id = p.user_id WHERE p.id = :id LIMIT 1');
$stmt->execute(['id' => $paymentId]);
$payment = $stmt->fetch();

if (!$payment) {
    header('Location: ' . APP_URL . '/admin/id-cards.php');
    exit;
}

$status = strtolower((string)($payment['status'] ?? ''));
$paymentType = strtolower((string)($payment['payment_type'] ?? $payment['category'] ?? ''));
if (!in_array($status, ['approved', 'verified'], true) || !str_contains($paymentType, 'id card')) {
    header('Location: ' . APP_URL . '/admin/id-cards.php');
    exit;
}

$systemMembershipNumber = $payment['membership_number'] ?? '';
if ($systemMembershipNumber === '') {
    try {
        $systemMembershipNumber = generateMembershipNumber(Database::getInstance()->getConnection());
        $update = $pdo->prepare('UPDATE payments SET membership_number = :mn WHERE id = :id');
        $update->execute(['mn' => $systemMembershipNumber, 'id' => $paymentId]);
    } catch (Throwable $e) {
        // continue, the PDF can still be generated with blank membership if necessary
    }
}

$idCardData = [
    'full_name' => $payment['full_name'] ?? '',
    'email' => $payment['email'] ?? '',
    'phone' => $payment['phone'] ?? '',
    'course' => $payment['course'] ?? '',
    'department' => $payment['department'] ?? '',
    'profile_photo' => $payment['profile_photo'] ?? '',
    'membership_number' => $systemMembershipNumber,
    'issued_at' => $payment['verified_at'] ?? $payment['created_at'] ?? '',
];

$html = generateStudentIdCardHtml($idCardData, $idCardData, 'both');

$options = new \Dompdf\Options();
$options->set('defaultFont', 'Arial');
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);

$dompdf = new \Dompdf\Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper([0, 0, 156, 240], 'portrait');
$dompdf->render();

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="nacos-id-card-' . preg_replace('/[^a-zA-Z0-9_-]/', '', strtolower($payment['full_name'] ?? 'student')) . '.pdf"');
echo $dompdf->output();
exit;
