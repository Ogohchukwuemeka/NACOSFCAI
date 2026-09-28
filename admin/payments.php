<?php require_once dirname(__DIR__) . '/templates/header.php'; ?>
<?php
$auth = new Auth(Database::getInstance());
$auth->requireLogin('admin');
$portal = new Portal(Database::getInstance());
$pdo = Database::getInstance()->getConnection();
$portal->ensurePaymentSchema();

$flashMessage = $_SESSION['flash_message'] ?? '';
$flashType = $_SESSION['flash_type'] ?? 'success';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  try {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    $adminId = (int)($_SESSION['user_id'] ?? 0);
    $message = '';

    if ($action === 'create_category') {
      $categoryId = $portal->createPaymentCategory([
        'title' => trim($_POST['title'] ?? ''),
        'category' => trim($_POST['category'] ?? 'payment'),
        'amount' => (float)($_POST['amount'] ?? 0),
        'description' => trim($_POST['description'] ?? ''),
        'due_date' => trim($_POST['due_date'] ?? ''),
        'status' => trim($_POST['status'] ?? 'active'),
        'academic_session' => trim($_POST['academic_session'] ?? ''),
      ]);
      $portal->logAudit($adminId, 'create_payment_category', 'Created payment category ID ' . $categoryId);
      $message = 'Payment category created.';
    } elseif ($action === 'update_category') {
      $categoryId = (int)($_POST['category_id'] ?? 0);
      if ($categoryId > 0) {
        $portal->updatePaymentCategory($categoryId, [
          'title' => trim($_POST['title'] ?? ''),
          'category' => trim($_POST['category'] ?? 'payment'),
          'amount' => (float)($_POST['amount'] ?? 0),
          'description' => trim($_POST['description'] ?? ''),
          'due_date' => trim($_POST['due_date'] ?? ''),
          'status' => trim($_POST['status'] ?? 'active'),
          'academic_session' => trim($_POST['academic_session'] ?? ''),
        ]);
        $portal->logAudit($adminId, 'update_payment_category', 'Updated payment category ID ' . $categoryId);
        $message = 'Payment category updated.';
      }
    } elseif ($action === 'activate_category') {
      $categoryId = (int)($_POST['category_id'] ?? 0);
      if ($categoryId > 0) {
        $portal->setPaymentCategoryStatus($categoryId, 'active');
        $portal->logAudit($adminId, 'activate_payment_category', 'Activated payment category ID ' . $categoryId);
        $message = 'Payment category activated.';
      }
    } elseif ($action === 'deactivate_category') {
      $categoryId = (int)($_POST['category_id'] ?? 0);
      if ($categoryId > 0) {
        $portal->setPaymentCategoryStatus($categoryId, 'inactive');
        $portal->logAudit($adminId, 'deactivate_payment_category', 'Deactivated payment category ID ' . $categoryId);
        $message = 'Payment category deactivated.';
      }
    } elseif ($action === 'delete_category') {
      $categoryId = (int)($_POST['category_id'] ?? 0);
      if ($categoryId > 0) {
        $portal->deletePaymentCategory($categoryId);
        $portal->logAudit($adminId, 'delete_payment_category', 'Deleted payment category ID ' . $categoryId);
        $message = 'Payment category deleted.';
      }
    } elseif ($action === 'verify_payment') {
      $paymentId = (int)($_POST['payment_id'] ?? 0);
      if ($paymentId > 0) {
        $portal->verifyPaymentSubmission($paymentId, $adminId);
        $payment = $pdo->prepare('SELECT p.payment_type, p.session, p.amount, p.payment_reference, u.email, u.full_name FROM payments p LEFT JOIN users u ON u.id = p.user_id WHERE p.id = :id LIMIT 1');
        $payment->execute(['id' => $paymentId]);
        $paymentRow = $payment->fetch();
        if ($paymentRow && !empty($paymentRow['email'])) {
          $mailer = new Mailer(Database::getInstance());
          $student = ['email' => $paymentRow['email'], 'full_name' => $paymentRow['full_name'] ?? 'Student'];
          $mailer->sendPaymentVerifiedEmail($student, $paymentRow);
        }
        $message = 'Payment verified.';
      }
    } elseif ($action === 'reject_payment') {
      $paymentId = (int)($_POST['payment_id'] ?? 0);
      $reason = trim($_POST['rejection_reason'] ?? '');
      if ($paymentId > 0 && $reason !== '') {
        $portal->rejectPaymentSubmission($paymentId, $reason, $adminId);
        $payment = $pdo->prepare('SELECT p.payment_type, p.session, p.amount, p.payment_reference, u.email, u.full_name FROM payments p LEFT JOIN users u ON u.id = p.user_id WHERE p.id = :id LIMIT 1');
        $payment->execute(['id' => $paymentId]);
        $paymentRow = $payment->fetch();
        if ($paymentRow && !empty($paymentRow['email'])) {
          $mailer = new Mailer(Database::getInstance());
          $student = ['email' => $paymentRow['email'], 'full_name' => $paymentRow['full_name'] ?? 'Student'];
          $mailer->sendPaymentRejectedEmail($student, $reason, $paymentRow);
        }
        $message = 'Payment rejected.';
      }
    } elseif ($action === 'update_payment') {
      $paymentId = (int)($_POST['payment_id'] ?? 0);
      if ($paymentId > 0) {
        $pdo->prepare('UPDATE payments SET payment_type = :payment_type, amount = :amount, status = :status, session = :session, payment_reference = :payment_reference WHERE id = :id')->execute([
          'payment_type' => trim($_POST['payment_type'] ?? 'Membership'),
          'amount' => (float)($_POST['amount'] ?? 0),
          'status' => trim($_POST['status'] ?? 'pending'),
          'session' => trim($_POST['session'] ?? ''),
          'payment_reference' => trim($_POST['payment_reference'] ?? ''),
          'id' => $paymentId,
        ]);
        $portal->logAudit($adminId, 'update_payment', 'Updated payment ID ' . $paymentId);
        $message = 'Payment updated.';
      }
    } elseif ($action === 'delete_payment') {
      $paymentId = (int)($_POST['payment_id'] ?? 0);
      if ($paymentId > 0) {
        $portal->deletePaymentSubmission($paymentId, $adminId);
        $message = 'Payment deleted.';
      }
    }

    $_SESSION['flash_message'] = $message !== '' ? $message : 'Action completed.';
    $_SESSION['flash_type'] = 'success';
  } catch (Exception $e) {
    $_SESSION['flash_message'] = $e->getMessage();
    $_SESSION['flash_type'] = 'error';
  }
  redirect('admin/payments.php');
}

