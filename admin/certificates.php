<?php require_once dirname(__DIR__) . '/templates/header.php'; ?>
<?php
$auth = new Auth(Database::getInstance());
$auth->requireLogin('admin');
$certificate = new Certificate(Database::getInstance());
$certificates = $certificate->listAllCertificates();
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
            <h4 class="fw-bold mb-1">Certificates</h4>
            <p class="text-secondary mb-0">View certificates generated for students and download PDFs.</p>
          </div>
          <span class="badge-soft"><?= count($certificates) ?> records</span>
        </div>

        <?php if (empty($certificates)): ?>
          <div class="text-center py-5">
            <i data-lucide="inbox" style="font-size: 48px; color: #999;"></i>
            <p class="text-secondary mt-3">No certificates have been generated yet.</p>
          </div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead>
                <tr>
                  <th>Student</th>
                  <th>Matric</th>
                  <th>Email</th>
                  <th>Event</th>
                  <th>Certificate #</th>
                  <th>Status</th>
                  <th>Generated</th>
                  <th class="text-end">Action</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($certificates as $cert): ?>
                  <?php $pdfPath = !empty($cert['pdf_path']) ? APP_ROOT . '/' . ltrim($cert['pdf_path'], '/') : null; ?>
                  <tr>
                    <td>
                      <strong><?= e((string)($cert['full_name'] ?? '—')) ?></strong><br>
                      <small class="text-secondary"><?= e((string)($cert['email'] ?? '—')) ?></small>
                    </td>
                    <td><code><?= e((string)($cert['matric_number'] ?? '—')) ?></code></td>
                    <td><?= e((string)($cert['email'] ?? '—')) ?></td>
                    <td><?= e((string)($cert['event_title'] ?? '—')) ?></td>
                    <td><?= e((string)($cert['certificate_number'] ?? '—')) ?></td>
                    <td>
                      <span class="badge bg-<?= in_array(strtolower((string)$cert['status']), ['generated', 'sent', 'viewed'], true) ? 'success' : (strtolower((string)$cert['status']) === 'pending' ? 'warning' : 'secondary') ?>">
                        <?= e((string)($cert['status'] ?? 'pending')) ?>
                      </span>
                    </td>
                    <td><small><?= e((string)($cert['generated_at'] ?? $cert['created_at'] ?? '—')) ?></small></td>
                    <td class="text-end">
                      <?php if ($pdfPath && file_exists($pdfPath)): ?>
                        <a href="<?= APP_URL ?>/admin/certificate-download.php?id=<?= (int)$cert['id'] ?>" class="btn btn-outline-primary btn-sm">Download</a>
                      <?php else: ?>
                        <span class="badge bg-warning">Missing PDF</span>
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
