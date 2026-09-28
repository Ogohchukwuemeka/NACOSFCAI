<?php require_once __DIR__ . '/templates/header.php'; ?>
<?php $portal = new Portal(Database::getInstance()); $items = $portal->listGalleryItems(); ?>
<?php
$categories = ['All', 'Matriculation', 'Workshops', 'Seminars', 'Tech Events', 'Coding Competitions', 'Student Projects', 'Videos'];
?>
<main class="gallery-page">
  <section class="gallery-hero">
    <div class="container py-5">
      <div class="row align-items-center g-4">
        <div class="col-lg-7" data-aos="fade-up">
          <div class="hero-badge"><i data-lucide="images"></i> Multimedia Gallery</div>
          <h1 class="display-5 fw-bold mt-3 mb-3">NACOSFCAI Gallery</h1>
          <p class="lead mb-4">A modern showcase of NACOSFCAI memories, events, academic milestones, and student achievements.</p>
          <div class="gallery-marquee" aria-label="Featured highlights">
            <div class="gallery-marquee-track">
              <span>Live campus moments</span>
              <span>Student achievements</span>
              <span>Academic excellence</span>
              <span>Departmental milestones</span>
              <span>Live campus moments</span>
              <span>Student achievements</span>
              <span>Academic excellence</span>
              <span>Departmental milestones</span>
            </div>
          </div>
        </div>
        <div class="col-lg-5" data-aos="zoom-in" data-aos-delay="100">
          <div class="gallery-hero-panel glass-card">
            <div class="hero-ring"></div>
            <div class="hero-ring hero-ring-alt"></div>
            <div class="hero-visual-copy">
              <div class="eyebrow">NACOSFCAI  Media</div>
              <h3 class="mt-2 mb-3">Preserving every memorable moment in a premium visual experience.</h3>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section class="container py-4" data-aos="fade-up">
    <div class="gallery-slider glass-card overflow-hidden">
      <div class="gallery-slider-track">
        <?php foreach ($items as $index => $item): ?>
          <?php $mediaType = detectMediaType($item['image_path']); $mediaUrl = asset($item['image_path']); ?>
          <div class="gallery-slide<?= $index === 0 ? ' active' : '' ?>" data-media-url="<?= e($mediaUrl) ?>" data-media-type="<?= e($mediaType) ?>">
            <?php if ($mediaType === 'video'): ?>
              <video muted playsinline loop preload="metadata" src="<?= e($mediaUrl) ?>"></video>
            <?php else: ?>
              <img src="<?= e($mediaUrl) ?>" alt="<?= e($item['title']) ?>">
            <?php endif; ?>
            <div class="gallery-slide-caption">
              <span class="eyebrow">Featured</span>
              <h3><?= e($item['title'] ?: 'Gallery Item') ?></h3>
              <p><?= e($item['description'] ?: 'A highlighted gallery entry.') ?></p>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
      <button class="slider-control prev" type="button" aria-label="Previous slide"><i data-lucide="chevron-left"></i></button>
      <button class="slider-control next" type="button" aria-label="Next slide"><i data-lucide="chevron-right"></i></button>
      <div class="slider-dots">
        <?php foreach ($items as $index => $item): ?>
          <button class="slider-dot<?= $index === 0 ? ' active' : '' ?>" type="button" data-index="<?= $index ?>"></button>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <section class="container py-4">
    <div class="gallery-toolbar glass-card p-3 mb-4" data-aos="fade-up">
      <div class="d-flex flex-wrap gap-2 justify-content-center">
        <?php foreach ($categories as $category): ?>
          <button class="gallery-filter-chip<?= $category === 'All' ? ' active' : '' ?>" type="button" data-category="<?= e($category) ?>"><?= e($category) ?></button>
        <?php endforeach; ?>
      </div>
    </div>

    <?php if (empty($items)): ?>
      <div class="empty-state glass-card text-center" data-aos="fade-up">
        <i data-lucide="image-plus"></i>
        <h3>No gallery items are available yet.</h3>
        <p>Check back soon for photos and videos from departmental activities.</p>
      </div>
    <?php else: ?>
      <div class="gallery-grid">
        <?php foreach ($items as $item): ?>
          <?php $mediaType = detectMediaType($item['image_path']); $category = $item['category'] ?: 'Others'; $mediaUrl = asset($item['image_path']); ?>
          <article class="gallery-card glass-card" data-aos="fade-up" data-category="<?= e($category) ?>" data-media-type="<?= e($mediaType) ?>" data-media-url="<?= e($mediaUrl) ?>">
            <div class="gallery-card-media">
              <?php if ($mediaType === 'video'): ?>
                <video preload="metadata" muted playsinline loop src="<?= e($mediaUrl) ?>"></video>
              <?php else: ?>
                <img src="<?= e($mediaUrl) ?>" alt="<?= e($item['title']) ?>">
              <?php endif; ?>
              <div class="gallery-card-overlay">
                <span class="gallery-media-badge"><i data-lucide="<?= $mediaType === 'video' ? 'play-circle' : 'image' ?>"></i><?= $mediaType === 'video' ? 'Video' : 'Photo' ?></span>
                <span class="gallery-open-badge"><i data-lucide="maximize-2"></i></span>
              </div>
            </div>
            <div class="gallery-card-body">
              <div class="d-flex justify-content-between align-items-start gap-2">
                <h6 class="fw-bold mb-1"><?= e($item['title']) ?></h6>
                <span class="gallery-category-label"><?= e($category) ?></span>
              </div>
              <p class="text-secondary small mb-0"><?= e($item['description'] ?? 'Departmental highlight') ?></p>
              <span class="gallery-card-date"><i data-lucide="calendar-days"></i><?= e(date('M j, Y', strtotime($item['created_at'] ?? 'now'))) ?></span>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <section class="container pb-5">
    <div class="cta-card glass-card" data-aos="fade-up">
      <div class="row align-items-center g-4">
        <div class="col-lg-8">
          <div class="eyebrow">Create Memories</div>
          <h3 class="mt-3 mb-2">Create Memories with NACOSFCAI</h3>
          <p class="mb-0">Join workshops, seminars, competitions, and departmental activities that shape your academic journey.</p>
        </div>
        <div class="col-lg-4 d-flex flex-wrap gap-3 justify-content-lg-end">
          <a href="<?= APP_URL ?>/auth/register.php" class="btn btn-light fw-semibold px-4">Register</a>
          <a href="<?= APP_URL ?>/events.php" class="btn btn-outline-light fw-semibold px-4">View Events</a>
        </div>
      </div>
    </div>
  </section>
</main>
<div class="gallery-lightbox" id="gallery-lightbox" hidden>
  <button class="gallery-lightbox-close" type="button" aria-label="Close gallery view" onclick="this.closest('.gallery-lightbox').hidden=true; this.closest('.gallery-lightbox').style.display='none'; document.body.style.overflow='';"><i data-lucide="x"></i></button>
  <div class="gallery-lightbox-shell">
    <div class="gallery-lightbox-nav-wrap">
      <button class="gallery-lightbox-nav prev" type="button" aria-label="Previous image"><i data-lucide="chevron-left"></i></button>
      <button class="gallery-lightbox-nav next" type="button" aria-label="Next image"><i data-lucide="chevron-right"></i></button>
    </div>
    <div class="gallery-lightbox-media"></div>
    <div class="gallery-lightbox-caption"></div>
  </div>
</div>
<?php require_once __DIR__ . '/templates/footer.php'; ?>
