<?php
require_once dirname(__DIR__) . '/config/bootstrap.php';
use Dompdf\Dompdf;

$auth = new Auth(Database::getInstance());
$auth->requireLogin('student');
$user = $auth->currentUser();
$portal = new Portal(Database::getInstance());
$payments = $portal->getStudentPayments((int)($user['id'] ?? 0));

$idCardAccess = false;
$verifiedPayment = null;
foreach ($payments as $payment) {
    $status = strtolower($payment['status'] ?? '');
    $paymentType = strtolower($payment['payment_type'] ?? $payment['category'] ?? '');
    if (in_array($status, ['approved', 'verified'], true) && str_contains($paymentType, 'id card')) {
        $idCardAccess = true;
        $verifiedPayment = $payment;
        break;
    }
}

if (!$idCardAccess) {
    header('Location: ' . APP_URL . '/student/id-card.php');
    exit;
}

$systemMembershipNumber = '';
$membershipSourcePayment = null;
foreach ($payments as $payment) {
    $status = strtolower($payment['status'] ?? '');
    $paymentType = strtolower($payment['payment_type'] ?? $payment['category'] ?? '');
    if (in_array($status, ['approved', 'verified'], true) && (str_contains($paymentType, 'dues') || str_contains($paymentType, 'membership'))) {
        if (!empty($payment['membership_number'])) {
            $systemMembershipNumber = $payment['membership_number'];
            break;
        }
        $membershipSourcePayment = $payment;
    }
}
if ($systemMembershipNumber === '' && $membershipSourcePayment !== null) {
    try {
        $pdo = Database::getInstance()->getConnection();
        $systemMembershipNumber = generateMembershipNumber($pdo);
        $stmt = $pdo->prepare('UPDATE payments SET membership_number = :mn WHERE id = :id');
        $stmt->execute(['mn' => $systemMembershipNumber, 'id' => $membershipSourcePayment['id']]);
    } catch (Throwable $e) {
        // if certificate membership assignment fails, fall back to id-card payment later
    }
}
if ($systemMembershipNumber === '') {
    $systemMembershipNumber = $verifiedPayment['membership_number'] ?? '';
}

$cardOverrides = [
    'membership_number' => $systemMembershipNumber,
    'issued_at' => $verifiedPayment['verified_at'] ?? $verifiedPayment['created_at'] ?? '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf();
        $profilePhoto = $user['profile_photo'] ?? '';
        if (!empty($_FILES['profile_photo']['tmp_name'])) {
            $result = uploadFile($_FILES['profile_photo'], APP_ROOT . '/assets/uploads/profile_pictures');
            if (!$result['success']) {
                throw new Exception($result['message']);
            }
            $profilePhoto = 'assets/uploads/profile_pictures/' . $result['filename'];
        }

        $portal->updateStudentProfile((int)($user['id'] ?? 0), [
            'full_name' => trim($_POST['full_name'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'phone' => trim($_POST['phone'] ?? ''),
            'course' => trim($_POST['course'] ?? ''),
            'department' => trim($_POST['department'] ?? ''),
            'profile_photo' => $profilePhoto,
        ]);

        $user = (new Auth(Database::getInstance()))->currentUser();
        $cardOverrides = [
            'full_name' => trim($_POST['full_name'] ?? $user['full_name'] ?? ''),
            'email' => trim($_POST['email'] ?? $user['email'] ?? ''),
            'phone' => trim($_POST['phone'] ?? $user['phone'] ?? ''),
            'course' => trim($_POST['course'] ?? $user['course'] ?? $user['course_of_study'] ?? $user['department'] ?? ''),
            'department' => trim($_POST['department'] ?? $user['department'] ?? ''),
            'profile_photo' => $profilePhoto,
            'membership_number' => $systemMembershipNumber,
        ];
    } catch (Throwable $e) {
        error_log($e->getMessage());
    }
}

$html = generateStudentIdCardHtml($user, $cardOverrides);

$options = new \Dompdf\Options();
$options->set('defaultFont', 'Arial');
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper([0, 0, 156, 240], 'portrait');
$dompdf->render();

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="nacos-id-card-' . preg_replace('/[^a-zA-Z0-9_-]/', '', strtolower($user['full_name'] ?? 'student')) . '.pdf"');
echo $dompdf->output();
exit;
