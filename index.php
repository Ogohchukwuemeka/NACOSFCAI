<?php require_once __DIR__ . '/templates/header.php'; ?>
<?php
$pdo = Database::getInstance()->getConnection();
$portal = new Portal(Database::getInstance());
$settings = $portal->getSettings();
$dashboardStats = $portal->getDashboardStats();
$announcements = $portal->listAnnouncements(4, 0);
$events = $portal->listEvents(4, 0);
$programmes = [
    ['title' => 'Computer Science', 'description' => 'A rigorous blend of theory, systems thinking, and software innovation.', 'image' => asset('images/CSc.jpeg'), 'link' => APP_URL . '/programme.php?slug=computer-science'],
    ['title' => 'Software & Web Development', 'description' => 'Build industry-ready products with modern development practices.', 'image' => asset('images/SWD.jpeg'), 'link' => APP_URL . '/programme.php?slug=software-web-development'],
    ['title' => 'Artificial Intelligence', 'description' => 'Explore intelligent systems, automation, and responsible AI solutions.', 'image' => asset('images/AI.png'), 'link' => APP_URL . '/programme.php?slug=artificial-intelligence']
];
$executivePreview = [
    ['name' => 'Comr. Ogoh Joshua Chukwuemeka', 'role' => 'President', 'image' => asset('images/Executives/President.jpeg')],
    ['name' => 'Comr. Chinwendu Rachel', 'role' => 'Vice President', 'image' => asset('images/Executives/vp.jpeg')],
    ['name' => 'Comr. Chukwu Victory Ngozichukwuka', 'role' => 'Secretary General', 'image' => asset('images/Executives/ssg.jpeg')]
    ];

