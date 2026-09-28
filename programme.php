<?php require_once __DIR__ . '/templates/header.php'; ?>
<?php $portal = new Portal(Database::getInstance()); $slug = $_GET['slug'] ?? ''; $programme = $portal->getProgramme($slug); ?>
<div class="container py-5">
  <?php if ($programme): ?>
    <div class="hero-card rounded-4 p-4 p-lg-5 shadow-lg" data-aos="fade-up">
      <h1 class="display-6 fw-bold text-primary"><?= e($programme['title']) ?></h1>
      <p class="lead text-secondary"><?= e($programme['description']) ?></p>
      <div class="row g-4 mt-2">
        <div class="col-md-6">
          <div class="card shadow-sm border-0 rounded-4 p-4">
            <h5 class="fw-bold">Career Opportunities</h5>
            <p class="text-secondary">Graduates can pursue paths in software engineering, AI development, systems analysis, product development, and digital transformation.</p>
          </div>
        </div>
        <div class="col-md-6">
          <div class="card shadow-sm border-0 rounded-4 p-4">
            <h5 class="fw-bold">Skills Students Acquire</h5>
            <p class="text-secondary">Programming, system design, teamwork, project delivery, data handling, and modern digital problem solving.</p>
          </div>
        </div>
      </div>
    </div>
  <?php else: ?>
    <div class="alert alert-warning">Programme not found.</div>
  <?php endif; ?>
</div>
<?php require_once __DIR__ . '/templates/footer.php'; ?>
