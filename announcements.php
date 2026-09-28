<?php require_once __DIR__ . '/templates/header.php'; ?>
<?php $portal = new Portal(Database::getInstance()); $announcements = $portal->getAnnouncementsList(20); ?>
<div class="container py-5">
  <?php if (($_SESSION['user_role'] ?? '') === 'student'): ?>
    <div class="mb-3">
      <a href="<?= APP_URL ?>/student/dashboard.php" class="btn btn-outline-success btn-sm"><i data-lucide="arrow-left"></i> Back to Student Dashboard</a>
    </div>
  <?php endif; ?>
  <div class="text-center mb-4" data-aos="fade-up">
    <h1 class="display-6 fw-bold text-primary">Announcements</h1>
    <p class="text-secondary">The latest updates from the department.</p>
  </div>
  <div class="row g-4">
    <?php foreach ($announcements as $item): ?>
      <div class="col-12">
        <div class="card shadow-sm border-0 rounded-4 p-4" data-aos="fade-up">
          <div class="d-flex justify-content-between align-items-start gap-3">
            <div>
              <h5 class="fw-bold mb-2"><?= e($item['title']) ?></h5>
              <p class="text-secondary mb-0"><?= e($item['body']) ?></p>
            </div>
            <span class="badge bg-primary-subtle text-primary"><?= e($item['category']) ?></span>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php require_once __DIR__ . '/templates/footer.php'; ?>
