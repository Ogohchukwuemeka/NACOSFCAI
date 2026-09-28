<?php require_once dirname(__DIR__) . '/config/bootstrap.php';
$auth = new Auth(Database::getInstance());
$auth->requireLogin('admin');
$portal = new Portal(Database::getInstance());
$pdo = Database::getInstance()->getConnection();
$portal->ensureVisitorMessagesSchema();
$filters = ['status' => trim((string)($_GET['status'] ?? ''))];
$messages = $portal->listVisitorMessages($filters);
?>
<?php require_once dirname(__DIR__) . '/templates/header.php'; ?>
<div class="container py-5">
  <div class="row">
    <div class="col-lg-3">
      <?php require_once dirname(__DIR__) . '/templates/admin-sidebar.php'; ?>
    </div>
    <div class="col-lg-9">
      <a href="<?= APP_URL ?>/admin/dashboard.php" class="btn btn-outline-success btn-sm mb-3"><i data-lucide="arrow-left"></i> Back to Dashboard</a>
      <h2 class="fw-bold mb-3">Visitor Messages</h2>
      <div class="glass-card p-4 mb-4">
        <form method="get" class="row g-3 align-items-end">
          <div class="col-md-4">
            <label class="form-label small fw-semibold">Status</label>
            <select name="status" class="form-select">
              <option value="">All</option>
              <option value="new" <?= ($filters['status'] ?? '') === 'new' ? 'selected' : '' ?>>New</option>
              <option value="pending" <?= ($filters['status'] ?? '') === 'pending' ? 'selected' : '' ?>>Pending</option>
              <option value="resolved" <?= ($filters['status'] ?? '') === 'resolved' ? 'selected' : '' ?>>Resolved</option>
            </select>
          </div>
          <div class="col-md-2">
            <button class="btn btn-success">Filter</button>
          </div>
        </form>
      </div>

      <div class="glass-card p-4">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead>
              <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Email</th>
                <th>Message</th>
                <th>Status</th>
                <th>Date</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($messages as $m): ?>
                <tr>
                  <td>#<?= (int)$m['id'] ?></td>
                  <td><?= e((string)($m['name'] ?? 'Visitor')) ?></td>
                  <td><?= e((string)($m['email'] ?? '—')) ?></td>
                  <td><?= e(substr((string)$m['message'], 0, 120)) ?><?= strlen((string)$m['message']) > 120 ? '...' : '' ?></td>
                  <td><?= e((string)$m['status']) ?></td>
                  <td><?= e((string)$m['created_at']) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
<?php require_once dirname(__DIR__) . '/templates/footer.php'; ?>
