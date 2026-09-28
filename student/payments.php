<?php require_once dirname(__DIR__) . '/templates/header.php'; ?>
<?php
$auth = new Auth(Database::getInstance());
$auth->requireLogin('student');
$user = $auth->currentUser();
$portal = new Portal(Database::getInstance());
$categories = $portal->getPaymentCategories();
$academicSessions = $portal->getAcademicSessions();
$payments = $portal->getStudentPayments((int)($user['id'] ?? 0));
$account = $portal->getActivePaymentAccount();
$errors = [];
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf();
        $selectedCategory = null;
        foreach ($categories as $category) {
            if ((string)$category['id'] === (string)($_POST['category_id'] ?? '')) {
                $selectedCategory = $category;
                break;
            }
        }
        if (!$selectedCategory) {
            throw new Exception('Please select a valid payment category.');
        }
        $paymentProof = null;
        if (!empty($_FILES['payment_proof']['tmp_name'])) {
            $upload = uploadFile($_FILES['payment_proof'], APP_ROOT . '/assets/uploads/payment_proofs');
            if (!$upload['success']) {
                throw new Exception($upload['message']);
            }
            $paymentProof = 'uploads/payment_proofs/' . $upload['filename'];
        }
        $categoryLabel = $selectedCategory['title'] ?? $selectedCategory['category'] ?? 'NACOS Payment';
        $sessionLabel = $_POST['session'] ?? '';
        $portal->createPayment([
            'user_id' => (int)($user['id'] ?? 0),
            'payment_type' => $categoryLabel,
            'session' => $sessionLabel,
            'amount' => $selectedCategory['amount'] ?? 0,
            'payment_proof' => $paymentProof,
            'status' => 'pending',
        ]);
        $success = 'Payment submitted successfully. Awaiting verification.';
        $payments = $portal->getStudentPayments((int)($user['id'] ?? 0));
    } catch (Exception $e) {
        $errors[] = $e->getMessage();
    }
}
$sessionOptions = [];
foreach ($academicSessions as $session) {
    $sessionOptions[] = ['id' => $session['id'] ?? $session['session_name'], 'name' => $session['session_name'] ?? $session['id']];
}
$currentYear = (int)date('Y');
for ($offset = -3; $offset <= 5; $offset++) {
    $year = $currentYear + $offset;
    $label = sprintf('%d/%d', $year, $year + 1);
    $found = false;
    foreach ($sessionOptions as $opt) {
        if ($opt['name'] === $label) {
            $found = true;
            break;
        }
    }
    if (!$found) {
        $sessionOptions[] = ['id' => $label, 'name' => $label];
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
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h4 class="fw-bold mb-1">Payments</h4>
                        <p class="text-secondary mb-0">Choose a category, review the amount, then upload proof to submit.</p>
                    </div>
                    <a href="<?= APP_URL ?>/student/dashboard.php" class="btn btn-outline-success btn-sm"><i data-lucide="arrow-left"></i> Back to Dashboard</a>
                </div>
                <?php foreach ($errors as $error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endforeach; ?>
                <?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
                <div class="row g-4">
                    <div class="col-lg-7">
                        <div class="card border-0 rounded-4 p-4 mb-4">
                            <h5 class="fw-bold mb-3">Make a Payment</h5>
                            <form method="post" enctype="multipart/form-data" id="payment-form">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Payment Category</label>
                                        <select name="category_id" id="payment-category" class="form-select" required>
                                            <option value="">Select a payment</option>
                                            <?php foreach ($categories as $category): ?>
                                                <option value="<?= e($category['id']) ?>" data-amount="<?= e($category['amount']) ?>"><?= e($category['title'] ?? $category['category']) ?> - ₦<?= e($category['amount']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Amount</label>
                                        <input type="text" name="amount" id="payment-amount" class="form-control" readonly>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Academic Session</label>
                                        <select name="session" class="form-select" required>
                                            <option value="">Select academic session</option>
                                            <?php foreach ($sessionOptions as $session): ?>
                                                <option value="<?= e($session['name']) ?>"><?= e($session['name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Proof of Payment</label>
                                        <input type="file" name="payment_proof" class="form-control" accept="image/*,.pdf" required>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">Remarks</label>
                                        <textarea name="notes" class="form-control" rows="3"></textarea>
                                    </div>
                                </div>
                                <button class="btn btn-primary mt-3">Continue</button>
                            </form>
                        </div>
                    </div>
                    <div class="col-lg-5">
                        <div class="card border-0 rounded-4 p-4 mb-4">
                            <h5 class="fw-bold mb-3">Bank Details</h5>
                            <div class="mb-3 text-secondary">
                                <p class="mb-2"><strong>Bank:</strong> <?= e($account['bank_name'] ?? 'N/A') ?></p>
                                <p class="mb-2"><strong>Account Name:</strong> <?= e($account['account_name'] ?? 'N/A') ?></p>
                                <p class="mb-2"><strong>Account Number:</strong> <span id="account-number"><?= e($account['account_number'] ?? 'N/A') ?></span></p>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="copy-account">Copy Account Number</button>
                            </div>
                            <p class="text-secondary">Make the payment outside the system and return to upload your receipt for verification.</p>
                        </div>
                    </div>
                </div>
                <div class="card border-0 rounded-4 p-4">
                    <h5 class="fw-bold mb-3">Payment History</h5>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr><th>Category</th><th>Amount</th><th>Session</th><th>Status</th><th>Date</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($payments as $payment): ?>
                                    <tr>
                                        <td><?= e($payment['payment_type'] ?? 'N/A') ?></td>
                                        <td>₦<?= e($payment['amount']) ?></td>
                                        <td><?= e($payment['session'] ?? 'N/A') ?></td>
                                        <td><?= ucfirst(e($payment['status'] ?? 'pending')) ?></td>
                                        <td><?= e($payment['created_at']) ?></td>
                                    </tr>
                                    <?php if (($payment['status'] ?? 'pending') === 'rejected' && !empty($payment['rejection_reason'])): ?>
                                        <tr><td colspan="5" class="text-danger small">Rejected: <?= e($payment['rejection_reason']) ?></td></tr>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const categorySelect = document.getElementById('payment-category');
        const amountField = document.getElementById('payment-amount');
        const copyButton = document.getElementById('copy-account');
        const accountNumber = document.getElementById('account-number');
        if (categorySelect && amountField) {
            categorySelect.addEventListener('change', function() {
                const selected = categorySelect.selectedOptions[0];
                amountField.value = selected?.dataset.amount ? '₦' + selected.dataset.amount : '';
            });
        }
        if (copyButton && accountNumber) {
            copyButton.addEventListener('click', function() {
                navigator.clipboard.writeText(accountNumber.textContent.trim()).then(function() {
                    copyButton.textContent = 'Copied';
                    setTimeout(() => { copyButton.textContent = 'Copy Account Number'; }, 1800);
                });
            });
        }
    });
</script>
<?php require_once dirname(__DIR__) . '/templates/footer.php'; ?>
