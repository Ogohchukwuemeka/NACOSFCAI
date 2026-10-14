<footer class="site-footer">
    <div class="container py-5">
        <div class="row gy-4">
            <div class="col-lg-4">
                <div class="footer-card p-4 h-100">
                    <div class="d-flex align-items-start gap-3 mb-4">
                        <img src="<?= asset('images/logo/nacos-logo.jpeg') ?>" alt="NACOS FCAI logo" class="brand-logo" loading="lazy">
                        <div>
                            <h5 class="fw-bold mb-1">NACOSFCAI</h5>
                            <p class="small text-secondary mb-1">Nigeria Association of Computing Students</p>
                            <p class="small text-secondary mb-0">Federal College of Agriculture, Ishiagu</p>
                        </div>
                    </div>
                    <p class="text-secondary small mb-0">A modern digital platform connecting students, executives, academic resources, payments, and departmental activities within the Computer Science Department, Federal College of Agriculture, Ishiagu.</p>
                </div>
            </div>
            <div class="col-lg-2 col-md-6">
                <div class="footer-card p-4 h-100">
                    <h6 class="footer-widget-title mb-4">Quick Links</h6>
                    <ul class="list-unstyled footer-links">
                        <li><a href="<?= APP_URL ?>/index.php">Home</a></li>
                        <li><a href="<?= APP_URL ?>/about.php">About</a></li>
                        <li><a href="<?= APP_URL ?>/programmes.php">Programmes</a></li>
                        <li><a href="<?= APP_URL ?>/executives.php">Executives</a></li>
                        <li><a href="<?= APP_URL ?>/events.php">Events</a></li>
                        <li><a href="<?= APP_URL ?>/gallery.php">Gallery</a></li>
                    </ul>
                </div>
            </div>
            <div class="col-lg-2 col-md-6">
                <div class="footer-card p-4 h-100">
                    <h6 class="footer-widget-title mb-4">Student Links</h6>
                    <ul class="list-unstyled footer-links">
                        <li><a href="<?= APP_URL ?>/auth/login.php">Login</a></li>
                        <li><a href="<?= APP_URL ?>/auth/register.php">Register</a></li>
                        <li><a href="<?= APP_URL ?>/student/payments.php">Payments</a></li>
                        <li><a href="<?= APP_URL ?>/student/resources.php">Resources</a></li>
                        <li><a href="<?= APP_URL ?>/announcements.php">Announcements</a></li>
                        <li><a href="<?= APP_URL ?>/student/support.php">Support</a></li>
                    </ul>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="row g-3">
                    <?php
                    try {
                        $portal = new Portal(Database::getInstance());
                        $settings = $portal->getSettings();
                    } catch (Throwable $e) {
                        $settings = [];
                    }
                    $contact_email = $settings['contact_email'] ?? 'nacosfcaishiagu@gmail.com';
                    $contact_phone = $settings['contact_phone'] ?? '+234 90 3310 3319';
                    $address = $settings['address'] ?? "Computer Science Department\nFederal College of Agriculture\nIshiagu, Ebonyi State\nNigeria";
                    $facebook = $settings['facebook'] ?? 'https://www.facebook.com/share/1N2WtJnZsv/?mibextid=wwXIfr';
                    $tiktok = $settings['tiktok'] ?? 'https://vt.tiktok.com/ZSX6VjxDh/';
                    $x_handle = $settings['x_handle'] ?? 'https://x.com/nacosfcai?s=11';
                    $whatsapp = $settings['whatsapp'] ?? 'https://wa.me/qr/VWTYVDPLMHM5H1';
                    ?>
                    <div class="col-12">
                        <div class="footer-card p-4 h-100">
                            <h6 class="footer-widget-title mb-4">Contact Information</h6>
                            <ul class="list-unstyled footer-contact-list mb-3">
                                <li class="footer-contact-item"><i data-lucide="mail"></i><span><?= e($contact_email) ?></span></li>
                                <li class="footer-contact-item"><i data-lucide="phone"></i><span><?= e($contact_phone) ?></span></li>
                                <li class="footer-contact-item"><i data-lucide="map-pin"></i><span><?= nl2br(e($address)) ?></span></li>
                            </ul>
                            <div class="footer-office-hours rounded-4 p-3">
                                <div class="d-flex align-items-center gap-2 mb-2"><i data-lucide="clock"></i><span class="fw-semibold">Office Hours</span></div>
                                <p class="mb-1">Monday – Friday</p>
                                <p class="mb-0">8:00 AM – 4:00 PM</p>
                            </div>
                            <div class="social-grid mt-4">
                                <a href="<?= e($facebook) ?>" class="social-pill facebook" target="_blank" rel="noopener noreferrer" aria-label="Visit our Facebook page"><i class="fa-brands fa-facebook-f"></i></a>
                                <a href="<?= e($tiktok) ?>" class="social-pill tiktok" target="_blank" rel="noopener noreferrer" aria-label="Visit our TikTok page"><i class="fa-brands fa-tiktok"></i></a>
                                <a href="<?= e($x_handle) ?>" class="social-pill x" target="_blank" rel="noopener noreferrer" aria-label="Visit our X profile"><i class="fa-brands fa-x-twitter"></i></a>
                                <a href="<?= e($whatsapp) ?>" class="social-pill whatsapp" target="_blank" rel="noopener noreferrer" aria-label="Chat with us on WhatsApp"><i class="fa-brands fa-whatsapp"></i></a>
                            </div>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="footer-card p-4 h-100">
                            <h6 class="footer-widget-title mb-3">Stay Updated</h6>
                            <p class="small text-secondary mb-4">Receive announcements and upcoming event updates directly in your inbox.</p>
                            <form id="newsletterForm" class="newsletter-form">
                                <div class="input-group mb-3">
                                    <span class="input-group-text"><i data-lucide="mail"></i></span>
                                    <label for="newsletterEmail" class="visually-hidden">Email address</label><input type="email" id="newsletterEmail" name="newsletter_email" class="form-control" aria-label="Email address" required>
                                </div>
                                <button type="submit" class="btn btn-success w-100">Subscribe</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="footer-bottom mt-5 pt-4 border-top">
            <div class="d-flex flex-column flex-lg-row justify-content-between align-items-center gap-3">
                <div class="text-secondary small text-center text-lg-start">
                    <p class="mb-1">© 2026 NACOS.FCAI</p>
                    <p class="mb-0">All Rights Reserved.</p>
                </div>
                <div class="d-flex flex-column flex-sm-row align-items-center gap-3">
                    <div class="text-secondary small text-center text-sm-start">
                        <p class="mb-1 fw-semibold">Designed & Developed by</p>
                        <p class="mb-0">Honest Ventures</p>
                        <p class="mb-0 small">Web • Mobile • Software Development</p>
                    </div>
                    <a href="https://wa.me/qr/VWTYVDPLMHM5H1" class="developer-btn btn btn-success btn-pill d-inline-flex align-items-center gap-2" target="_blank" rel="noopener noreferrer">
                        <i class="fa-brands fa-whatsapp"></i>
                        <div class="text-start">
                            <span class="d-block small">Need a Similar Portal?</span>
                            <strong>Chat the Developer</strong>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </div>
