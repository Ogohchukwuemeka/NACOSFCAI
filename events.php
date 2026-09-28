<?php require_once __DIR__ . '/templates/header.php'; ?>
<?php $portal = new Portal(Database::getInstance()); $events = $portal->listEvents(12, 0); ?>
<div class="container py-5">
  <div class="text-center mb-4" data-aos="fade-up">
    <h1 class="display-6 fw-bold text-primary">Events</h1>
    <p class="text-secondary">Stay connected with upcoming and previous departmental events.</p>
  </div>
  <div class="row g-4">
    <?php foreach ($events as $event): ?>
      <div class="col-lg-6">
        <div class="card shadow-sm border-0 rounded-4 h-100 card-hover" data-aos="fade-up">
          <div class="card-body">
            <h4 class="fw-bold"><?= e($event['title']) ?></h4>
            <p class="text-secondary mb-2"><?= e($event['description']) ?></p>
            <p class="mb-1"><strong>Date:</strong> <?= e($event['event_date']) ?></p>
            <p class="mb-0"><strong>Venue:</strong> <?= e($event['venue']) ?></p>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php require_once __DIR__ . '/templates/footer.php'; ?>
