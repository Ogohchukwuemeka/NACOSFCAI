<?php require_once __DIR__ . '/templates/header.php'; ?>
<?php $portal = new Portal(Database::getInstance()); ?>
<div class="container py-5">
  <div class="row g-4 align-items-center">
    <div class="col-lg-7">
      <div class="hero-card rounded-4 p-4 p-lg-5 shadow-lg" data-aos="fade-right">
        <h1 class="display-6 fw-bold text-primary">About NACOSFCAI</h1>
        <p class="lead text-secondary">The Computer Science Department provides a vibrant learning environment where innovation, teamwork, and technical excellence are central to every activity.</p>
        <p class="text-secondary">Through NACOS FCAI, we create opportunities for academic support, professional growth, networking, and community development for all students and members.</p>
      </div>
    </div>
    <div class="col-lg-5">
      <div class="card shadow-lg border-0 rounded-4 p-4" data-aos="fade-left">
        <h5 class="fw-bold">Vision</h5>
        <p class="text-secondary">To become a leading academic community that develops innovative, ethical, and globally competitive computing professionals.</p>
      </div>
    </div>
  </div>
  <div class="row g-4 mt-2">
    <div class="col-md-6">
      <div class="card shadow-sm border-0 rounded-4 p-4" data-aos="fade-up">
        <h4 class="fw-bold">Mission</h4>
        <p class="text-secondary">To foster academic excellence, professional development, and social impact through collaboration, resources, and technology-driven departmental engagement.</p>
      </div>
    </div>
    <div class="col-md-6">
      <div class="card shadow-sm border-0 rounded-4 p-4" data-aos="fade-up" data-aos-delay="100">
        <h4 class="fw-bold">What we offer</h4>
        <ul class="text-secondary mb-0">
          <li>Professional student engagement</li>
          <li>Payments and membership support</li>
          <li>Resources and academic progression</li>
          <li>Events, gallery, and announcements</li>
        </ul>
      </div>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/templates/footer.php'; ?>
