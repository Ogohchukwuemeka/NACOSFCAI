<?php require_once __DIR__ . '/templates/header.php'; ?>
<?php $portal = new Portal(Database::getInstance()); $executives = $portal->listExecutives(); $executiveTenures = $portal->listExecutiveTenures(); ?>
<?php
$executiveImageMap = [
    'president' => 'images/President.jpeg',
    'vice president' => 'images/vp.jpeg',
    'vice-president' => 'images/vp.jpeg',
    'general secretary' => 'images/ssg.jpeg',
    'secretary' => 'images/ssg.jpeg',
    'welfare' => 'images/welfare.jpeg',
    'social' => 'images/social.jpeg',
    'financial secretary' => 'images/fin sec.jpeg',
    'financial' => 'images/fin sec.jpeg',
    'publicity' => 'images/pro.jpeg',
    'publicity secretary' => 'images/pro.jpeg',
    'assistant' => 'images/AI.png',
    'chaplin' => 'images/chaplin.jpeg',
    'alfred' => 'images/alfred.jpeg',
    'mathias' => 'images/mathias.jpeg',
    'ify' => 'images/ify.jpeg',
    'annas' => 'images/annas.jpeg',
    'chomzzy' => 'images/chomzzy.jpeg',
];
$executiveImageFallbacks = [
    'images/President.jpeg',
    'images/vp.jpeg',
    'images/ssg.jpeg',
    'images/social.jpeg',
    'images/welfare.jpeg',
    'images/pro.jpeg',
    'images/fin sec.jpeg',
    'images/chaplin.jpeg',
    'images/annas.jpeg',
    'images/alfred.jpeg',
    'images/mathias.jpeg',
    'images/ify.jpeg',
    'images/chomzzy.jpeg',
    'images/AI.png',
];
$executiveCounter = 0;
?>
<div class="executives-page">
  <section class="executives-hero position-relative overflow-hidden">
    <div class="hero-glow hero-glow-one"></div>
    <div class="hero-glow hero-glow-two"></div>
    <div class="container py-5 position-relative">
      <nav aria-label="breadcrumb" class="mb-4" data-aos="fade-right">
        <ol class="breadcrumb mb-0">
          <li class="breadcrumb-item"><a href="<?= APP_URL ?>/index.php">Home</a></li>
          <li class="breadcrumb-item active" aria-current="page">Executives</li>
        </ol>
      </nav>
      <div class="row align-items-center g-4">
        <div class="col-lg-7" data-aos="fade-up">
          <div class="hero-badge"><i data-lucide="sparkles"></i> Leadership Council</div>
          <h1 class="display-5 fw-bold mt-3 mb-3">Meet Our Departmental Executives</h1>
          <p class="lead mb-4">The executive council steers student engagement, academic support, and departmental growth with professionalism, vision, and dedication.</p>
          <div class="hero-pills">
            <span><i data-lucide="shield-check"></i> Student-led leadership</span>
            <span><i data-lucide="graduation-cap"></i> Academic excellence</span>
            <span><i data-lucide="heart-handshake"></i> Community impact</span>
          </div>
        </div>
        <div class="col-lg-5" data-aos="zoom-in" data-aos-delay="100">
          <div class="executives-hero-visual glass-card">
            <div class="hero-ring"></div>
            <div class="hero-ring hero-ring-alt"></div>
            <div class="hero-floating-card top-pill"><i data-lucide="users"></i> 13 dedicated leaders</div>
            <div class="hero-floating-card bottom-pill"><i data-lucide="trending-up"></i> Premium service</div>
            <div class="hero-visual-copy">
              <div class="eyebrow">NACOSFCAIshiagu Leadership</div>
              <h3 class="mt-2 mb-3">A polished showcase of the people guiding the department.</h3>
              <p class="mb-0 text-secondary">Every role reflects responsibility, unity, and a commitment to excellence.</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section class="py-5">
    <div class="container">
      <?php if (count($executiveTenures) > 1): ?>
        <div class="executive-tenure-tabs d-flex flex-wrap gap-2 mb-4" role="tablist" aria-label="Executive tenures">
          <?php foreach ($executiveTenures as $tenure): ?>
            <a class="btn btn-outline-success" href="#tenure-<?= (int)$tenure['id'] ?>"><?= e($tenure['name']) ?></a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      <div class="section-heading mb-4" data-aos="fade-up">
        <div class="eyebrow">Executive Showcase</div>
        <h2 class="mt-2">A refined leadership gallery for the NACOSFCAIshiagu community</h2>
        <p class="mt-3">Each executive card highlights the personality, role, and presence behind the department’s success.</p>
      </div>
      <?php foreach ($executiveTenures as $tenure): ?>
      <section id="tenure-<?= (int)$tenure['id'] ?>" class="mb-5">
        <h3 class="h4 mb-3"><?= e($tenure['name']) ?> Executive</h3>
      <div class="row g-4">
        <?php foreach ($executives as $executive): ?>
          <?php if ((int)($executive['tenure_id'] ?? 0) !== (int)$tenure['id']) continue; ?>
          <?php
            $executiveName = strtolower(trim($executive['name']));
            $executivePosition = strtolower(trim($executive['position']));
            $imagePath = null;
            if (!empty($executive['passport_path'])) {
                $candidate = ltrim($executive['passport_path'], '/');
                $diskPath = __DIR__ . '/assets/' . $candidate;
                if (file_exists($diskPath)) {
                    $imagePath = asset($candidate);
                }
            }

            if ($imagePath === null) {
                $imagePath = asset($executiveImageFallbacks[$executiveCounter % count($executiveImageFallbacks)]);
                foreach ($executiveImageMap as $needle => $path) {
                    if (strpos($executiveName, $needle) !== false || strpos($executivePosition, $needle) !== false) {
                        $imagePath = asset($path);
                        break;
                    }
                }
            }

            $isPresident = strpos($executivePosition, 'president') !== false;
            $executiveCounter++;
          ?>
          <div class="col-sm-6 col-lg-4">
            <article class="executive-card glass-card h-100" data-aos="fade-up" data-aos-delay="<?= 70 + ($executiveCounter * 35) % 220 ?>">
              <div class="executive-image-wrap">
                <img src="<?= e($imagePath) ?>" alt="<?= e($executive['name']) ?>" class="executive-photo">
              </div>
              <div class="executive-content">
                <div class="position-badge"><i data-lucide="award"></i><span><?= e($executive['position']) ?></span></div>
                <h3 class="executive-name mb-0"><?= e($executive['name']) ?></h3>
                <?php if ($isPresident): ?>
                  
                <?php endif; ?>
              </div>
            </article>
          </div>
        <?php endforeach; ?>
      </div>
      </section>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="pb-5">
    <div class="container">
      <div class="cta-card glass-card" data-aos="fade-up">
        <div class="row align-items-center g-4">
          <div class="col-lg-8">
            <div class="eyebrow">Join the Movement</div>
            <h3 class="mt-3 mb-2">Be part of the next chapter of NACOS</h3>
            <p class="mb-0">Take your place in the department’s vibrant community, connect with peers, and grow through leadership and opportunity.</p>
          </div>
          <div class="col-lg-4 d-flex flex-wrap gap-3 justify-content-lg-end">
            <a href="<?= APP_URL ?>/auth/register.php" class="btn btn-light fw-semibold px-4">Register</a>
            <a href="<?= APP_URL ?>/auth/login.php" class="btn btn-outline-light fw-semibold px-4">Login</a>
          </div>
        </div>
      </div>
    </div>
  </section>
</div>
<?php require_once __DIR__ . '/templates/footer.php'; ?>
