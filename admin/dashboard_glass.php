<?php
require_once dirname(__DIR__) . '/config/bootstrap.php';
$auth = new Auth(Database::getInstance());
$auth->requireLogin('admin');
require_once dirname(__DIR__) . '/templates/header.php';
?>
<?php
$portal = new Portal(Database::getInstance());
$pdo = Database::getInstance()->getConnection();

$studentPage = max(1, (int)($_GET['student_page'] ?? 1));
$paymentPage = max(1, (int)($_GET['payment_page'] ?? 1));
$studentQuery = trim($_GET['student_q'] ?? '');
$paymentQuery = trim($_GET['payment_q'] ?? '');
$perPage = 6;

// Dashboard stats
$baseStats = $portal->getDashboardStats();
$stats = [
  'total_students' => $baseStats['students'] ?? 0,
  'total_payments' => $baseStats['payments'] ?? 0,
  'verified_payments' => $baseStats['verified_payments'] ?? 0,
  'pending_payments' => max(0, ($baseStats['payments'] ?? 0) - ($baseStats['verified_payments'] ?? 0)),
  'id_cards' => 0,
  'certificates' => 0,
];

// Student table (server-side filtered + paginated)
$studentWhere = '';
$studentParams = [];
if ($studentQuery !== '') {
  $studentWhere = ' WHERE (u.full_name LIKE :student_q OR u.matric_number LIKE :student_q OR u.department LIKE :student_q OR u.level LIKE :student_q)';
  $studentParams['student_q'] = '%' . $studentQuery . '%';
}
$countStmt = $pdo->prepare('SELECT COUNT(*) FROM students s JOIN users u ON u.id = s.user_id' . $studentWhere);
$countStmt->execute($studentParams);
$totalStudents = (int)$countStmt->fetchColumn();
$studentPages = max(1, (int)ceil($totalStudents / $perPage));
$studentOffset = ($studentPage - 1) * $perPage;
$studentStmt = $pdo->prepare('SELECT s.id, u.full_name, u.matric_number, u.level, u.department, s.created_at, u.status, s.profile_photo FROM students s JOIN users u ON u.id = s.user_id' . $studentWhere . ' ORDER BY s.created_at DESC LIMIT :limit OFFSET :offset');
if ($studentQuery !== '') { $studentStmt->bindValue(':student_q', '%' . $studentQuery . '%'); }
$studentStmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$studentStmt->bindValue(':offset', $studentOffset, PDO::PARAM_INT);
$studentStmt->execute();
$recentStudents = $studentStmt->fetchAll();

// Payment table (server-side filtered + paginated)
$paymentWhere = '';
$paymentParams = [];
if ($paymentQuery !== '') {
  $paymentWhere = ' WHERE (u.full_name LIKE :payment_q OR p.payment_type LIKE :payment_q OR p.status LIKE :payment_q OR p.payment_reference LIKE :payment_q)';
  $paymentParams['payment_q'] = '%' . $paymentQuery . '%';
}
$paymentCountStmt = $pdo->prepare('SELECT COUNT(*) FROM payments p LEFT JOIN users u ON u.id = p.user_id' . $paymentWhere);
$paymentCountStmt->execute($paymentParams);
$totalPayments = (int)$paymentCountStmt->fetchColumn();
$paymentPages = max(1, (int)ceil($totalPayments / $perPage));
$paymentOffset = ($paymentPage - 1) * $perPage;
$paymentStmt = $pdo->prepare('SELECT p.*, u.full_name FROM payments p LEFT JOIN users u ON u.id = p.user_id' . $paymentWhere . ' ORDER BY p.created_at DESC LIMIT :limit OFFSET :offset');
if ($paymentQuery !== '') { $paymentStmt->bindValue(':payment_q', '%' . $paymentQuery . '%'); }
$paymentStmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$paymentStmt->bindValue(':offset', $paymentOffset, PDO::PARAM_INT);
$paymentStmt->execute();
$recentPayments = $paymentStmt->fetchAll();

