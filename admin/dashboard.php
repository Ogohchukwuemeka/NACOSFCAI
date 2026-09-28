<?php require_once dirname(__DIR__) . '/templates/header.php'; ?>
<?php
$auth = new Auth(Database::getInstance());
$auth->requireLogin('admin');
$portal = new Portal(Database::getInstance());
$stats = $portal->getDashboardStats();
$announcements = $portal->listAnnouncements(5, 0);
?>
<div class="admin-dashboard-page">
    <section class="container py-4">
        <div class="row gy-4">
            <div class="col-lg-3">
                <?php require_once dirname(__DIR__) . '/templates/admin-sidebar.php'; ?>
            </div>
            <div class="col-lg-9">
                <div class="row g-4 mb-4">
                    <div class="col-md-6 col-xl-3" data-aos="fade-up"><div class="glass-card p-4 stat-panel"><div class="d-flex align-items-center justify-content-between mb-3"><h6 class="text-secondary mb-0">Students</h6><div class="dashboard-icon"><i data-lucide="users"></i></div></div><h3 class="fw-bold mb-0"><?= e($stats['students']) ?></h3></div></div>
                    <div class="col-md-6 col-xl-3" data-aos="fade-up" data-aos-delay="50"><div class="glass-card p-4 stat-panel"><div class="d-flex align-items-center justify-content-between mb-3"><h6 class="text-secondary mb-0">Payments</h6><div class="dashboard-icon"><i data-lucide="credit-card"></i></div></div><h3 class="fw-bold mb-0"><?= e($stats['payments']) ?></h3></div></div>
                    <div class="col-md-6 col-xl-3" data-aos="fade-up" data-aos-delay="100"><div class="glass-card p-4 stat-panel"><div class="d-flex align-items-center justify-content-between mb-3"><h6 class="text-secondary mb-0">Announcements</h6><div class="dashboard-icon"><i data-lucide="megaphone"></i></div></div><h3 class="fw-bold mb-0"><?= e($stats['announcements']) ?></h3></div></div>
                    <div class="col-md-6 col-xl-3" data-aos="fade-up" data-aos-delay="150"><div class="glass-card p-4 stat-panel"><div class="d-flex align-items-center justify-content-between mb-3"><h6 class="text-secondary mb-0">Support Tickets</h6><div class="dashboard-icon"><i data-lucide="message-square-more"></i></div></div><h3 class="fw-bold mb-0"><?= e($stats['tickets']) ?></h3></div></div>
                </div>

                <div class="row g-4">
                    <div class="col-lg-8">
                <div class="glass-card p-4" data-aos="fade-up">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div>
                            <h4 class="fw-bold mb-1">Publish New Announcement</h4>
                            <p class="text-secondary mb-0">Share important updates with the department community.</p>
                        </div>
                        <div class="dashboard-icon"><i data-lucide="megaphone"></i></div>
                    </div>
                    <form method="post" action="actions.php" class="mb-4">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="create_announcement">
                        <div class="row g-3">
                            <div class="col-md-4"><input type="text" name="title" class="form-control form-control-lg" placeholder="Title" required></div>
                            <div class="col-md-3"><input type="text" name="category" class="form-control form-control-lg" placeholder="Category" required></div>
                            <div class="col-md-5"><input type="text" name="body" class="form-control form-control-lg" placeholder="Message" required></div>
                        </div>
                        <button class="btn btn-success mt-3">Publish</button>
                    </form>
                    <div class="dashboard-list">
                        <?php foreach ($announcements as $item): ?>
                            <div class="dashboard-list-item">
                                <div>
                                    <h6 class="mb-1"><?= e($item['title']) ?></h6>
                                    <p class="text-secondary small mb-0"><?= e($item['category'] ?? 'Update') ?></p>
                                </div>
                                <span class="badge-soft">Live</span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="glass-card p-4" data-aos="fade-up" data-aos-delay="100">
                    <h5 class="fw-bold mb-3">Admin Quick Actions</h5>
                    <div class="d-grid gap-2">
                        <a href="<?= APP_URL ?>/admin/students.php" class="btn btn-success">Manage Students</a>
                        <a href="<?= APP_URL ?>/admin/payments.php" class="btn btn-outline-success">Manage Payments</a>
                        <a href="<?= APP_URL ?>/admin/content.php" class="btn btn-outline-success">Create Events & Resources</a>
                        <a href="<?= APP_URL ?>/admin/support/tickets.php" class="btn btn-outline-secondary">Review Tickets</a>
                        <a href="<?= APP_URL ?>/admin/reports.php" class="btn btn-outline-primary">View Reports</a>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
<?php require_once dirname(__DIR__) . '/templates/footer.php'; ?>
