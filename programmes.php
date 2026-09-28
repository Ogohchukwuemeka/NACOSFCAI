<?php require_once __DIR__ . '/templates/header.php'; ?>
<?php $portal = new Portal(Database::getInstance()); $programmes = $portal->listProgrammes(); ?>
<div class="container py-5">
  <div class="text-center mb-4" data-aos="fade-up">
    <h1 class="display-6 fw-bold text-primary">Programmes</h1>
    <p class="text-secondary">Explore the three core academic pathways available in the department.</p>
  </div>
  <div class="row g-4">
    <?php foreach ($programmes as $programme): ?>
      <div class="col-lg-4">
        <div class="card shadow-sm border-0 rounded-4 h-100 overflow-hidden card-hover" data-aos="fade-up">
          <img src="<?= e(!empty($programme['image']) ? $programme['image'] : asset('images/nacosfcai.jpeg')) ?>" alt="<?= e($programme['title']) ?>" class="img-fluid" style="height: 220px; object-fit: cover;">
          <div class="card-body">
            <h4 class="fw-bold"><?= e($programme['title']) ?></h4>
            <p class="text-secondary"><?= e($programme['description']) ?></p>
            <a href="<?= APP_URL ?>/programme.php?slug=<?= e($programme['slug']) ?>" class="btn btn-outline-primary">Read More</a>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php require_once __DIR__ . '/templates/footer.php'; ?>