// Recent announcements
$recentAnnouncements = $portal->getAnnouncementsList(6);

// Charts data: registrations per month (last 12 months)
$labels = [];
$regData = [];
$payCountData = [];
$paySumData = [];
$months = [];
$start = new DateTime('first day of -11 months');
for ($i = 0; $i < 12; $i++) {
  $months[] = $start->format('Y-m');
  $labels[] = $start->format('M Y');
  $start->modify('+1 month');
}
$inClause = implode("','", $months);

$regStmt = $pdo->prepare("SELECT DATE_FORMAT(s.created_at, '%Y-%m') AS ym, COUNT(*) AS cnt FROM students s WHERE DATE_FORMAT(s.created_at, '%Y-%m') IN ('" . $inClause . "') GROUP BY ym");
$regStmt->execute();
$regRows = $regStmt->fetchAll();
$regMap = [];
foreach ($regRows as $r) { $regMap[$r['ym']] = (int)$r['cnt']; }
foreach ($months as $m) { $regData[] = $regMap[$m] ?? 0; }

$payStmt = $pdo->prepare("SELECT DATE_FORMAT(created_at, '%Y-%m') AS ym, COUNT(*) AS cnt, COALESCE(SUM(amount),0) AS total FROM payments WHERE DATE_FORMAT(created_at, '%Y-%m') IN ('" . $inClause . "') GROUP BY ym");
$payStmt->execute();
$payRows = $payStmt->fetchAll();
$payMapCount = []; $payMapSum = [];
foreach ($payRows as $r) { $payMapCount[$r['ym']] = (int)$r['cnt']; $payMapSum[$r['ym']] = (float)$r['total']; }
foreach ($months as $m) { $payCountData[] = $payMapCount[$m] ?? 0; $paySumData[] = $payMapSum[$m] ?? 0; }

// Students by level
$levelStmt = $pdo->prepare('SELECT COALESCE(u.level, "Unspecified") AS level, COUNT(*) AS cnt FROM students s JOIN users u ON u.id = s.user_id GROUP BY u.level ORDER BY cnt DESC');
$levelStmt->execute();
$levelRows = $levelStmt->fetchAll();

// Live system status checks
$systemStatus = [
  'database' => ['label' => 'Database', 'status' => 'Online', 'detail' => 'Connected'],
  'server' => ['label' => 'Server', 'status' => 'Healthy', 'detail' => 'PHP ' . PHP_VERSION],
  'smtp' => ['label' => 'SMTP Email', 'status' => 'Configured', 'detail' => 'Mail settings available'],
  'storage' => ['label' => 'Storage', 'status' => 'Healthy', 'detail' => 'Usage monitored'],
  'backup' => ['label' => 'Backup', 'status' => 'Ready', 'detail' => 'Database dump ready'],
];
try {
  $pdo->query('SELECT 1');
} catch (Throwable $e) {
  $systemStatus['database']['status'] = 'Offline';
  $systemStatus['database']['detail'] = $e->getMessage();
}
$settings = $portal->getSettings();
if (empty($settings['smtp_host'] ?? '')) {
  $systemStatus['smtp']['status'] = 'Pending';
  $systemStatus['smtp']['detail'] = 'SMTP host not configured';
}
$storageTotal = @disk_total_space(__DIR__);
$storageFree = @disk_free_space(__DIR__);
if ($storageTotal && $storageFree) {
  $storagePercent = max(0, min(100, (int)round((($storageTotal - $storageFree) / $storageTotal) * 100)));
  $systemStatus['storage']['detail'] = $storagePercent . '% used';
  $systemStatus['storage']['status'] = $storagePercent > 85 ? 'Warning' : 'Healthy';
}
if (!file_exists(dirname(__DIR__) . '/database.sql')) {
  $systemStatus['backup']['status'] = 'Pending';
  $systemStatus['backup']['detail'] = 'Backup file missing';
}

