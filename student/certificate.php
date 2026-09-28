<?php require_once dirname(__DIR__) . '/templates/header.php'; ?>
<?php
$auth = new Auth(Database::getInstance());
$auth->requireLogin('student');
$user = $auth->currentUser();
$portal = new Portal(Database::getInstance());
$settings = $portal->getSettings();
$payments = $portal->getStudentPayments((int)($user['id'] ?? 0));
$errors = [];
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

if ($certificateAccess && !empty($certificatePayment) && empty($certificatePayment['membership_number'])) {
    try {
        $pdo = Database::getInstance()->getConnection();
        $generatedNumber = generateMembershipNumber($pdo);
        $stmt = $pdo->prepare('UPDATE payments SET membership_number = :mn WHERE id = :id');
        $stmt->execute(['mn' => $generatedNumber, 'id' => $certificatePayment['id']]);
        $certificatePayment['membership_number'] = $generatedNumber;
    } catch (Throwable $e) {
        $errors[] = 'Unable to assign a certificate membership number. Please try again or contact support.';
    }
}
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
                        <h4 class="fw-bold mb-1">Certificate</h4>
                        <p class="text-secondary mb-0">Download your NACOS certificate once dues are approved.</p>
                    </div>
                    <?php if ($certificateAccess): ?>
                        <a class="btn btn-success" href="<?= APP_URL ?>/student/certificate-download.php">Download Certificate</a>
                    <?php endif; ?>
                </div>
                <?php if (!empty($_GET['error'])): ?>
                    <div class="alert alert-warning">
                        <?php if ($_GET['error'] === 'missing_membership_number'): ?>
                            <strong>Membership number missing:</strong> we could not assign a certificate number automatically. Please reload the page or contact support.
                        <?php else: ?>
                            <?= e($_GET['error']) ?>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                <?php foreach ($errors as $error): ?>
                    <div class="alert alert-danger"><?= e($error) ?></div>
                <?php endforeach; ?>
                <?php if (!$certificateAccess): ?>
                    <div class="alert alert-warning">
                        <h5 class="mb-2">Access Denied</h5>
                        <p>Please complete and verify your NACOS dues payment before your certificate becomes available.</p>
                    </div>
                <?php else: ?>
                    <div class="certificate-preview">
                        <div class="certificate-preview-inner">
                            <div class="preview-header">
                                <div>
                                    <strong class="text-dark">Certificate Preview</strong>
                                    <div class="text-secondary small">This preview reflects the downloadable PDF layout.</div>
                                </div>
                                <a class="btn btn-success btn-sm" href="<?= APP_URL ?>/student/certificate-download.php">Download Certificate</a>
                            </div>
                            <iframe src="<?= APP_URL ?>/student/certificate-preview.php" title="Certificate preview"></iframe>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php require_once dirname(__DIR__) . '/templates/footer.php'; ?>
