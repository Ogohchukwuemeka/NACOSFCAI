<?php require_once dirname(__DIR__) . '/templates/header.php'; ?>
<?php
$auth = new Auth(Database::getInstance());
$auth->requireLogin('admin');
$pdo = Database::getInstance()->getConnection();

$payments = $pdo->query(
    "SELECT p.*, u.full_name, u.email, COALESCE(u.matric_number, s.registration_number, 'N/A') AS matric_number
     FROM payments p
     LEFT JOIN users u ON u.id = p.user_id
     LEFT JOIN students s ON s.user_id = u.id
     WHERE LOWER(p.payment_type) LIKE '%id card%'
     ORDER BY p.created_at DESC"
)->fetchAll();
?>
<div class="container py-5">
  <div class="row g-4 align-items-start">
    <div class="col-lg-3">
      <?php require_once dirname(__DIR__) . '/templates/admin-sidebar.php'; ?>
    </div>
    <div class="col-lg-9">
      <div class="glass-card p-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <div>
            <h4 class="fw-bold mb-1">ID Card Requests</h4>
            <p class="text-secondary mb-0">Review student ID card payments and verification status.</p>
          </div>
          <span class="badge-soft"><?= count($payments) ?> records</span>
        </div>

        <?php if (empty($payments)): ?>
          <div class="text-center py-5">
            <i data-lucide="inbox" style="font-size: 48px; color: #999;"></i>
            <p class="text-secondary mt-3">No ID card payments found.</p>
          </div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead>
                <tr>
                  <th>Student</th>
                  <th>Matric</th>
                  <th>Session</th>
                  <th>Amount</th>
                  <th>Status</th>
                  <th>Reference</th>
                  <th>Submitted</th>
                  <th class="text-end">Action</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($payments as $payment): ?>
                  <?php $canDownload = in_array(strtolower((string)$payment['status']), ['approved', 'verified'], true); ?>
                  <tr>
                    <td>
                      <strong><?= e((string)($payment['full_name'] ?? '—')) ?></strong><br>
                      <small class="text-secondary"><?= e((string)($payment['email'] ?? '—')) ?></small>
                    </td>
                    <td><code><?= e((string)($payment['matric_number'] ?? '—')) ?></code></td>
                    <td><?= e((string)($payment['session'] ?? '—')) ?></td>
                    <td><?= formatCurrency((float)($payment['amount'] ?? 0)) ?></td>
                    <td>
                      <span class="badge bg-<?= $canDownload ? 'success' : ($payment['status'] === 'rejected' ? 'danger' : 'warning') ?>">
                        <?= e((string)($payment['status'] ?? 'pending')) ?>
                      </span>
                    </td>
                    <td><?= e((string)($payment['payment_reference'] ?? '—')) ?></td>
                    <td><small><?= date('M d, Y', strtotime((string)($payment['created_at'] ?? 'now'))) ?></small></td>
                    <td class="text-end">
                      <?php if ($canDownload): ?>
                        <a href="<?= APP_URL ?>/admin/id-card-download.php?payment_id=<?= (int)$payment['id'] ?>" class="btn btn-outline-primary btn-sm">Download</a>
                      <?php else: ?>
                        <span class="badge bg-secondary">Unavailable</span>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php require_once dirname(__DIR__) . '/templates/footer.php'; ?>