$events = $portal->listEvents(4, 0);
$resources = $portal->listResources(6, 0);
$galleryImages = [
  asset('images/AI.png'),
  asset('images/logo/nacos-logo.jpeg'),
  asset('images/NACOS.jpeg'),
  asset('images/nacos-logo1.jpeg'),
];
$currentAcademicSession = $settings['current_academic_session'] ?? (date('Y') . '/' . (date('Y') + 1));

// Export data for JS
$dashData = [
  'labels' => $labels,
  'registrations' => $regData,
  'payments_count' => $payCountData,
  'payments_sum' => $paySumData,
  'students_by_level' => $levelRows,
  'recent_students' => $recentStudents,
  'recent_payments' => $recentPayments,
  'recent_announcements' => $recentAnnouncements,
  'system_status' => $systemStatus,
];
?>
<link rel="stylesheet" href="<?= asset('css/admin-dashboard.css') ?>">
<div class="admin-dashboard-app">
  <aside class="sidebar">
    <div class="sidebar-inner">
      <div class="brand">
        <div class="logo-glass">
          <img src="<?= asset('images/logo/logo.png') ?>" alt="NACOS FCAI" />
        </div>
        <div class="brand-text">NACOS FCAI</div>
      </div>
      <nav class="menu">
        <a href="<?= APP_URL ?>/admin/dashboard.php" class="menu-item active"><i data-lucide="home"></i><span>Dashboard</span></a>
        <a href="<?= APP_URL ?>/admin/students.php" class="menu-item"><i data-lucide="users"></i><span>Students</span></a>
        <a href="<?= APP_URL ?>/admin/payments.php" class="menu-item"><i data-lucide="credit-card"></i><span>Payments</span></a>
        <a href="<?= APP_URL ?>/admin/id-cards.php" class="menu-item"><i data-lucide="id"></i><span>ID Cards</span></a>
        <a href="<?= APP_URL ?>/admin/content.php" class="menu-item"><i data-lucide="book-open"></i><span>Resources</span></a>
        <a href="<?= APP_URL ?>/admin/announcements.php" class="menu-item"><i data-lucide="megaphone"></i><span>Announcements</span></a>
        <a href="<?= APP_URL ?>/admin/events.php" class="menu-item"><i data-lucide="calendar"></i><span>Events</span></a>
        <a href="<?= APP_URL ?>/gallery.php" class="menu-item"><i data-lucide="image"></i><span>Gallery</span></a>
        <a href="<?= APP_URL ?>/executives.php" class="menu-item"><i data-lucide="users"></i><span>Executives</span></a>
        <a href="<?= APP_URL ?>/programmes.php" class="menu-item"><i data-lucide="book"></i><span>Programmes</span></a>
        <a href="<?= APP_URL ?>/admin/email.php" class="menu-item"><i data-lucide="mail"></i><span>Email Centre</span></a>
        <a href="<?= APP_URL ?>/admin/reports.php" class="menu-item"><i data-lucide="bar-chart-2"></i><span>Reports</span></a>
        <a href="<?= APP_URL ?>/admin/settings.php" class="menu-item"><i data-lucide="settings"></i><span>Portal Settings</span></a>
        <a href="<?= APP_URL ?>/admin/profile.php" class="menu-item"><i data-lucide="user"></i><span>Profile</span></a>
        <a href="<?= APP_URL ?>/auth/logout.php" class="menu-item"><i data-lucide="log-out"></i><span>Logout</span></a>
      </nav>
      <div class="sidebar-footer">&copy; 2026 NACOS FCAI</div>
    </div>
  </aside>

  <header class="topbar">
    <div class="topbar-left">
      <button class="hamburger" aria-label="Toggle sidebar"><i data-lucide="menu"></i></button>
      <div class="welcome">
        <div class="welcome-title">Good Afternoon, Administrator <span class="wave">👋</span></div>
        <div class="welcome-sub">Welcome back to NACOS FCAI</div>
      </div>
    </div>
    <div class="topbar-center">
      <div class="search">
        <input type="search" placeholder="Search students, payments, announcements..." />
        <button><i data-lucide="search"></i></button>
      </div>
    </div>
    <div class="topbar-right">
      <div class="badge session">2025/2026</div>
      <div class="date"><?= date('D, M j, Y') ?></div>
      <button class="icon-btn"><i data-lucide="bell"></i><span class="badge-dot">3</span></button>
      <button class="icon-btn"><i data-lucide="mail"></i><span class="badge-dot blue">5</span></button>
      <div class="profile">
        <img src="<?= asset('images/profile-placeholder.png') ?>" alt="Admin" />
        <div class="profile-name">Administrator</div>
      </div>
    </div>
  </header>

  <main class="main">
    <section class="quick-actions">
      <button class="action add" data-action="add_student" data-bs-toggle="modal" data-bs-target="#quickActionModal"><i data-lucide="user-plus"></i><span>Add Student</span></button>
      <button class="action verify" data-action="verify_payment" data-bs-toggle="modal" data-bs-target="#quickActionModal"><i data-lucide="check-circle"></i><span>Verify Payment</span></button>
      <button class="action announce" data-action="post_announcement" data-bs-toggle="modal" data-bs-target="#quickActionModal"><i data-lucide="speaker"></i><span>Post Announcement</span></button>
      <button class="action event" data-action="create_event" data-bs-toggle="modal" data-bs-target="#quickActionModal"><i data-lucide="calendar-plus"></i><span>Create Event</span></button>
      <button class="action upload" data-action="upload_resource" data-bs-toggle="modal" data-bs-target="#quickActionModal"><i data-lucide="upload"></i><span>Upload Resource</span></button>
      <button class="action report" data-action="generate_reports" data-bs-toggle="modal" data-bs-target="#quickActionModal"><i data-lucide="file-text"></i><span>Generate Reports</span></button>
    </section>

    <section class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon"><i data-lucide="users"></i></div>
        <div class="stat-body">
          <div class="stat-number"><?= e($stats['total_students']) ?></div>
          <div class="stat-desc">Total Students</div>
        </div>
        <div class="stat-trend">+4.8%</div>
      </div>

      <div class="stat-card">
        <div class="stat-icon"><i data-lucide="credit-card"></i></div>
        <div class="stat-body">
          <div class="stat-number"><?= e($stats['total_payments']) ?></div>
          <div class="stat-desc">Total Payments</div>
        </div>
        <div class="stat-trend">+6.2%</div>
      </div>

      <div class="stat-card">
        <div class="stat-icon"><i data-lucide="check"></i></div>
        <div class="stat-body">
          <div class="stat-number"><?= e($stats['verified_payments']) ?></div>
          <div class="stat-desc">Verified Payments</div>
        </div>
        <div class="stat-trend">+2.1%</div>
      </div>

      <div class="stat-card">
        <div class="stat-icon"><i data-lucide="clock"></i></div>
        <div class="stat-body">
          <div class="stat-number"><?= e($stats['pending_payments']) ?></div>
          <div class="stat-desc">Pending Payments</div>
        </div>
        <div class="stat-trend">-1.4%</div>
      </div>

      <div class="stat-card">
        <div class="stat-icon"><i data-lucide="id"></i></div>
        <div class="stat-body">
          <div class="stat-number"><?= e($stats['id_cards']) ?></div>
          <div class="stat-desc">ID Cards Generated</div>
        </div>
        <div class="stat-trend">+8.0%</div>
      </div>

      <div class="stat-card">
        <div class="stat-icon"><i data-lucide="award"></i></div>
        <div class="stat-body">
          <div class="stat-number"><?= e($stats['certificates']) ?></div>
          <div class="stat-desc">Certificates Generated</div>
        </div>
        <div class="stat-trend">+3.3%</div>
      </div>
    </section>

    <section class="analytics">
      <div class="charts">
        <div class="chart-card">
          <h4>Student Registration Trend</h4>
          <canvas id="registrationsChart"></canvas>
        </div>
        <div class="chart-card">
          <h4>Payments Overview</h4>
          <canvas id="paymentsChart"></canvas>
        </div>
        <div class="chart-card">
          <h4>Students by Level</h4>
          <canvas id="levelChart"></canvas>
        </div>
      </div>
      <div class="side-widgets">
        <div class="widget card-announce">
          <h5>Recent Announcements</h5>
          <ul>
            <?php foreach ($recentAnnouncements as $announcement): ?>
              <li><strong><?= e($announcement['title'] ?? 'Announcement') ?></strong><span class="meta"><?= e(substr($announcement['created_at'] ?? '', 0, 10)) ?></span></li>
            <?php endforeach; ?>
          </ul>
          <a class="btn-link" href="<?= APP_URL ?>/admin/announcements.php">View All</a>
        </div>

        <div class="widget card-events">
          <h5>Upcoming Events</h5>
          <div class="event">
            <img src="<?= asset('images/AI.png') ?>" alt="Event" />
            <div class="event-body"><strong>Tech Workshop</strong><span class="meta">Oct 12 • Hall A</span></div>
          </div>
          <a class="btn-link" href="<?= APP_URL ?>/admin/events.php">Register</a>
        </div>

        <div class="widget system-status">
          <h5>System Status</h5>
          <ul>
            <?php foreach ($systemStatus as $key => $item): ?>
              <li><?= e($item['label']) ?>: <span class="<?= strtolower($item['status']) === 'online' || strtolower($item['status']) === 'healthy' || strtolower($item['status']) === 'ready' || strtolower($item['status']) === 'configured' ? 'ok' : (strtolower($item['status']) === 'warning' || strtolower($item['status']) === 'pending' ? 'warn' : 'offline') ?>"><?= e($item['status']) ?></span></li>
            <?php endforeach; ?>
          </ul>
        </div>
      </div>
    </section>

    <section class="tables">
      <div class="table-card">
        <div class="table-header">
          <h5>Student Management</h5>
          <form method="get" class="table-search">
            <input type="search" name="student_q" value="<?= e($studentQuery) ?>" placeholder="Search students" />
            <input type="hidden" name="student_page" value="1" />
            <button type="submit">Search</button>
          </form>
        </div>
        <div class="table-responsive">
          <table class="table">
            <thead>
              <tr><th>Passport</th><th>Member #</th><th>Student Name</th><th>Matric</th><th>Level</th><th>Department</th><th>Registered</th><th>Status</th><th>Actions</th></tr>
            </thead>
            <tbody>
              <?php foreach ($recentStudents as $s): ?>
                <tr>
                  <td>
                    <?php $photo = $s['profile_photo'] ?? ''; ?>
                    <?php if ($photo): ?><img src="<?= e(APP_URL . '/' . ltrim($photo, '/')) ?>" alt="Profile photo" class="mini-avatar"/><?php else: ?><span class="mini-avatar d-inline-flex align-items-center justify-content-center bg-light text-success fw-bold" aria-label="No profile photo">N/A</span><?php endif; ?>
                  </td>
                  <td><?= e($s['matric_number'] ?: ('MEM' . $s['id'])) ?></td>
                  <td><?= e($s['full_name']) ?></td>
                  <td><?= e($s['matric_number'] ?: '—') ?></td>
                  <td><?= e($s['level'] ?: '—') ?></td>
                  <td><?= e($s['department'] ?: '—') ?></td>
                  <td><?= e(substr($s['created_at'],0,10)) ?></td>
                  <td><span class="badge <?= strtolower($s['status'] ?? '') === 'active' ? 'green' : 'yellow' ?>"><?= e(ucfirst($s['status'] ?? 'active')) ?></span></td>
                  <td>
                    <button class="btn-sm">View</button>
                    <button class="btn-sm">Edit</button>
                    <button class="btn-sm danger">Delete</button>
                    <button class="btn-sm">Generate ID</button>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <div class="table-pagination">
          <a class="page-link" href="?student_page=<?= max(1, $studentPage - 1) ?>&student_q=<?= rawurlencode($studentQuery) ?>&payment_page=<?= $paymentPage ?>&payment_q=<?= rawurlencode($paymentQuery) ?>">Prev</a>
          <?php for ($page = 1; $page <= $studentPages; $page++): ?>
            <a class="page-link <?= $page === $studentPage ? 'active' : '' ?>" href="?student_page=<?= $page ?>&student_q=<?= rawurlencode($studentQuery) ?>&payment_page=<?= $paymentPage ?>&payment_q=<?= rawurlencode($paymentQuery) ?>"><?= $page ?></a>
          <?php endfor; ?>
          <a class="page-link" href="?student_page=<?= min($studentPages, $studentPage + 1) ?>&student_q=<?= rawurlencode($studentQuery) ?>&payment_page=<?= $paymentPage ?>&payment_q=<?= rawurlencode($paymentQuery) ?>">Next</a>
        </div>
      </div>

      <div class="table-card">
        <div class="table-header">
          <h5>Payment Management</h5>
          <form method="get" class="table-search">
            <input type="search" name="payment_q" value="<?= e($paymentQuery) ?>" placeholder="Search payments" />
            <input type="hidden" name="payment_page" value="1" />
            <button type="submit">Search</button>
          </form>
        </div>
        <div class="table-responsive">
          <table class="table">
            <thead>
              <tr><th>Student</th><th>Type</th><th>Amount</th><th>Date</th><th>Status</th><th>Receipt</th><th>Actions</th></tr>
            </thead>
            <tbody>
              <?php foreach ($recentPayments as $p): ?>
              <tr>
                <td><?= e($p['full_name'] ?? 'Student') ?></td>
                <td><?= e($p['payment_type'] ?? 'Membership') ?></td>
                <td>₦<?= number_format($p['amount'] ?? 0) ?></td>
                <td><?= e(substr($p['created_at'] ?? '',0,10)) ?></td>
                <td><span class="badge <?= ($p['status'] === 'verified') ? 'green' : 'yellow' ?>"><?= e(ucfirst($p['status'] ?? 'pending')) ?></span></td>
                <td><?php if (!empty($p['payment_proof'])): ?><a href="<?= e(asset($p['payment_proof'])) ?>" class="link">Receipt</a><?php else: ?>—<?php endif; ?></td>
                <td><button class="btn-sm success">Verify</button> <button class="btn-sm danger">Reject</button></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <div class="table-pagination">
          <a class="page-link" href="?payment_page=<?= max(1, $paymentPage - 1) ?>&payment_q=<?= rawurlencode($paymentQuery) ?>&student_page=<?= $studentPage ?>&student_q=<?= rawurlencode($studentQuery) ?>">Prev</a>
          <?php for ($page = 1; $page <= $paymentPages; $page++): ?>
            <a class="page-link <?= $page === $paymentPage ? 'active' : '' ?>" href="?payment_page=<?= $page ?>&payment_q=<?= rawurlencode($paymentQuery) ?>&student_page=<?= $studentPage ?>&student_q=<?= rawurlencode($studentQuery) ?>"><?= $page ?></a>
          <?php endfor; ?>
          <a class="page-link" href="?payment_page=<?= min($paymentPages, $paymentPage + 1) ?>&payment_q=<?= rawurlencode($paymentQuery) ?>&student_page=<?= $studentPage ?>&student_q=<?= rawurlencode($studentQuery) ?>">Next</a>
        </div>
      </div>
    </section>

    <footer class="admin-footer">&copy; 2026 NACOS FCAI • Designed & Developed by Ogoh Joshua Chukwuemeka</footer>
  </main>
</div>

<div class="modal fade" id="quickActionModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="quickActionTitle">Quick action</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="quickActionBody"></div>
      <div class="modal-footer">
        <a id="quickActionLink" class="btn btn-success" href="#">Open page</a>
      </div>
    </div>
  </div>
</div>

<script>
window.DASH_DATA = <?= json_encode($dashData, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT) ?>;
</script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="<?= asset('js/admin-dashboard.js') ?>"></script>
<?php require_once dirname(__DIR__) . '/templates/footer.php'; ?>
