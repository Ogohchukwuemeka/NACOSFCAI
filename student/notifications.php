<?php require_once dirname(__DIR__) . '/templates/header.php'; ?>
<?php
$auth = new Auth(Database::getInstance());
$auth->requireLogin('student');
$user = $auth->currentUser();
$portal = new Portal(Database::getInstance());
$notifications = $portal->getStudentNotifications((int)($user['id'] ?? 0));
?>
<div class="container py-5">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h4 class="fw-bold mb-1">Notifications</h4>
      <p class="text-secondary mb-0">Stay up to date with portal updates.</p>
    </div>
  </div>
  <div class="card shadow-sm border-0 rounded-4 p-4">
    <h4 class="fw-bold">Notifications</h4>
    <div class="list-group mt-3">
      <?php foreach ($notifications as $notification): ?>
        <div class="list-group-item">
          <div class="d-flex justify-content-between align-items-start gap-3">
            <div>
              <h6 class="fw-bold mb-1"><?= e($notification['title'] ?? 'Notification') ?></h6>
              <p class="mb-0 text-secondary"><?= e($notification['message'] ?? '') ?></p>
            </div>
            <span class="text-muted small"><?= e($notification['created_at'] ?? '') ?></span>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php require_once dirname(__DIR__) . '/templates/footer.php'; ?>