$galleryPreview = [
    ['title' => 'Matriculation', 'image' => asset('images/matric.jpeg')],
    ['title' => 'Coding Competition', 'image' => asset('images/SWD.jpeg')],
    ['title' => 'Workshops', 'image' => asset('images/AI.png')],
    ['title' => 'Student Project', 'image' => asset('images/SWD.jpeg')]
];
$resources = [
    ['level' => 'ND I', 'description' => 'Semester resources, course notes, and handbooks.', 'icon' => 'book-open'],
    ['level' => 'ND II', 'description' => 'Advanced materials for practical and academic growth.', 'icon' => 'graduation-cap'],
    ['level' => 'HND I', 'description' => 'Specialized guides for professional readiness.', 'icon' => 'rocket'],
    ['level' => 'HND II', 'description' => 'Capstone lessons, revision packs, and project support.', 'icon' => 'sparkles']
];
$faqs = [
    ['question' => 'How do I register?', 'answer' => 'Create an account through the registration link and complete the student profile before accessing the portal.'],
    ['question' => 'How do I pay NACOS dues?', 'answer' => 'Use the student dashboard to submit your payment details and reference number for verification.'],
    ['question' => 'How do I download my certificate?', 'answer' => 'Visit the student dashboard and use the certificate section once your request is approved.'],
    ['question' => 'How do I generate my ID card?', 'answer' => 'Your ID card request can be generated from your profile section upon successful verification.']
];
?>
<div class="page-shell">
    <section class="hero-section position-relative overflow-hidden">
        <div class="hero-glow hero-glow-one"></div>
        <div class="hero-glow hero-glow-two"></div>
        <div class="container hero-content py-5 py-lg-0">
            <div class="row align-items-center gy-5">
                <div class="col-lg-7" data-aos="fade-right">
                    <span class="hero-badge"><i data-lucide="sparkles" class="me-2"></i> NACOS FCAI</span>
                    <p class="hero-chapter text-white-75 mb-3">NACOSFCAI.  Towards Advanced Computing</p>
                    <h1 class="display-4 fw-bold mb-3">Empowering Students. Building the Future.</h1>
                    <p class="lead text-white-75 mb-4">Discover announcements, manage payments, explore resources, and stay connected with a modern departmental portal crafted for every student.</p>
                    <div class="d-flex flex-wrap gap-3 mb-4">
                        <a href="<?= APP_URL ?>/auth/register.php" class="btn btn-light btn-lg px-4">Become a Member</a>
                        <a href="<?= APP_URL ?>/auth/login.php" class="btn btn-outline-white btn-lg px-4">Access Dashboard</a>
                        <a href="<?= APP_URL ?>/about.php" class="btn btn-outline-white btn-lg px-4">Learn More</a>
                    </div>
                    <div class="hero-pills">
                        <span><i data-lucide="shield-check"></i> Secure access</span>
                        <span><i data-lucide="calendar-days"></i> Live updates</span>
                        <span><i data-lucide="book-open-check"></i> Rich resources</span>
                    </div>
                </div>
                <div class="col-lg-5" data-aos="fade-left">
                    <div class="hero-visual glass-panel position-relative">
                        <div class="floating-card top-card">
                            <div class="d-flex align-items-center gap-2">
                                <i data-lucide="graduation-cap" class="text-success"></i>
                                <span class="fw-semibold">Student Engagement</span>
                            </div>
                        </div>
                        <div class="floating-card bottom-card">
                            <div class="d-flex align-items-center gap-2">
                                <i data-lucide="bell-ring" class="text-primary"></i>
                                <span class="fw-semibold">Instant notices</span>
                            </div>
                        </div>
                        <div class="hero-illustration">
                            <img src="<?= asset('images/grp1.jpeg') ?>" alt="grp1" class="img-fluid hero-logo">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <div class="assistant-widget-shell" aria-live="polite" aria-label="NACOS Assistant">
        <button id="assistantLauncher" class="assistant-launcher" aria-expanded="false" aria-controls="assistantPanel">
            <span class="assistant-icon">🤖</span>
            <span class="assistant-badge">1</span>
        </button>
        <div id="assistantPanel" class="assistant-panel" hidden>
            <div class="assistant-header">
                <div>
                    <h5 class="mb-0">🤖 NACOSFCAI Assistant</h5>
                    <p class="small text-secondary mb-0">How can I help you today?</p>
                </div>
                <button type="button" id="assistantClose" class="assistant-close" aria-label="Close assistant">×</button>
            </div>
            <div class="assistant-messages" id="assistantMessages">
                <div class="assistant-message assistant-message-bot">
                    <div class="assistant-message-content">
                        <p>Hello 👋</p>
                        <p>Welcome to NACOSFCAI.</p>
                        <p>How may I help you today?</p>
                    </div>
                    <span class="assistant-timestamp">Now</span>
                </div>
            </div>
            <div class="assistant-suggestions">
                <button type="button" class="assistant-suggestion">How do I register?</button>
                <button type="button" class="assistant-suggestion">How do I pay dues?</button>
                <button type="button" class="assistant-suggestion">How do I get my ID card?</button>
                <button type="button" class="assistant-suggestion">Contact support.</button>
                <button type="button" class="assistant-suggestion">Certificate.</button>
            </div>
            <div class="assistant-input-shell">
                <label for="assistantInput" class="visually-hidden">Message to NACOS Assistant</label>
                <input type="text" id="assistantInput" class="form-control" aria-label="Message to NACOS Assistant">
                <button type="button" id="assistantSend" class="btn btn-primary">Send</button>
            </div>
            <div class="assistant-status" id="assistantStatus" hidden>Assistant is typing... <span class="assistant-dots">● ● ●</span></div>
        </div>
    </div>

    <section class="container py-5">
        <div class="row g-4">
            <div class="col-sm-6 col-lg-3" data-aos="fade-up" data-aos-delay="100">
                <div class="glass-card stat-card">
                    <i data-lucide="users" class="stat-icon"></i>
                    <h3 class="counter" data-target="<?= $dashboardStats['students'] ?>"><?= $dashboardStats['students'] ?></h3>
                    <p>Registered Students</p>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3" data-aos="fade-up" data-aos-delay="200">
                <div class="glass-card stat-card">
                    <i data-lucide="folder-open" class="stat-icon"></i>
                    <h3 class="counter" data-target="<?= (int)$dashboardStats['resources'] ?>"><?= number_format((int)$dashboardStats['resources']) ?></h3>
                    <p>Resources Uploaded</p>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3" data-aos="fade-up" data-aos-delay="300">
                <div class="glass-card stat-card">
                    <i data-lucide="users-2" class="stat-icon"></i>
                    <h3 class="counter" data-target="<?= $dashboardStats['executives'] ?>"><?= $dashboardStats['executives'] ?></h3>
                    <p>Executives</p>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3" data-aos="fade-up" data-aos-delay="400">
                <div class="glass-card stat-card">
                    <i data-lucide="calendar-plus" class="stat-icon"></i>
                    <h3 class="counter" data-target="<?= $dashboardStats['events'] ?>"><?= $dashboardStats['events'] ?></h3>
                    <p>Events</p>
                </div>
            </div>
        </div>
    </section>

    <section class="container py-5">
        <div class="section-heading" data-aos="fade-up">
            <span class="eyebrow">About NACOSFCAI</span>
            <h2>Built for academic growth, innovation, and leadership.</h2>
            <p>Our department brings together community, technology, and student success into one elegant digital experience.</p>
        </div>
        <div class="row g-4 mt-2">
            <div class="col-lg-6" data-aos="fade-up" data-aos-delay="100">
                <div class="glass-card about-card p-4 h-100">
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <i data-lucide="eye" class="text-success"></i>
                        <h4 class="mb-0">Vision</h4>
                    </div>
                    <p class="mb-0 text-secondary">To be a leading department that nurtures technology-driven, ethical, and globally competitive graduates.</p>
                </div>
            </div>
            <div class="col-lg-6" data-aos="fade-up" data-aos-delay="200">
                <div class="glass-card about-card p-4 h-100">
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <i data-lucide="target" class="text-primary"></i>
                        <h4 class="mb-0">Mission</h4>
                    </div>
                    <p class="mb-0 text-secondary">To promote scholarship, innovation, leadership, and professional excellence through quality teaching and service.</p>
                </div>
            </div>
        </div>
        <div class="text-center mt-4" data-aos="fade-up">
            <a href="<?= APP_URL ?>/about.php" class="btn btn-outline-primary">Read More</a>
        </div>
    </section>

    <section class="container py-5">
        <div class="section-heading" data-aos="fade-up">
            <span class="eyebrow">Programmes</span>
            <h2>Explore the academic pathways we offer.</h2>
        </div>
        <div class="row g-4 mt-2">
            <?php foreach ($programmes as $programme): ?>
                <div class="col-lg-4" data-aos="fade-up">
                    <div class="glass-card programme-card h-100">
                        <img src="<?= e($programme['image']) ?>" alt="<?= e($programme['title']) ?>" class="img-fluid rounded-4 mb-3">
                        <h4 class="fw-bold mb-2"><?= e($programme['title']) ?></h4>
                        <p class="text-secondary mb-3"><?= e($programme['description']) ?></p>
                        <a href="<?= e($programme['link']) ?>" class="btn btn-sm btn-outline-primary">Explore</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="container py-5">
        <div class="section-heading" data-aos="fade-up">
            <span class="eyebrow">Executive Team</span>
            <h2>Meet the people guiding NACOS FCAIshiagu.</h2>
        </div>
        <div class="row g-4 mt-2">
            <?php foreach ($executivePreview as $executive): ?>
                <div class="col-md-6 col-lg-4" data-aos="fade-up">
                    <div class="glass-card executive-card text-center p-4 h-100">
                        <img src="<?= e($executive['image']) ?>" alt="<?= e($executive['name']) ?>" class="executive-photo">
                        <h5 class="fw-bold mt-3 mb-1"><?= e($executive['name']) ?></h5>
                        <p class="text-secondary mb-0"><?= e($executive['role']) ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="text-center mt-4" data-aos="fade-up">
            <a href="<?= APP_URL ?>/executives.php" class="btn btn-outline-primary">View All Executives</a>
        </div>
    </section>

    <section class="container py-5">
        <div class="section-heading" data-aos="fade-up">
            <span class="eyebrow">Latest Announcements</span>
            <h2>Stay ahead of department news and updates.</h2>
        </div>
        <div class="row g-4 mt-2">
            <?php if ($announcements): foreach ($announcements as $item): ?>
                <div class="col-lg-6" data-aos="fade-up">
                    <div class="glass-card announcement-card p-4 h-100">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span class="badge bg-success-subtle text-success"><?= e($item['category'] ?? 'Update') ?></span>
                            <span class="small text-secondary"><?= e(substr($item['created_at'] ?? '', 0, 10)) ?></span>
                        </div>
                        <h5 class="fw-bold mb-2"><?= e($item['title'] ?? '') ?></h5>
                        <p class="text-secondary mb-0"><?= e(substr($item['body'] ?? '', 0, 140)) ?>...</p>
                    </div>
                </div>
            <?php endforeach; else: ?>
                <div class="col-12">
                    <div class="glass-card p-4 text-center"><p class="mb-0 text-secondary">No announcements have been published yet.</p></div>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <section class="container py-5">
        <div class="section-heading" data-aos="fade-up">
            <span class="eyebrow">Upcoming Events</span>
            <h2>Engage with NACOS FCAIshiagu through memorable experiences.</h2>
        </div>
        <div class="row g-4 mt-2">
            <?php if ($events): foreach ($events as $index => $event): ?>
                <div class="col-lg-6" data-aos="fade-up">
                    <div class="glass-card event-card h-100 overflow-hidden" data-event-date="<?= e($event['event_date'] ?? '') ?>" data-images="<?= e(json_encode([asset('images/event.png'), asset('images/event.png'), asset('images/event.png')])) ?>">
                        <div class="event-media"></div>
                        <div class="p-4 position-relative">
                            <h5 class="fw-bold mb-2"><?= e($event['title']) ?></h5>
                            <p class="text-secondary mb-2"><?= e($event['event_date']) ?> • <?= e($event['venue']) ?></p>
                            <p class="text-secondary mb-3"><?= e(substr($event['description'] ?? 'NACOS event designed to inspire collaboration and growth.', 0, 120)) ?>...</p>
                            <div class="d-flex justify-content-between align-items-center">
                                <a href="<?= APP_URL ?>/events.php" class="btn btn-sm btn-outline-primary">View Event</a>
                                <div class="countdown-pill" data-countdown="<?= e($event['event_date'] ?? '') ?>"></div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; else: ?>
                <div class="col-12">
                    <div class="glass-card p-4 text-center"><p class="mb-0 text-secondary">No events scheduled.</p></div>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <section class="container py-5">
        <div class="section-heading" data-aos="fade-up">
            <span class="eyebrow">Gallery Preview</span>
            <h2>Moments that tell the story of our community.</h2>
        </div>
        <div class="gallery-grid mt-3">
            <?php foreach ($galleryPreview as $item): ?>
                <div class="gallery-item glass-card" data-aos="zoom-in">
                    <img src="<?= e($item['image']) ?>" alt="<?= e($item['title']) ?>">
                    <div class="gallery-overlay"><span><?= e($item['title']) ?></span></div>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="text-center mt-4" data-aos="fade-up">
            <a href="<?= APP_URL ?>/gallery.php" class="btn btn-outline-primary">View Gallery</a>
        </div>
    </section>

    <section class="container py-5">
        <div class="section-heading" data-aos="fade-up">
            <span class="eyebrow">Resources</span>
            <h2>Everything you need for academic progress.</h2>
        </div>
        <div class="row g-4 mt-2">
            <?php foreach ($resources as $resource): ?>
                <div class="col-6 col-lg-3" data-aos="fade-up">
                    <div class="glass-card resource-card p-4 h-100">
                        <i data-lucide="<?= e($resource['icon']) ?>" class="resource-icon"></i>
                        <h5 class="fw-bold mt-3 mb-2"><?= e($resource['level']) ?></h5>
                        <p class="text-secondary small mb-3"><?= e($resource['description']) ?></p>
                        <a href="<?= APP_URL ?>/student/resources.php" class="btn btn-sm btn-outline-primary">Open</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="container py-5">
        <div class="glass-card cta-card p-4 p-lg-5" data-aos="fade-up">
            <div class="row align-items-center gy-4">
                <div class="col-lg-8">
                    <span class="eyebrow">Join the Department</span>
                    <h2 class="mt-2 mb-3">Take the next step into a stronger academic future.</h2>
                    <p class="text-secondary mb-0">Register today and unlock resources, events, support, and a seamless digital experience with NACOS.</p>
                </div>
                <div class="col-lg-4 text-lg-end">
                    <a href="<?= APP_URL ?>/auth/register.php" class="btn btn-primary btn-lg mb-2">Register</a>
                </div>
            </div>
        </div>
    </section>

    <section class="container py-5">
        <div class="section-heading" data-aos="fade-up">
            <span class="eyebrow">Frequently Asked Questions</span>
            <h2>Helpful answers for students and members.</h2>
        </div>
        <div class="faq-list mt-3">
            <?php foreach ($faqs as $faq): ?>
                <div class="glass-card faq-item" data-aos="fade-up">
                    <button class="faq-question" type="button">
                        <span><?= e($faq['question']) ?></span>
                        <i data-lucide="plus"></i>
                    </button>
                    <div class="faq-answer">
                        <p class="mb-0 text-secondary"><?= e($faq['answer']) ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="container py-5">
        <div class="row g-4">
            <div class="col-lg-6" data-aos="fade-right">
                <div class="glass-card support-card p-4 h-100">
                    <span class="eyebrow">Support</span>
                    <h3 class="mt-2 mb-3">Need help? Our support desk is ready.</h3>
                    <ul class="list-unstyled text-secondary mb-0">
                         <div class="social-grid mt-3">
                    <a href="https://www.facebook.com/share/1N2WtJnZsv/?mibextid=wwXIfr" class="social-pill" target="_blank" rel="noopener noreferrer" aria-label="Visit our Facebook page"><i class="fa-brands fa-facebook-f"></i></a>
                    <a href="https://vt.tiktok.com/ZSX6VjxDh/" class="social-pill" target="_blank" rel="noopener noreferrer" aria-label="Visit our TikTok page"><i class="fa-brands fa-tiktok"></i></a>
                    <a href="https://x.com/nacosfcai?s=11" class="social-pill" target="_blank" rel="noopener noreferrer" aria-label="Visit our X profile"><i class="fa-brands fa-x-twitter"></i></a>
                    <a href="https://wa.me/qr/VWTYVDPLMHM5H1" class="social-pill" target="_blank" rel="noopener noreferrer" aria-label="Chat with us on WhatsApp"><i class="fa-brands fa-whatsapp"></i></a>
                </ul>
                    <a href="<?= APP_URL ?>/student/support.php" class="btn btn-outline-primary mt-4">Contact Support</a>
                </div>
            </div>
            <div class="col-lg-6" data-aos="fade-left">
                <div class="glass-card social-card p-4 h-100">
                    <span class="eyebrow">Social Media</span>
                    <h3 class="mt-2 mb-3">Stay connected with the department.</h3>
                    <div class="social-grid mt-3">
                    <a href="https://www.facebook.com/share/1N2WtJnZsv/?mibextid=wwXIfr" class="social-pill" target="_blank" rel="noopener noreferrer" aria-label="Visit our Facebook page"><i class="fa-brands fa-facebook-f"></i></a>
                    <a href="https://vt.tiktok.com/ZSX6VjxDh/" class="social-pill" target="_blank" rel="noopener noreferrer" aria-label="Visit our TikTok page"><i class="fa-brands fa-tiktok"></i></a>
                    <a href="https://x.com/nacosfcai?s=11" class="social-pill" target="_blank" rel="noopener noreferrer" aria-label="Visit our X profile"><i class="fa-brands fa-x-twitter"></i></a>
                    <a href="https://wa.me/qr/VWTYVDPLMHM5H1" class="social-pill" target="_blank" rel="noopener noreferrer" aria-label="Chat with us on WhatsApp"><i class="fa-brands fa-whatsapp"></i></a>
                </div>
                </div>
            </div>
        </div>
    </section>
</div>
<?php require_once __DIR__ . '/templates/footer.php'; ?>
