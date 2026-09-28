<?php require_once dirname(__DIR__) . '/templates/header.php'; ?>
<?php
$auth = new Auth(Database::getInstance());
$auth->requireLogin('student');
$user = $auth->currentUser();
$portal = new Portal(Database::getInstance());
$election = new Election(Database::getInstance());

$announcements = $portal->getRecentAnnouncements(4);
$events = $portal->getUpcomingEvents(4);
$paymentSummary = $portal->getStudentPaymentSummary((int)($user['id'] ?? 0));
$supportSummary = $portal->getStudentSupportSummary((int)($user['id'] ?? 0));
$activityLogs = $portal->getStudentActivityLog((int)($user['id'] ?? 0), 6);
$unreadNotifications = $portal->getStudentUnreadNotificationCount((int)($user['id'] ?? 0));
$resourceCount = $portal->countResourcesByLevelAndSemester($user['level'] ?? '', $user['semester'] ?? '');
$activeElections = $election->getActiveElections();
$activeElectionCount = count($activeElections);
$paymentCategories = $portal->listPaymentCategories(true);

$membershipStatus = ucfirst($user['status'] ?? 'active');
$membershipDate = !empty($user['created_at']) ? date('M dS Y', strtotime($user['created_at'])) : date('M dS Y');
$membershipNumber = $user['registration_number'] ?? $user['matric_number'] ?? 'N/A';
$courseOfStudy = $user['course'] ?? 'Course not assigned';
$currentSession = $user['session'] ?? $user['academic_session'] ?? 'Not assigned';

$payments = $portal->getStudentPayments((int)($user['id'] ?? 0));
$certificateAccess = false;
$idCardAccess = false;
foreach ($payments as $payment) {
    $status = strtolower($payment['status'] ?? 'pending');
    $paymentType = strtolower($payment['payment_type'] ?? $payment['category'] ?? '');
    if (in_array($status, ['approved', 'verified'], true)) {
        if (str_contains($paymentType, 'dues') || str_contains($paymentType, 'membership')) {
            $certificateAccess = true;
        }
        if (str_contains($paymentType, 'id card') || str_contains($paymentType, 'identity card')) {
            $idCardAccess = true;
        }
    }
}