</footer>
<button id="backToTop" type="button" class="back-to-top" aria-label="Scroll back to top"><i data-lucide="chevron-up"></i></button>

<button id="assistantLauncher" class="assistant-launcher" type="button" aria-expanded="false" aria-controls="assistantPanel">
    <span class="assistant-launcher-icon"><i class="fa-solid fa-headset"></i></span>
    <span class="assistant-launcher-label">Need help?</span>
</button>

<div id="assistantPanel" class="assistant-panel" hidden>
    <div class="assistant-panel-header">
        <div>
            <div class="assistant-panel-title">NACOS Assistant</div>
            <small class="assistant-panel-subtitle">Student support</small>
        </div>
        <button id="assistantClose" type="button" class="assistant-close" aria-label="Close assistant">×</button>
    </div>

    <div id="assistantMessages" class="assistant-messages" aria-live="polite">
        <div class="assistant-message assistant-message-bot">
            <div class="assistant-message-content">
                <p>Hello! I can help with registrations, payments, certificates, support, resources, and general student portal questions.</p>
            </div>
            <span class="assistant-timestamp">Now</span>
        </div>
    </div>

    <div class="assistant-suggestions" aria-label="Suggested questions">
        <button type="button" class="assistant-suggestion">How do I register?</button>
        <button type="button" class="assistant-suggestion">How do I pay dues?</button>
        <button type="button" class="assistant-suggestion">How do I get my certificate?</button>
    </div>

    <div id="assistantStatus" class="assistant-status" hidden>Assistant is typing…</div>

    <div class="assistant-composer">
        <textarea id="assistantInput" rows="2" placeholder="Ask a question..."></textarea>
        <button id="assistantSend" type="button" class="assistant-send-btn">Send</button>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.6/dist/chart.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="<?= asset('js/app.js') ?>"></script>