$filters = [
  'session' => trim($_GET['academic_session'] ?? ''),
  'payment_type' => trim($_GET['payment_type'] ?? ''),
  'status' => trim($_GET['status'] ?? ''),
  'student_name' => trim($_GET['student_name'] ?? ''),
  'date_from' => trim($_GET['date_from'] ?? ''),
  'date_to' => trim($_GET['date_to'] ?? ''),
  'search' => trim($_GET['q'] ?? ''),
];
$payments = $portal->listPaymentsForAdmin($filters);
$paymentCategories = $portal->listPaymentCategories(false);
$stats = $portal->getPaymentDashboardStats();
$academicSessions = $portal->getAcademicSessions();
$sessionOptions = [];
foreach ($academicSessions as $session) {
  $sessionOptions[] = $session['session_name'] ?? $session['id'];
}
$paymentTypes = [];
foreach ($payments as $payment) {
  $paymentType = $payment['payment_type'] ?? '';
  if ($paymentType !== '' && !in_array($paymentType, $paymentTypes, true)) {
    $paymentTypes[] = $paymentType;
  }
}
foreach ($paymentCategories as $category) {
  $paymentType = $category['title'] ?? $category['category'] ?? '';
  if ($paymentType !== '' && !in_array($paymentType, $paymentTypes, true)) {
    $paymentTypes[] = $paymentType;
  }
}
sort($paymentTypes);
function resolveReceiptPath(string $path): string
{
  if ($path === '') {
    return '';
  }
  if (str_starts_with($path, 'http')) {
    return $path;
  }
  if (str_starts_with($path, 'assets/')) {
    return APP_URL . '/' . ltrim($path, '/');
  }
  return asset($path);
}
?>
<div class="payments-page">
  <section class="dashboard-hero admin-hero">
    <div class="container py-5">
      <div class="row align-items-center g-4">
        <div class="col-lg-8" data-aos="fade-up">
          <div class="hero-badge"><i data-lucide="credit-card"></i> Payment Center</div>
          <h1 class="display-5 fw-bold mt-3 mb-3">Payment Management</h1>
          <p class="lead mb-4">Create payment categories, review student submissions, verify payments, and log every action in the portal.</p>
        </div>
        <div class="col-lg-4" data-aos="zoom-in" data-aos-delay="100">
          <div class="profile-hero-card glass-card">
            <div class="dashboard-icon large"><i data-lucide="wallet"></i></div>
            <div class="mt-3">
              <h5 class="mb-1">Review Payments</h5>
              <p class="text-secondary mb-0">Status overview and payment history</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section class="container py-4">
    <div class="row g-4 align-items-start">
      <div class="col-lg-3">
        <?php require_once dirname(__DIR__) . '/templates/admin-sidebar.php'; ?>
      </div>
      <div class="col-lg-9">
        <?php if ($flashMessage): ?>
          <div class="alert alert-<?= $flashType === 'error' ? 'danger' : 'success' ?> mb-4"><?= e($flashMessage) ?></div>
        <?php endif; ?>

        <div class="row g-3 mb-4">
          <div class="col-md-3"><div class="glass-card p-3"><div class="small text-secondary">Payment Categories</div><h4 class="fw-bold mb-0"><?= (int)$stats['categories'] ?></h4></div></div>
          <div class="col-md-3"><div class="glass-card p-3"><div class="small text-secondary">Pending Payments</div><h4 class="fw-bold mb-0"><?= (int)$stats['pending'] ?></h4></div></div>
          <div class="col-md-3"><div class="glass-card p-3"><div class="small text-secondary">Verified Payments</div><h4 class="fw-bold mb-0"><?= (int)$stats['verified'] ?></h4></div></div>
          <div class="col-md-3"><div class="glass-card p-3"><div class="small text-secondary">Rejected Payments</div><h4 class="fw-bold mb-0"><?= (int)$stats['rejected'] ?></h4></div></div>
        </div>
        <div class="row g-3 mb-4">
          <div class="col-md-4"><div class="glass-card p-3"><div class="small text-secondary">Total Revenue</div><h4 class="fw-bold mb-0">₦<?= number_format((float)$stats['revenue'], 2) ?></h4></div></div>
          <div class="col-md-4"><div class="glass-card p-3"><div class="small text-secondary">Today's Payments</div><h4 class="fw-bold mb-0">₦<?= number_format((float)$stats['today'], 2) ?></h4></div></div>
          <div class="col-md-4"><div class="glass-card p-3"><div class="small text-secondary">This Month</div><h4 class="fw-bold mb-0">₦<?= number_format((float)$stats['month'], 2) ?></h4></div></div>
        </div>

        <div class="glass-card p-4 mb-4" data-aos="fade-up">
          <h4 class="fw-bold mb-3">Create Payment Category</h4>
          <form method="post" class="row g-3">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="create_category">
            <div class="col-md-3"><input type="text" name="title" class="form-control" placeholder="Payment name" required></div>
            <div class="col-md-2"><input type="number" name="amount" class="form-control" placeholder="Amount" min="0" step="0.01" required></div>
            <div class="col-md-2"><input type="text" name="academic_session" class="form-control" placeholder="Session"></div>
            <div class="col-md-2"><select name="status" class="form-select"><option value="active">Active</option><option value="inactive">Inactive</option></select></div>
            <div class="col-md-3"><input type="date" name="due_date" class="form-control"></div>
            <div class="col-12"><textarea name="description" class="form-control" rows="2" placeholder="Description"></textarea></div>
            <div class="col-12"><button type="submit" class="btn btn-success">Save Category</button></div>
          </form>
        </div>

        <div class="glass-card p-4 mb-4" data-aos="fade-up">
          <h4 class="fw-bold mb-3">Payment Categories</h4>
          <div class="table-responsive">
            <table class="table table-hover align-middle">
              <thead><tr><th>Name</th><th>Amount</th><th>Session</th><th>Status</th><th>Created</th><th>Actions</th></tr></thead>
              <tbody>
                <?php foreach ($paymentCategories as $category): ?>
                  <tr>
                    <td><div class="fw-semibold"><?= e($category['title'] ?? $category['category'] ?? 'Payment') ?></div><div class="small text-secondary"><?= e($category['description'] ?? '') ?></div></td>
                    <td>₦<?= e($category['amount'] ?? 0) ?></td>
                    <td><?= e($category['academic_session'] ?? '') ?></td>
                    <td><span class="badge-soft"><?= e($category['status'] ?? 'active') ?></span></td>
                    <td><?= e($category['created_at'] ?? '') ?></td>
                    <td>
                      <details class="d-inline-block">
                        <summary class="text-success" style="cursor:pointer">Edit</summary>
                        <form method="post" class="mt-2">
                          <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                          <input type="hidden" name="action" value="update_category">
                          <input type="hidden" name="category_id" value="<?= (int)$category['id'] ?>">
                          <div class="mb-2"><input type="text" name="title" class="form-control form-control-sm" value="<?= e($category['title'] ?? '') ?>"></div>
                          <div class="mb-2"><input type="number" name="amount" class="form-control form-control-sm" value="<?= e($category['amount'] ?? 0) ?>" step="0.01"></div>
                          <div class="mb-2"><input type="text" name="academic_session" class="form-control form-control-sm" value="<?= e($category['academic_session'] ?? '') ?>" placeholder="Session"></div>
                          <div class="mb-2"><select name="status" class="form-select form-select-sm"><option value="active" <?= ($category['status'] ?? '') === 'active' ? 'selected' : '' ?>>Active</option><option value="inactive" <?= ($category['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option></select></div>
                          <div class="mb-2"><textarea name="description" class="form-control form-control-sm" rows="2"><?= e($category['description'] ?? '') ?></textarea></div>
                          <button class="btn btn-success btn-sm">Save</button>
                        </form>
                      </details>
                      <form method="post" class="d-inline ms-2">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="activate_category">
                        <input type="hidden" name="category_id" value="<?= (int)$category['id'] ?>">
                        <button class="btn btn-outline-success btn-sm">Activate</button>
                      </form>
                      <form method="post" class="d-inline ms-2">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="deactivate_category">
                        <input type="hidden" name="category_id" value="<?= (int)$category['id'] ?>">
                        <button class="btn btn-outline-warning btn-sm">Deactivate</button>
                      </form>
                      <form method="post" class="d-inline ms-2">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="delete_category">
                        <input type="hidden" name="category_id" value="<?= (int)$category['id'] ?>">
                        <button class="btn btn-outline-danger btn-sm">Delete</button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>

        <div class="glass-card p-4" data-aos="fade-up">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
              <h4 class="fw-bold mb-1">Submitted Payments</h4>
              <p class="text-secondary mb-0">Filter by student, session, payment type, or payment status.</p>
            </div>
            <span class="badge-soft"><?= count($payments) ?> items</span>
          </div>
          <form method="get" class="row g-2 mb-3">
            <div class="col-md-2"><input type="text" class="form-control" name="q" value="<?= e($filters['search']) ?>" placeholder="Search"></div>
            <div class="col-md-2"><select name="academic_session" class="form-select"><option value="">All Sessions</option><?php foreach ($sessionOptions as $option): ?><option value="<?= e($option) ?>" <?= $filters['session'] === $option ? 'selected' : '' ?>><?= e($option) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-2"><select name="payment_type" class="form-select"><option value="">All Types</option><?php foreach ($paymentTypes as $option): ?><option value="<?= e($option) ?>" <?= $filters['payment_type'] === $option ? 'selected' : '' ?>><?= e($option) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-2"><select name="status" class="form-select"><option value="">All Status</option><option value="pending" <?= $filters['status'] === 'pending' ? 'selected' : '' ?>>Pending</option><option value="verified" <?= $filters['status'] === 'verified' ? 'selected' : '' ?>>Verified</option><option value="rejected" <?= $filters['status'] === 'rejected' ? 'selected' : '' ?>>Rejected</option></select></div>
            <div class="col-md-2"><input type="text" class="form-control" name="student_name" value="<?= e($filters['student_name']) ?>" placeholder="Student name"></div>
            <div class="col-md-2"><button class="btn btn-success w-100">Filter</button></div>
            <div class="col-md-2"><input type="date" class="form-control" name="date_from" value="<?= e($filters['date_from']) ?>"></div>
            <div class="col-md-2"><input type="date" class="form-control" name="date_to" value="<?= e($filters['date_to']) ?>"></div>
            <div class="col-md-2"><a href="<?= APP_URL ?>/admin/payments.php" class="btn btn-outline-secondary w-100">Reset</a></div>
          </form>
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 payment-table">
              <thead>
                <tr><th>Student</th><th>Payment Type</th><th>Amount</th><th>Session</th><th>Reference</th><th>Receipt</th><th>Date</th><th>Status</th><th>Actions</th></tr>
              </thead>
              <tbody>
                <?php foreach ($payments as $payment): ?>
                  <tr>
                    <td><div class="fw-semibold"><?= e($payment['full_name'] ?? 'N/A') ?></div><div class="small text-secondary"><?= e($payment['email'] ?? '') ?></div></td>
                    <td><?= e($payment['payment_type'] ?? 'N/A') ?></td>
                    <td>₦<?= e($payment['amount']) ?></td>
                    <td><?= e($payment['session'] ?? '') ?></td>
                    <td><?= e($payment['payment_reference'] ?? '') ?></td>
                    <td><?php if (!empty($payment['payment_proof'])): ?><button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#receiptModal" data-receipt-path="<?= e(resolveReceiptPath($payment['payment_proof'])) ?>" data-payment-type="<?= e($payment['payment_type'] ?? '') ?>" data-reference="<?= e($payment['payment_reference'] ?? '') ?>" data-status="<?= e($payment['status'] ?? 'pending') ?>" data-payment-id="<?= (int)$payment['id'] ?>">View Receipt</button><?php else: ?>—<?php endif; ?></td>
                    <td><?= e($payment['created_at']) ?></td>
                    <td><span class="badge-soft"><?= e($payment['status'] ?? 'pending') ?></span></td>
                    <td>
                      <div class="d-flex gap-2 flex-wrap">
                        <?php if (($payment['status'] ?? 'pending') !== 'verified'): ?>
                          <form method="post" class="d-inline">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="action" value="verify_payment">
                            <input type="hidden" name="payment_id" value="<?= (int)$payment['id'] ?>">
                            <button class="btn btn-outline-success btn-sm">Verify</button>
                          </form>
                        <?php endif; ?>
                        <details class="d-inline-block">
                          <summary class="text-warning" style="cursor:pointer">Reject</summary>
                          <form method="post" class="mt-2">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="action" value="reject_payment">
                            <input type="hidden" name="payment_id" value="<?= (int)$payment['id'] ?>">
                            <textarea name="rejection_reason" class="form-control form-control-sm mb-2" rows="2" placeholder="Reason for rejection"></textarea>
                            <button class="btn btn-warning btn-sm">Save</button>
                          </form>
                        </details>
                        <details class="d-inline-block">
                          <summary class="text-success" style="cursor:pointer">Edit</summary>
                          <form method="post" class="mt-2">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="action" value="update_payment">
                            <input type="hidden" name="payment_id" value="<?= (int)$payment['id'] ?>">
                            <div class="mb-2"><input type="text" name="payment_type" class="form-control form-control-sm" value="<?= e($payment['payment_type'] ?? '') ?>"></div>
                            <div class="mb-2"><input type="number" name="amount" class="form-control form-control-sm" value="<?= e($payment['amount'] ?? 0) ?>" step="0.01"></div>
                            <div class="mb-2"><input type="text" name="payment_reference" class="form-control form-control-sm" value="<?= e($payment['payment_reference'] ?? '') ?>" placeholder="Reference"></div>
                            <div class="mb-2"><select name="status" class="form-select form-select-sm"><option value="pending" <?= ($payment['status'] ?? '') === 'pending' ? 'selected' : '' ?>>Pending</option><option value="verified" <?= ($payment['status'] ?? '') === 'verified' ? 'selected' : '' ?>>Verified</option><option value="rejected" <?= ($payment['status'] ?? '') === 'rejected' ? 'selected' : '' ?>>Rejected</option></select></div>
                            <div class="mb-2"><input type="text" name="session" class="form-control form-control-sm" value="<?= e($payment['session'] ?? '') ?>" placeholder="Session"></div>
                            <button class="btn btn-success btn-sm">Save</button>
                          </form>
                        </details>
                        <form method="post" class="d-inline">
                          <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                          <input type="hidden" name="action" value="delete_payment">
                          <input type="hidden" name="payment_id" value="<?= (int)$payment['id'] ?>">
                          <button class="btn btn-outline-danger btn-sm">Delete</button>
                        </form>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </section>
</div>
<div class="modal fade" id="receiptModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Receipt Preview</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body text-center">
        <div id="receiptPreviewContent" class="py-3"></div>
      </div>
      <div class="modal-footer">
        <a id="receiptDownloadLink" href="#" class="btn btn-outline-secondary" target="_blank" rel="noopener noreferrer">Download</a>
        <a id="receiptOpenLink" href="#" class="btn btn-outline-primary" target="_blank" rel="noopener noreferrer">Open in New Tab</a>
        <form method="post" class="d-inline" id="verifyReceiptForm">
          <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="action" value="verify_payment">
          <input type="hidden" name="payment_id" id="receiptVerifyPaymentId" value="0">
          <button class="btn btn-success">Verify</button>
        </form>
        <form method="post" class="d-inline" id="rejectReceiptForm">
          <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="action" value="reject_payment">
          <input type="hidden" name="payment_id" id="receiptRejectPaymentId" value="0">
          <input type="hidden" name="rejection_reason" id="receiptRejectionReason" value="Receipt requires clarification.">
          <button class="btn btn-warning">Reject</button>
        </form>
      </div>
    </div>
  </div>
</div>
<script>
  document.addEventListener('DOMContentLoaded', function () {
    const modal = document.getElementById('receiptModal');
    if (!modal) return;
    modal.addEventListener('show.bs.modal', function (event) {
      const trigger = event.relatedTarget;
      const path = trigger?.getAttribute('data-receipt-path') || '';
      const paymentType = trigger?.getAttribute('data-payment-type') || 'Receipt';
      const reference = trigger?.getAttribute('data-reference') || '';
      const preview = document.getElementById('receiptPreviewContent');
      const downloadLink = document.getElementById('receiptDownloadLink');
      const openLink = document.getElementById('receiptOpenLink');
      const verifyId = document.getElementById('receiptVerifyPaymentId');
      const rejectId = document.getElementById('receiptRejectPaymentId');
      if (!preview || !downloadLink || !openLink) return;
      if (path) {
        const lower = path.toLowerCase();
        if (lower.endsWith('.pdf')) {
          preview.innerHTML = '<iframe src="' + path + '" style="width:100%;min-height:70vh;border:0;"></iframe>';
        } else {
          preview.innerHTML = '<img src="' + path + '" alt="Receipt preview" style="max-width:100%;max-height:70vh;border-radius:12px;">';
        }
      } else {
        preview.innerHTML = '<div class="text-secondary">No receipt attached.</div>';
      }
      downloadLink.href = path;
      openLink.href = path;
      if (verifyId) verifyId.value = trigger?.getAttribute('data-payment-id') || '0';
      if (rejectId) rejectId.value = trigger?.getAttribute('data-payment-id') || '0';
      modal.querySelector('.modal-title').textContent = paymentType + ' • ' + reference;
    });
  });
</script>
<?php if ($flashMessage): ?>
<script>
  document.addEventListener('DOMContentLoaded', function () {
    if (window.Swal) {
      Swal.fire({
        title: '<?= $flashType === 'error' ? 'Action failed' : 'Success' ?>',
        text: '<?= e($flashMessage) ?>',
        icon: '<?= $flashType === 'error' ? 'error' : 'success' ?>',
        confirmButtonColor: '#14532d'
      });
    }
  });
</script>
<?php endif; ?>
<?php require_once dirname(__DIR__) . '/templates/footer.php'; ?>