$profileFields = [
    'full_name' => !empty($user['full_name']),
    'email' => !empty($user['email']),
    'phone' => !empty($user['phone']),
    'department' => !empty($user['department']),
    'course' => !empty($user['course']),
    'semester' => !empty($user['semester']),
    'registration_number' => !empty($user['registration_number']),
];
$profileCompletion = (int)(array_sum(array_map(fn($ok) => $ok ? 1 : 0, $profileFields)) / count($profileFields) * 100);
?>
<div class="student-page-shell container py-5">
    <div class="row g-4">
        <div class="col-lg-3">
            <?php require_once dirname(__DIR__) . '/templates/student-sidebar.php'; ?>
        </div>
        <div class="col-lg-9">
            <div class="student-dashboard-page">
                <section class="dashboard-hero compact-dashboard-hero">
                    <div class="container">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h2 class="mb-1 text-white">Welcome back, <?= e($user['full_name'] ?? 'Student') ?>!</h2>
                                <p class="text-white-50 mb-1">Member ID: <?= e($membershipNumber) ?> • Course: <?= e($courseOfStudy) ?></p>
                                <p class="text-white-50 mb-0">Session: <?= e($currentSession) ?> • Joined <?= e($membershipDate) ?></p>
                            </div>
                            <div class="col-md-4 text-md-end mt-4 mt-md-0">
                                <div class="d-inline-flex align-items-center gap-2">
                                    <button id="themeToggleBtn" type="button" class="btn btn-outline-light btn-sm theme-mode-btn">
                                        <i class="fa-solid fa-moon"></i>
                                        <span class="ms-2">Dark Mode</span>
                                    </button>
                                    <span class="badge bg-white text-success fw-semibold">Student Portal</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="container py-4">
                    <div class="row g-3">
                        <div class="col-lg-4 col-md-6" data-aos="fade-up">
                            <div class="glass-card p-4 h-100">
                                <div class="d-flex align-items-center justify-content-between mb-3">
                                    <div>
                                        <h6 class="text-secondary small mb-1">Member Status</h6>
                                        <h4 class="mb-1"><?= e($membershipStatus) ?></h4>
                                    </div>
                                    <i data-lucide="user-check" class="text-success" style="width:28px;height:28px"></i>
                                </div>
                                <p class="text-secondary small mb-0">Verified membership and access to portal resources.</p>
                            </div>
                        </div>
                        <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="50">
                            <div class="glass-card p-4 h-100">
                                <div class="d-flex align-items-center justify-content-between mb-3">
                                    <div>
                                        <h6 class="text-secondary small mb-1">Payments Verified</h6>
                                        <h4 class="mb-1"><?= e(number_format($paymentSummary['verified_count'] ?? 0)) ?></h4>
                                    </div>
                                    <i data-lucide="credit-card" class="text-primary" style="width:28px;height:28px"></i>
                                </div>
                                <p class="text-secondary small mb-0">Total payment records approved or verified.</p>
                            </div>
                        </div>
                        <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="100">
                            <div class="glass-card p-4 h-100">
                                <div class="d-flex align-items-center justify-content-between mb-3">
                                    <div>
                                        <h6 class="text-secondary small mb-1">Resources Available</h6>
                                        <h4 class="mb-1"><?= e(number_format($resourceCount)) ?></h4>
                                    </div>
                                    <i data-lucide="book-open" class="text-warning" style="width:28px;height:28px"></i>
                                </div>
                                <p class="text-secondary small mb-0">Study materials aligned with your level and semester.</p>
                            </div>
                        </div>
                        <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="150">
                            <div class="glass-card p-4 h-100">
                                <div class="d-flex align-items-center justify-content-between mb-3">
                                    <div>
                                        <h6 class="text-secondary small mb-1">Open Support Tickets</h6>
                                        <h4 class="mb-1"><?= e(number_format($supportSummary['open_count'] ?? 0)) ?></h4>
                                    </div>
                                    <i data-lucide="help-circle" class="text-info" style="width:28px;height:28px"></i>
                                </div>
                                <p class="text-secondary small mb-0">Requests pending review by support staff.</p>
                            </div>
                        </div>
                        <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="200">
                            <div class="glass-card p-4 h-100">
                                <div class="d-flex align-items-center justify-content-between mb-3">
                                    <div>
                                        <h6 class="text-secondary small mb-1">Unread Notifications</h6>
                                        <h4 class="mb-1"><?= e(number_format($unreadNotifications)) ?></h4>
                                    </div>
                                    <i data-lucide="bell" class="text-danger" style="width:28px;height:28px"></i>
                                </div>
                                <p class="text-secondary small mb-0">Alerts, announcements, and portal updates.</p>
                            </div>
                        </div>
                        <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="250">
                            <div class="glass-card p-4 h-100">
                                <div class="d-flex align-items-center justify-content-between mb-3">
                                    <div>
                                        <h6 class="text-secondary small mb-1">Profile Completion</h6>
                                        <h4 class="mb-1"><?= e($profileCompletion) ?>%</h4>
                                    </div>
                                    <i data-lucide="settings" class="text-muted" style="width:28px;height:28px"></i>
                                </div>
                                <p class="text-secondary small mb-0">Complete your profile to access all services.</p>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="container py-4">
                    <div class="row g-4">
                        <div class="col-xl-8" data-aos="fade-up">
                            <div class="card border-0 rounded-4 shadow-sm p-4 h-100">
                                <div class="d-flex align-items-center justify-content-between mb-4">
                                    <div>
                                        <h5 class="fw-bold mb-1">Departmental Actions</h5>
                                        <p class="text-secondary small mb-0">Access core student services quickly.</p>
                                    </div>
                                    <span class="badge bg-success">Core portal</span>
                                </div>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <a href="<?= APP_URL ?>/student/payments.php" class="btn btn-outline-success w-100 py-3 text-start text-truncate">Pay Dues & Fees</a>
                                    </div>
                                    <div class="col-md-6">
                                        <a href="<?= APP_URL ?>/student/resources.php" class="btn btn-outline-primary w-100 py-3 text-start text-truncate">Browse Course Resources</a>
                                    </div>
                                    <div class="col-md-6">
                                        <a href="<?= APP_URL ?>/student/notifications.php" class="btn btn-outline-warning w-100 py-3 text-start text-truncate">View Notifications</a>
                                    </div>
                                    <div class="col-md-6">
                                        <a href="<?= APP_URL ?>/student/support.php" class="btn btn-outline-info w-100 py-3 text-start text-truncate">Open Support Ticket</a>
                                    </div>
                                    <div class="col-md-6">
                                        <a href="<?= APP_URL ?>/student/profile.php" class="btn btn-outline-secondary w-100 py-3 text-start text-truncate">Update Profile</a>
                                    </div>
                                    <div class="col-md-6">
                                        <a href="<?= APP_URL ?>/student/voting.php" class="btn btn-outline-dark w-100 py-3 text-start text-truncate">Participate in Elections</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-4" data-aos="fade-up" data-aos-delay="100">
                            <div class="glass-card p-4 h-100">
                                <div class="d-flex align-items-center justify-content-between mb-4">
                                    <div>
                                        <h5 class="fw-bold mb-1">Your Portal Status</h5>
                                        <p class="text-secondary small mb-0">Quick overview of your current eligibility.</p>
                                    </div>
                                </div>
                                <ul class="list-unstyled mb-0">
                                    <li class="d-flex justify-content-between align-items-center py-2 border-bottom">
                                        <span>Certificate access</span>
                                        <span class="badge bg-<?= $certificateAccess ? 'success' : 'secondary' ?>"><?= $certificateAccess ? 'Active' : 'Pending' ?></span>
                                    </li>
                                    <li class="d-flex justify-content-between align-items-center py-2 border-bottom">
                                        <span>ID card access</span>
                                        <span class="badge bg-<?= $idCardAccess ? 'success' : 'secondary' ?>"><?= $idCardAccess ? 'Active' : 'Pending' ?></span>
                                    </li>
                                    <li class="d-flex justify-content-between align-items-center py-2 border-bottom">
                                        <span>Active elections</span>
                                        <span class="badge bg-info"><?= e($activeElectionCount) ?></span>
                                    </li>
                                    <li class="d-flex justify-content-between align-items-center py-2 border-bottom">
                                        <span>Open dues</span>
                                        <span class="badge bg-warning text-dark"><?= e(number_format($paymentSummary['pending_count'] ?? 0)) ?></span>
                                    </li>
                                    <li class="d-flex justify-content-between align-items-center py-2">
                                        <span>Pending rejections</span>
                                        <span class="badge bg-danger"><?= e(number_format($paymentSummary['rejected_count'] ?? 0)) ?></span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="container py-4">
                    <div class="row g-4">
                        <div class="col-lg-6" data-aos="fade-up">
                            <div class="glass-card p-4 h-100">
                                <div class="d-flex align-items-center justify-content-between mb-4">
                                    <div>
                                        <h5 class="fw-bold mb-1">Payment Summary</h5>
                                        <p class="text-secondary small mb-0">Overview of your recent dues and payments.</p>
                                    </div>
                                    <span class="badge bg-success">₹</span>
                                </div>
                                <div class="row g-3">
                                    <div class="col-6">
                                        <div class="border rounded-3 p-3 text-center">
                                            <div class="text-secondary small">Verified</div>
                                            <div class="fs-4 fw-bold"><?= e(number_format($paymentSummary['verified_count'] ?? 0)) ?></div>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="border rounded-3 p-3 text-center">
                                            <div class="text-secondary small">Pending</div>
                                            <div class="fs-4 fw-bold"><?= e(number_format($paymentSummary['pending_count'] ?? 0)) ?></div>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="border rounded-3 p-3 text-center">
                                            <div class="text-secondary small">Rejected</div>
                                            <div class="fs-4 fw-bold"><?= e(number_format($paymentSummary['rejected_count'] ?? 0)) ?></div>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="border rounded-3 p-3 text-center">
                                            <div class="text-secondary small">Last Paid</div>
                                            <div class="fs-4 fw-bold">₦<?= e(number_format($paymentSummary['total_paid'] ?? 0, 2)) ?></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-6" data-aos="fade-up" data-aos-delay="100">
                            <div class="glass-card p-4 h-100">
                                <div class="d-flex align-items-center justify-content-between mb-4">
                                    <div>
                                        <h5 class="fw-bold mb-1">Student Activity</h5>
                                        <p class="text-secondary small mb-0">Recent portal actions and updates.</p>
                                    </div>
                                    <span class="badge bg-secondary">Live</span>
                                </div>
                                <?php if ($activityLogs): ?>
                                    <div class="list-group list-group-flush">
                                        <?php foreach ($activityLogs as $log): ?>
                                            <div class="list-group-item px-0 border-0 pb-3">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div>
                                                        <div class="fw-semibold"><?= e($log['action'] ?? 'Activity recorded') ?></div>
                                                        <p class="text-secondary small mb-0"><?= e($log['description'] ?? $log['details'] ?? '') ?></p>
                                                    </div>
                                                    <div class="text-end small text-muted"><?= e(date('M j, Y', strtotime($log['created_at'] ?? 'now'))) ?></div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-5">
                                        <p class="text-secondary mb-0">No recent activity yet. Use the portal actions to get started.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="container py-4">
                    <div class="row g-4">
                        <div class="col-lg-6" data-aos="fade-up">
                            <div class="glass-card p-4 h-100">
                                <div class="d-flex align-items-center gap-2 mb-3">
                                    <i data-lucide="megaphone" style="width: 20px; height: 20px;"></i>
                                    <h5 class="mb-0">Announcements</h5>
                                </div>
                                <div class="dashboard-list">
                                    <?php if ($announcements): ?>
                                        <?php foreach ($announcements as $item): ?>
                                            <div class="dashboard-list-item">
                                                <div>
                                                    <h6 class="mb-1"><?= e($item['title']) ?></h6>
                                                    <p class="text-secondary small mb-0"><?= e(substr($item['body'] ?? '', 0, 100)) ?></p>
                                                </div>
                                                <a href="<?= APP_URL ?>/announcements.php" class="btn btn-sm btn-outline-success">View</a>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <p class="text-secondary small mb-0">No announcements available right now.</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-6" data-aos="fade-up" data-aos-delay="100">
                            <div class="glass-card p-4 h-100">
                                <div class="d-flex align-items-center gap-2 mb-3">
                                    <i data-lucide="calendar-days" style="width: 20px; height: 20px;"></i>
                                    <h5 class="mb-0">Upcoming Events</h5>
                                </div>
                                <div class="dashboard-list">
                                    <?php if ($events): ?>
                                        <?php foreach ($events as $event): ?>
                                            <div class="dashboard-list-item">
                                                <div>
                                                    <h6 class="mb-1"><?= e($event['title']) ?></h6>
                                                    <p class="text-secondary small mb-0"><?= e(date('M j, Y', strtotime($event['event_date'] ?? 'now'))) ?> • <?= e($event['venue'] ?? 'Venue not specified') ?></p>
                                                </div>
                                                <span class="badge bg-success">Upcoming</span>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <p class="text-secondary small mb-0">No upcoming events scheduled.</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </div>
</div>
<?php require_once dirname(__DIR__) . '/templates/footer.php'; ?>
