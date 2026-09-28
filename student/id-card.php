<?php require_once dirname(__DIR__) . '/templates/header.php'; ?>
<?php
$auth = new Auth(Database::getInstance());
$auth->requireLogin('student');
$user = $auth->currentUser();
$portal = new Portal(Database::getInstance());
$errors = [];
$success = '';
$payments = $portal->getStudentPayments((int)($user['id'] ?? 0));
$idCardAccess = false;
$idCardPayment = null;
foreach ($payments as $payment) {
    $status = strtolower($payment['status'] ?? '');
    $paymentType = strtolower($payment['payment_type'] ?? $payment['category'] ?? '');
    if (str_contains($paymentType, 'id card') && in_array($status, ['approved', 'verified'], true)) {
        $idCardAccess = true;
        $idCardPayment = $payment;
        break;
    }
}

$sharedMembershipNumber = '';
$membershipSourcePayment = null;
foreach ($payments as $payment) {
    $status = strtolower($payment['status'] ?? '');
    $paymentType = strtolower($payment['payment_type'] ?? $payment['category'] ?? '');
    if (in_array($status, ['approved', 'verified'], true) && (str_contains($paymentType, 'dues') || str_contains($paymentType, 'membership'))) {
        if (!empty($payment['membership_number'])) {
            $sharedMembershipNumber = $payment['membership_number'];
            break;
        }
        $membershipSourcePayment = $payment;
    }
}
if ($sharedMembershipNumber === '' && $membershipSourcePayment !== null) {
    try {
        $pdo = Database::getInstance()->getConnection();
        $sharedMembershipNumber = generateMembershipNumber($pdo);
        $stmt = $pdo->prepare('UPDATE payments SET membership_number = :mn WHERE id = :id');
        $stmt->execute(['mn' => $sharedMembershipNumber, 'id' => $membershipSourcePayment['id']]);
    } catch (Throwable $e) {
        // keep preview logic unchanged if assignment fails
    }
}
$systemMembershipNumber = $sharedMembershipNumber !== '' ? $sharedMembershipNumber : ($idCardPayment['membership_number'] ?? '');

$previewData = [
    'full_name' => $user['full_name'] ?? '',
    'email' => $user['email'] ?? '',
    'phone' => $user['phone'] ?? '',
    'course' => $user['course'] ?? $user['course_of_study'] ?? $user['department'] ?? '',
    'department' => $user['department'] ?? '',
    'profile_photo' => $user['profile_photo'] ?? '',
    'membership_number' => $systemMembershipNumber,
    'issued_at' => $idCardPayment['verified_at'] ?? $idCardPayment['created_at'] ?? '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf();
        $action = $_POST['action'] ?? 'preview';
        $profilePhoto = $previewData['profile_photo'];
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
        $previewData = [
            'full_name' => $user['full_name'] ?? '',
            'email' => $user['email'] ?? '',
            'phone' => $user['phone'] ?? '',
            'course' => $user['course'] ?? $user['course_of_study'] ?? $user['department'] ?? '',
            'department' => $user['department'] ?? '',
            'profile_photo' => $profilePhoto,
            'membership_number' => $systemMembershipNumber,
            'issued_at' => $idCardPayment['created_at'] ?? '',
        ];

        if ($action === 'download') {
            header('Location: ' . APP_URL . '/student/id-card-download.php');
            exit;
        }

        $success = 'ID card details updated. Preview refreshed below.';
    } catch (Exception $e) {
        $errors[] = $e->getMessage();
    }
}

$previewView = ($_GET['view'] ?? 'front');
$previewFrontHtml = generateStudentIdCardHtml($user, $previewData, 'front');
$previewBackHtml = generateStudentIdCardHtml($user, $previewData, 'back');
$previewHtml = $previewView === 'back' ? $previewBackHtml : $previewFrontHtml;
?>
<div class="student-page-shell container py-5">
    <div class="row g-4">
        <div class="col-lg-3">
            <?php require_once dirname(__DIR__) . '/templates/student-sidebar.php'; ?>
        </div>
        <div class="col-lg-9">
            <div class="card shadow-sm border-0 rounded-4 p-4">
                <div class="d-flex justify-content-between align-items-start gap-3 mb-4">
                    <div>
                        <h4 class="fw-bold mb-1">ID Card</h4>
                        <p class="text-secondary mb-0">Update your details, preview the card, and download the PDF.</p>
                    </div>
                </div>
                <?php foreach ($errors as $error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endforeach; ?>
                <?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
                <?php if (!$idCardAccess): ?>
                    <div class="alert alert-warning">
                        <h5 class="mb-2">Access Denied</h5>
                        <p>Please complete and verify your ID Card payment before your ID card becomes available.</p>
                    </div>
                <?php else: ?>
                    <form method="post" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Full Name</label>
                                <input type="text" name="full_name" class="form-control" value="<?= e((string)($previewData['full_name'] ?? '')) ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Email</label>
                                <input type="email" name="email" class="form-control" value="<?= e((string)($previewData['email'] ?? '')) ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Phone</label>
                                <input type="text" name="phone" class="form-control" value="<?= e((string)($previewData['phone'] ?? '')) ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Course of Study</label>
                                <input type="text" name="course" class="form-control" value="<?= e((string)($previewData['course'] ?? '')) ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Department</label>
                                <input type="text" name="department" class="form-control" value="<?= e((string)($previewData['department'] ?? '')) ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Profile Photo</label>
                                <input type="file" name="profile_photo" class="form-control" accept="image/*">
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-bold">Membership Number</label>
                                <input type="text" class="form-control" value="<?= e($systemMembershipNumber) ?>" disabled>
                                <small class="text-secondary">System-generated ID (view only)</small>
                            </div>
                        </div>

                        <div class="d-flex flex-wrap gap-2 mb-4">
                            <button type="submit" class="btn btn-primary">Update & Preview</button>
                            <a href="<?= APP_URL ?>/student/id-card.php?view=front" class="btn btn-outline-info">View Front</a>
                            <a href="<?= APP_URL ?>/student/id-card.php?view=back" class="btn btn-outline-info">View Back</a>
                            <button type="submit" formaction="<?= APP_URL ?>/student/id-card-download.php" class="btn btn-success">Download PDF</button>
                        </div>
                    </form>

                    <div class="mt-4 rounded-4 border overflow-hidden" style="background: #fff;">
                        <div class="p-3 border-bottom bg-light d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <div>
                                <strong class="text-dark">ID Card Preview</strong>
                                <div class="text-secondary small">Currently showing: <span class="fw-bold"><?= $previewView === 'back' ? 'Back Side' : 'Front Side' ?></span></div>
                            </div>
                        </div>
                        <iframe srcdoc="<?= e($previewHtml) ?>" class="w-100 border-0" style="min-height: 500px; background: #f8f9fa;"></iframe>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php require_once dirname(__DIR__) . '/templates/footer.php'; ?>
