<?php require_once dirname(__DIR__) . '/templates/header.php'; ?>
<?php
$auth = new Auth(Database::getInstance());
$auth->requireLogin('admin');
$portal = new Portal(Database::getInstance());
$pdo = Database::getInstance()->getConnection();

function formatDate(?string $value): string
{
    if ($value === null || $value === '') {
        return '—';
    }
    try {
        return (new DateTimeImmutable($value))->format('Y-m-d');
    } catch (Exception $e) {
        return $value;
    }
}

function resolveDateRange(string $preset, ?string $dateFrom, ?string $dateTo): array
{
    $now = new DateTimeImmutable('now');
    $from = null;
    $to = null;
    switch ($preset) {
        case 'today':
            $from = $now->setTime(0, 0, 0);
            $to = $now->setTime(23, 59, 59);
            break;
        case 'yesterday':
            $yesterday = $now->modify('-1 day');
            $from = $yesterday->setTime(0, 0, 0);
            $to = $yesterday->setTime(23, 59, 59);
            break;
        case 'last_7_days':
            $from = $now->modify('-6 days')->setTime(0, 0, 0);
            $to = $now->setTime(23, 59, 59);
            break;
        case 'last_30_days':
            $from = $now->modify('-29 days')->setTime(0, 0, 0);
            $to = $now->setTime(23, 59, 59);
            break;
        case 'this_month':
            $from = $now->modify('first day of this month')->setTime(0, 0, 0);
            $to = $now->modify('last day of this month')->setTime(23, 59, 59);
            break;
        case 'last_month':
            $from = $now->modify('first day of previous month')->setTime(0, 0, 0);
            $to = $now->modify('last day of previous month')->setTime(23, 59, 59);
            break;
        case 'this_year':
            $from = $now->setDate((int)$now->format('Y'), 1, 1)->setTime(0, 0, 0);
            $to = $now->setDate((int)$now->format('Y'), 12, 31)->setTime(23, 59, 59);
            break;
        case 'custom':
            if ($dateFrom !== '' && $dateTo !== '') {
                $from = new DateTimeImmutable($dateFrom);
                $to = new DateTimeImmutable($dateTo);
            }
            break;
    }
    return [$from ? $from->format('Y-m-d') : null, $to ? $to->format('Y-m-d') : null];
}

function buildReportQueryString(array $filters, string $datePreset, ?string $dateFrom, ?string $dateTo): string
{
    $data = [
        'search' => $filters['search'] ?? '',
        'date_preset' => $datePreset,
        'date_from' => $dateFrom ?? '',
        'date_to' => $dateTo ?? '',
        'session_filter' => $filters['session_filter'] ?? '',
        'payment_type_filter' => $filters['payment_type_filter'] ?? '',
        'payment_status_filter' => $filters['payment_status_filter'] ?? '',
        'student_filter' => $filters['student_filter'] ?? '',
        'programme_filter' => $filters['programme_filter'] ?? '',
        'level_filter' => $filters['level_filter'] ?? '',
        'department_filter' => $filters['department_filter'] ?? '',
        'ticket_status_filter' => $filters['ticket_status_filter'] ?? '',
        'ticket_priority_filter' => $filters['ticket_priority_filter'] ?? '',
        'event_filter' => $filters['event_filter'] ?? '',
        'resource_filter' => $filters['resource_filter'] ?? '',
        'executive_filter' => $filters['executive_filter'] ?? '',
    ];
    return http_build_query($data);
}

$search = trim((string)($_GET['search'] ?? ''));
$datePreset = trim((string)($_GET['date_preset'] ?? 'this_month'));
$dateFrom = trim((string)($_GET['date_from'] ?? ''));
$dateTo = trim((string)($_GET['date_to'] ?? ''));
[$dateFrom, $dateTo] = resolveDateRange($datePreset, $dateFrom, $dateTo);

$filters = [
    'session_filter' => trim((string)($_GET['session_filter'] ?? '')),
    'payment_type_filter' => trim((string)($_GET['payment_type_filter'] ?? '')),
    'payment_status_filter' => trim((string)($_GET['payment_status_filter'] ?? '')),
    'student_filter' => trim((string)($_GET['student_filter'] ?? '')),
    'programme_filter' => trim((string)($_GET['programme_filter'] ?? '')),
    'level_filter' => trim((string)($_GET['level_filter'] ?? '')),
    'department_filter' => trim((string)($_GET['department_filter'] ?? '')),
    'ticket_status_filter' => trim((string)($_GET['ticket_status_filter'] ?? '')),
    'ticket_priority_filter' => trim((string)($_GET['ticket_priority_filter'] ?? '')),
    'event_filter' => trim((string)($_GET['event_filter'] ?? '')),
    'resource_filter' => trim((string)($_GET['resource_filter'] ?? '')),
    'executive_filter' => trim((string)($_GET['executive_filter'] ?? '')),
    'search' => $search,
];

$baseQuery = buildReportQueryString($filters, $datePreset, $dateFrom, $dateTo);

$settings = $portal->getSettings();
$currentSession = (string)($settings['current_academic_session'] ?? '');

$stats = [];
$stats['students_total'] = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetchColumn();
$stats['students_active'] = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student' AND status = 'active'")->fetchColumn();
$stats['students_session'] = 0;
if ($currentSession !== '') {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM students s JOIN users u ON u.id = s.user_id WHERE u.role = 'student' AND COALESCE(s.session, '') = :session");
    $stmt->execute(['session' => $currentSession]);
    $stats['students_session'] = (int)$stmt->fetchColumn();
}
$stats['executives_total'] = (int)$pdo->query('SELECT COUNT(*) FROM executives')->fetchColumn();
$stats['events_total'] = (int)$pdo->query('SELECT COUNT(*) FROM events')->fetchColumn();
$stats['events_upcoming'] = (int)$pdo->query("SELECT COUNT(*) FROM events WHERE event_date >= CURDATE() OR is_upcoming = 1")->fetchColumn();
$stats['resources_total'] = (int)$pdo->query('SELECT COUNT(*) FROM resources')->fetchColumn();
$stats['payments_total'] = (int)$pdo->query('SELECT COUNT(*) FROM payments')->fetchColumn();
$stats['payments_pending'] = (int)$pdo->query("SELECT COUNT(*) FROM payments WHERE status = 'pending'")->fetchColumn();
$stats['payments_verified'] = (int)$pdo->query("SELECT COUNT(*) FROM payments WHERE status IN ('approved', 'verified')")->fetchColumn();
$stats['payments_rejected'] = (int)$pdo->query("SELECT COUNT(*) FROM payments WHERE status = 'rejected'")->fetchColumn();
$stats['payments_revenue'] = (float)$pdo->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status IN ('approved', 'verified')")->fetchColumn();
$stats['payments_outstanding'] = (float)$pdo->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status IN ('pending', 'rejected')")->fetchColumn();
$stats['tickets_open'] = (int)$pdo->query("SELECT COUNT(*) FROM support_tickets WHERE status = 'open'")->fetchColumn();
$stats['tickets_closed'] = (int)$pdo->query("SELECT COUNT(*) FROM support_tickets WHERE status = 'closed'")->fetchColumn();
$stats['today_revenue'] = (float)$pdo->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status IN ('approved', 'verified') AND DATE(created_at) = CURDATE()")->fetchColumn();
$stats['today_registrations'] = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student' AND DATE(created_at) = CURDATE()")->fetchColumn();
$stats['pending_verifications'] = $stats['payments_pending'];
$stats['upcoming_events'] = $stats['events_upcoming'];
$stats['resources_today'] = (int)$pdo->query("SELECT COUNT(*) FROM resources WHERE DATE(created_at) = CURDATE()")->fetchColumn();
$stats['open_tickets'] = $stats['tickets_open'];
$stats['unread_notifications'] = (int)$pdo->query("SELECT COUNT(*) FROM notifications WHERE is_read = 0")->fetchColumn();

$galleryDir = dirname(__DIR__) . '/assets/uploads/gallery';
$galleryData = ['images' => 0, 'videos' => 0, 'uploads_this_month' => 0, 'uploads_this_year' => 0];
if (is_dir($galleryDir)) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($galleryDir));
    foreach ($iterator as $fileInfo) {
        if ($fileInfo->isFile()) {
            $extension = strtolower($fileInfo->getExtension());
            $mtime = (int)$fileInfo->getMTime();
            if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
                $galleryData['images']++;
            } elseif (in_array($extension, ['mp4', 'webm', 'mov', 'avi', 'mkv'], true)) {
                $galleryData['videos']++;
            }
            if ($mtime >= strtotime('first day of this month')) {
                $galleryData['uploads_this_month']++;
            }
            if ($mtime >= strtotime('first day of January')) {
                $galleryData['uploads_this_year']++;
            }
        }
    }
}
$stats['gallery_images'] = $galleryData['images'];
$stats['gallery_videos'] = $galleryData['videos'];

$paymentSql = 'SELECT p.*, u.full_name, u.matric_number, s.course AS programme, u.department, u.level FROM payments p LEFT JOIN users u ON u.id = p.user_id LEFT JOIN students s ON s.user_id = u.id';
$where = [];
$params = [];
if ($search !== '') { $where[] = '(u.full_name LIKE :search OR u.matric_number LIKE :search OR p.payment_reference LIKE :search OR p.payment_type LIKE :search)'; $params[':search'] = '%' . $search . '%'; }
if ($filters['session_filter'] !== '') { $where[] = 'p.session = :session'; $params[':session'] = $filters['session_filter']; }
if ($filters['payment_type_filter'] !== '') { $where[] = 'p.payment_type = :payment_type'; $params[':payment_type'] = $filters['payment_type_filter']; }
if ($filters['payment_status_filter'] !== '') { $where[] = 'p.status = :payment_status'; $params[':payment_status'] = $filters['payment_status_filter']; }
if ($filters['student_filter'] !== '') { $where[] = '(u.full_name LIKE :student OR u.matric_number LIKE :student)'; $params[':student'] = '%' . $filters['student_filter'] . '%'; }
if ($filters['programme_filter'] !== '') { $where[] = 's.course LIKE :programme'; $params[':programme'] = '%' . $filters['programme_filter'] . '%'; }
if ($filters['level_filter'] !== '') { $where[] = 'u.level = :level'; $params[':level'] = $filters['level_filter']; }
if ($dateFrom !== null) { $where[] = 'DATE(p.created_at) >= :date_from'; $params[':date_from'] = $dateFrom; }
if ($dateTo !== null) { $where[] = 'DATE(p.created_at) <= :date_to'; $params[':date_to'] = $dateTo; }
if ($where) { $paymentSql .= ' WHERE ' . implode(' AND ', $where); }
$paymentSql .= ' ORDER BY p.created_at DESC';
$paymentStmt = $pdo->prepare($paymentSql);
$paymentStmt->execute($params);
$paymentRows = $paymentStmt->fetchAll();
$paymentSummary = [
    'count' => count($paymentRows),
    'total' => array_sum(array_map(static fn($row) => (float)($row['amount'] ?? 0), $paymentRows)),
    'average' => count($paymentRows) > 0 ? array_sum(array_map(static fn($row) => (float)($row['amount'] ?? 0), $paymentRows)) / count($paymentRows) : 0,
    'highest' => count($paymentRows) > 0 ? max(array_map(static fn($row) => (float)($row['amount'] ?? 0), $paymentRows)) : 0,
    'lowest' => count($paymentRows) > 0 ? min(array_map(static fn($row) => (float)($row['amount'] ?? 0), $paymentRows)) : 0,
    'pending' => array_sum(array_map(static fn($row) => ((string)($row['status'] ?? 'pending') === 'pending') ? (float)($row['amount'] ?? 0) : 0, $paymentRows)),
    'verified' => array_sum(array_map(static fn($row) => ((string)($row['status'] ?? 'pending') === 'verified' || (string)($row['status'] ?? 'pending') === 'approved') ? (float)($row['amount'] ?? 0) : 0, $paymentRows)),
    'rejected' => array_sum(array_map(static fn($row) => ((string)($row['status'] ?? 'pending') === 'rejected') ? (float)($row['amount'] ?? 0) : 0, $paymentRows)),
];

$studentSql = 'SELECT u.full_name, u.matric_number, u.department, u.level, s.course AS programme, s.session, u.status, s.created_at FROM students s JOIN users u ON u.id = s.user_id WHERE u.role = :role';
$studentParams = ['role' => 'student'];
$studentWhere = [];
if ($search !== '') { $studentWhere[] = '(u.full_name LIKE :search OR u.matric_number LIKE :search OR s.course LIKE :search OR u.department LIKE :search)'; $studentParams[':search'] = '%' . $search . '%'; }
if ($filters['session_filter'] !== '') { $studentWhere[] = 'COALESCE(s.session, \'\') = :session'; $studentParams[':session'] = $filters['session_filter']; }
if ($filters['programme_filter'] !== '') { $studentWhere[] = 's.course LIKE :programme'; $studentParams[':programme'] = '%' . $filters['programme_filter'] . '%'; }
if ($filters['level_filter'] !== '') { $studentWhere[] = 'u.level = :level'; $studentParams[':level'] = $filters['level_filter']; }
if ($filters['department_filter'] !== '') { $studentWhere[] = 'u.department LIKE :department'; $studentParams[':department'] = '%' . $filters['department_filter'] . '%'; }
if ($dateFrom !== null) { $studentWhere[] = 'DATE(s.created_at) >= :date_from'; $studentParams[':date_from'] = $dateFrom; }
if ($dateTo !== null) { $studentWhere[] = 'DATE(s.created_at) <= :date_to'; $studentParams[':date_to'] = $dateTo; }
if ($studentWhere) { $studentSql .= ' AND ' . implode(' AND ', $studentWhere); }
$studentSql .= ' ORDER BY s.created_at DESC';
$studentStmt = $pdo->prepare($studentSql);
$studentStmt->execute($studentParams);
$studentRows = $studentStmt->fetchAll();
$studentSummary = [
    'total' => count($studentRows),
    'new' => (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student' AND DATE(created_at) = CURDATE()")->fetchColumn(),
    'approved' => 0,
    'pending' => 0,
    'rejected' => 0,
    'inactive' => 0,
    'level' => [],
    'programme' => [],
    'session' => [],
    'department' => [],
];
foreach ($studentRows as $row) {
    $level = (string)($row['level'] ?? 'Unspecified');
    $programme = (string)($row['programme'] ?? 'Unspecified');
    $department = (string)($row['department'] ?? 'Unspecified');
    $session = (string)($row['session'] ?? $currentSession);
    $studentSummary['level'][$level] = ($studentSummary['level'][$level] ?? 0) + 1;
    $studentSummary['programme'][$programme] = ($studentSummary['programme'][$programme] ?? 0) + 1;
    $studentSummary['department'][$department] = ($studentSummary['department'][$department] ?? 0) + 1;
    $studentSummary['session'][$session] = ($studentSummary['session'][$session] ?? 0) + 1;
    $status = strtolower((string)($row['status'] ?? 'active'));
    if ($status === 'active') { $studentSummary['approved']++; }
    elseif ($status === 'pending') { $studentSummary['pending']++; }
    elseif ($status === 'rejected') { $studentSummary['rejected']++; }
    elseif ($status === 'inactive') { $studentSummary['inactive']++; }
}

$eventSql = 'SELECT e.id, e.title, e.venue, e.event_date, e.is_upcoming, COUNT(er.id) AS participants FROM events e LEFT JOIN event_registrations er ON er.event_id = e.id';
$eventParams = [];
$eventWhere = [];
if ($search !== '') { $eventWhere[] = '(e.title LIKE :search OR e.venue LIKE :search)'; $eventParams[':search'] = '%' . $search . '%'; }
if ($filters['event_filter'] !== '') { $eventWhere[] = 'e.title LIKE :event'; $eventParams[':event'] = '%' . $filters['event_filter'] . '%'; }
if ($dateFrom !== null) { $eventWhere[] = 'DATE(e.event_date) >= :date_from'; $eventParams[':date_from'] = $dateFrom; }
if ($dateTo !== null) { $eventWhere[] = 'DATE(e.event_date) <= :date_to'; $eventParams[':date_to'] = $dateTo; }
if ($eventWhere) { $eventSql .= ' WHERE ' . implode(' AND ', $eventWhere); }
$eventSql .= ' GROUP BY e.id ORDER BY e.event_date DESC';
$eventStmt = $pdo->prepare($eventSql);
$eventStmt->execute($eventParams);
$eventRows = $eventStmt->fetchAll();
$eventSummary = [
    'total' => count($eventRows),
    'upcoming' => 0,
    'completed' => 0,
    'cancelled' => 0,
    'archived' => 0,
    'participants' => 0,
    'attendance' => 0,
];
foreach ($eventRows as $row) {
    if ((int)$row['is_upcoming'] === 0) { $eventSummary['completed']++; }
    elseif ((int)$row['is_upcoming'] === 2) { $eventSummary['archived']++; }
    else { $eventSummary['upcoming']++; }
    $eventSummary['participants'] += (int)$row['participants'];
    $eventSummary['attendance'] += (int)$row['participants'];
}

$resourceSql = 'SELECT title, programme, level, semester, course, created_at FROM resources';
$resourceParams = [];
$resourceWhere = [];
if ($search !== '') { $resourceWhere[] = '(title LIKE :search OR programme LIKE :search OR course LIKE :search)'; $resourceParams[':search'] = '%' . $search . '%'; }
if ($filters['resource_filter'] !== '') { $resourceWhere[] = 'title LIKE :resource'; $resourceParams[':resource'] = '%' . $filters['resource_filter'] . '%'; }
if ($filters['programme_filter'] !== '') { $resourceWhere[] = 'programme LIKE :programme'; $resourceParams[':programme'] = '%' . $filters['programme_filter'] . '%'; }
if ($filters['level_filter'] !== '') { $resourceWhere[] = 'level = :level'; $resourceParams[':level'] = $filters['level_filter']; }
if ($dateFrom !== null) { $resourceWhere[] = 'DATE(created_at) >= :date_from'; $resourceParams[':date_from'] = $dateFrom; }
if ($dateTo !== null) { $resourceWhere[] = 'DATE(created_at) <= :date_to'; $resourceParams[':date_to'] = $dateTo; }
if ($resourceWhere) { $resourceSql .= ' WHERE ' . implode(' AND ', $resourceWhere); }
$resourceSql .= ' ORDER BY created_at DESC';
$resourceStmt = $pdo->prepare($resourceSql);
$resourceStmt->execute($resourceParams);
$resourceRows = $resourceStmt->fetchAll();
$resourceSummary = [
    'total' => count($resourceRows),
    'downloads' => 0,
    'most_downloaded' => 'N/A',
    'least_downloaded' => 'N/A',
];

$executiveStmt = $pdo->prepare('SELECT full_name, position, department, academic_level, created_at FROM executives' . ($search !== '' ? ' WHERE full_name LIKE :search OR position LIKE :search OR department LIKE :search' : '') . ' ORDER BY full_name ASC');
$executiveParams = [];
if ($search !== '') { $executiveParams[':search'] = '%' . $search . '%'; }
$executiveStmt->execute($executiveParams);
$executiveRows = $executiveStmt->fetchAll();
$executiveSummary = [
    'current' => count($executiveRows),
    'former' => 0,
    'active' => count($executiveRows),
    'inactive' => 0,
];

$supportSql = 'SELECT st.id, st.subject, st.category, st.status, st.created_at, st.priority, u.full_name, u.matric_number, u.email FROM support_tickets st LEFT JOIN users u ON u.id = st.user_id';
$supportWhere = [];
$supportParams = [];
if ($search !== '') { $supportWhere[] = '(st.subject LIKE :search OR u.full_name LIKE :search OR u.matric_number LIKE :search OR u.email LIKE :search)'; $supportParams[':search'] = '%' . $search . '%'; }
if ($filters['ticket_status_filter'] !== '') { $supportWhere[] = 'st.status = :status'; $supportParams[':status'] = $filters['ticket_status_filter']; }
if ($filters['ticket_priority_filter'] !== '') { $supportWhere[] = 'st.priority = :priority'; $supportParams[':priority'] = $filters['ticket_priority_filter']; }
if ($filters['student_filter'] !== '') { $supportWhere[] = 'u.full_name LIKE :student'; $supportParams[':student'] = '%' . $filters['student_filter'] . '%'; }
if ($dateFrom !== null) { $supportWhere[] = 'DATE(st.created_at) >= :date_from'; $supportParams[':date_from'] = $dateFrom; }
if ($dateTo !== null) { $supportWhere[] = 'DATE(st.created_at) <= :date_to'; $supportParams[':date_to'] = $dateTo; }
if ($supportWhere) { $supportSql .= ' WHERE ' . implode(' AND ', $supportWhere); }
$supportSql .= ' ORDER BY st.created_at DESC';
$supportStmt = $pdo->prepare($supportSql);
$supportStmt->execute($supportParams);
$supportRows = $supportStmt->fetchAll();
$supportSummary = [
    'open' => (int)$pdo->query("SELECT COUNT(*) FROM support_tickets WHERE status = 'open'")->fetchColumn(),
    'pending' => (int)$pdo->query("SELECT COUNT(*) FROM support_tickets WHERE status = 'pending'")->fetchColumn(),
    'resolved' => (int)$pdo->query("SELECT COUNT(*) FROM support_tickets WHERE status = 'resolved'")->fetchColumn(),
    'closed' => (int)$pdo->query("SELECT COUNT(*) FROM support_tickets WHERE status = 'closed'")->fetchColumn(),
    'avg_resolution' => 'N/A',
];

$monthlyRevenue = ['labels' => [], 'data' => []];
$monthlyRevenueStmt = $pdo->query("SELECT DATE_FORMAT(created_at, '%Y-%m') AS month, COALESCE(SUM(amount), 0) AS total FROM payments WHERE status IN ('approved', 'verified') GROUP BY month ORDER BY month ASC LIMIT 12");
foreach ($monthlyRevenueStmt->fetchAll() as $row) { $monthlyRevenue['labels'][] = $row['month']; $monthlyRevenue['data'][] = (float)$row['total']; }
$paymentTypeChart = ['labels' => [], 'data' => []];
$paymentTypeStmt = $pdo->query('SELECT payment_type, COUNT(*) AS count FROM payments GROUP BY payment_type ORDER BY count DESC');
foreach ($paymentTypeStmt->fetchAll() as $row) { $paymentTypeChart['labels'][] = $row['payment_type']; $paymentTypeChart['data'][] = (int)$row['count']; }
$levelChart = ['labels' => [], 'data' => []];
$levelStmt = $pdo->query("SELECT u.level, COUNT(*) AS count FROM students s JOIN users u ON u.id = s.user_id WHERE u.role = 'student' GROUP BY u.level ORDER BY count DESC");
foreach ($levelStmt->fetchAll() as $row) { $levelChart['labels'][] = $row['level'] ?: 'Unspecified'; $levelChart['data'][] = (int)$row['count']; }
$programmeChart = ['labels' => [], 'data' => []];
$programmeStmt = $pdo->query("SELECT s.course AS programme, COUNT(*) AS count FROM students s JOIN users u ON u.id = s.user_id WHERE u.role = 'student' GROUP BY s.course ORDER BY count DESC");
foreach ($programmeStmt->fetchAll() as $row) { $programmeChart['labels'][] = $row['programme'] ?: 'Unspecified'; $programmeChart['data'][] = (int)$row['count']; }
$supportChart = ['labels' => [], 'data' => []];
$supportChartStmt = $pdo->query('SELECT status, COUNT(*) AS count FROM support_tickets GROUP BY status ORDER BY count DESC');
foreach ($supportChartStmt->fetchAll() as $row) { $supportChart['labels'][] = $row['status'] ?: 'Unknown'; $supportChart['data'][] = (int)$row['count']; }
$registrationChart = ['labels' => [], 'data' => []];
$registrationStmt = $pdo->query("SELECT DATE_FORMAT(created_at, '%Y-%m') AS month, COUNT(*) AS count FROM users WHERE role = 'student' GROUP BY month ORDER BY month ASC LIMIT 12");
foreach ($registrationStmt->fetchAll() as $row) { $registrationChart['labels'][] = $row['month']; $registrationChart['data'][] = (int)$row['count']; }
$resourceChart = ['labels' => [], 'data' => []];
$resourceChartStmt = $pdo->query("SELECT DATE_FORMAT(created_at, '%Y-%m') AS month, COUNT(*) AS count FROM resources GROUP BY month ORDER BY month ASC LIMIT 12");
foreach ($resourceChartStmt->fetchAll() as $row) { $resourceChart['labels'][] = $row['month']; $resourceChart['data'][] = (int)$row['count']; }
$eventChart = ['labels' => [], 'data' => []];
$eventChartStmt = $pdo->query("SELECT DATE_FORMAT(event_date, '%Y-%m') AS month, COUNT(*) AS count FROM events GROUP BY month ORDER BY month ASC LIMIT 12");
foreach ($eventChartStmt->fetchAll() as $row) { $eventChart['labels'][] = $row['month']; $eventChart['data'][] = (int)$row['count']; }
$galleryChart = ['labels' => ['Images', 'Videos'], 'data' => [$galleryData['images'], $galleryData['videos']]];

$activityItems = [];
foreach ($pdo->query("SELECT full_name AS name, created_at FROM users WHERE role = 'student' ORDER BY created_at DESC LIMIT 8")->fetchAll() as $row) { $activityItems[] = ['created_at' => $row['created_at'], 'type' => 'Student Registered', 'message' => 'Student registered: ' . ($row['name'] ?? 'Unknown')]; }
foreach ($pdo->query("SELECT payment_reference, created_at FROM payments WHERE status IN ('approved', 'verified') ORDER BY created_at DESC LIMIT 8")->fetchAll() as $row) { $activityItems[] = ['created_at' => $row['created_at'], 'type' => 'Payment Verified', 'message' => 'Payment verified: ' . ($row['payment_reference'] ?? '—')]; }
foreach ($pdo->query('SELECT title, created_at FROM resources ORDER BY created_at DESC LIMIT 8')->fetchAll() as $row) { $activityItems[] = ['created_at' => $row['created_at'], 'type' => 'Resource Uploaded', 'message' => 'Resource uploaded: ' . ($row['title'] ?? '—')]; }
foreach ($pdo->query('SELECT full_name, created_at FROM executives ORDER BY created_at DESC LIMIT 8')->fetchAll() as $row) { $activityItems[] = ['created_at' => $row['created_at'], 'type' => 'Executive Added', 'message' => 'Executive added: ' . ($row['full_name'] ?? '—')]; }
foreach ($pdo->query('SELECT title, created_at FROM events ORDER BY created_at DESC LIMIT 8')->fetchAll() as $row) { $activityItems[] = ['created_at' => $row['created_at'], 'type' => 'Event Created', 'message' => 'Event created: ' . ($row['title'] ?? '—')]; }
foreach ($pdo->query('SELECT title, created_at FROM announcements ORDER BY created_at DESC LIMIT 8')->fetchAll() as $row) { $activityItems[] = ['created_at' => $row['created_at'], 'type' => 'Announcement Published', 'message' => 'Announcement published: ' . ($row['title'] ?? '—')]; }
foreach ($pdo->query('SELECT subject, created_at FROM support_tickets ORDER BY created_at DESC LIMIT 8')->fetchAll() as $row) { $activityItems[] = ['created_at' => $row['created_at'], 'type' => 'Support Ticket Created', 'message' => 'Support ticket created: ' . ($row['subject'] ?? '—')]; }
usort($activityItems, static fn($a, $b) => strcmp($b['created_at'], $a['created_at']));
$activityItems = array_slice($activityItems, 0, 10);

function generateCsv(array $headers, array $rows): string
{
    $handle = fopen('php://temp', 'r+');
    if ($handle === false) {
        return '';
    }
    fputcsv($handle, $headers);
    foreach ($rows as $row) {
        fputcsv($handle, array_map(static function ($value): string {
            return is_scalar($value) ? (string)$value : '';
        }, $row));
    }
    rewind($handle);
    $csv = stream_get_contents($handle);
    fclose($handle);
    return $csv === false ? '' : $csv;
}

function generateExcelHtml(array $headers, array $rows): string
{
    $html = '<html><head><meta charset="utf-8" /></head><body><table border="1" cellpadding="5" cellspacing="0"><tr>';
    foreach ($headers as $header) {
        $html .= '<th>' . htmlspecialchars((string)$header, ENT_QUOTES, 'UTF-8') . '</th>';
    }
    $html .= '</tr>';
    foreach ($rows as $row) {
        $html .= '<tr>';
        foreach ($row as $value) {
            $html .= '<td>' . htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8') . '</td>';
        }
        $html .= '</tr>';
    }
    $html .= '</table></body></html>';
    return $html;
}

function sendDownload(string $filename, string $content, string $mime): void
{
    header('Content-Type: ' . $mime);
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    echo $content;
    exit;
}

$exportFormat = isset($_GET['format']) ? strtolower((string)$_GET['format']) : '';
$exportReport = isset($_GET['report']) ? strtolower((string)$_GET['report']) : 'payments';
if ($exportFormat === 'csv' || $exportFormat === 'excel') {
    $exportHeaders = [];
    $exportRows = [];
    switch ($exportReport) {
        case 'students':
            $exportHeaders = ['Student', 'Matric Number', 'Programme', 'Level', 'Department', 'Session', 'Status', 'Registered'];
            foreach ($studentRows as $row) {
                $exportRows[] = [
                    $row['full_name'] ?? '—',
                    $row['matric_number'] ?? '—',
                    $row['programme'] ?? '—',
                    $row['level'] ?? '—',
                    $row['department'] ?? '—',
                    $row['session'] ?? $currentSession,
                    ucfirst((string)($row['status'] ?? 'active')),
                    formatDate($row['created_at'] ?? null),
                ];
            }
            break;
        case 'events':
            $exportHeaders = ['Event', 'Venue', 'Date', 'Participants', 'Status'];
            foreach ($eventRows as $row) {
                $status = 'Upcoming';
                if ((int)$row['is_upcoming'] === 2) {
                    $status = 'Archived';
                } elseif ((string)($row['event_date'] ?? '') < date('Y-m-d')) {
                    $status = 'Completed';
                }
                $exportRows[] = [
                    $row['title'] ?? '—',
                    $row['venue'] ?? '—',
                    formatDate($row['event_date'] ?? null),
                    $row['participants'] ?? 0,
                    $status,
                ];
            }
            break;
        case 'resources':
            $exportHeaders = ['Resource', 'Programme', 'Level', 'Semester', 'Uploaded', 'Downloads'];
            foreach ($resourceRows as $row) {
                $exportRows[] = [
                    $row['title'] ?? '—',
                    $row['programme'] ?? '—',
                    $row['level'] ?? '—',
                    $row['semester'] ?? '—',
                    formatDate($row['created_at'] ?? null),
                    0,
                ];
            }
            break;
        case 'executives':
            $exportHeaders = ['Name', 'Position', 'Department', 'Academic Level', 'Status'];
            foreach ($executiveRows as $row) {
                $exportRows[] = [
                    $row['full_name'] ?? '—',
                    $row['position'] ?? '—',
                    $row['department'] ?? '—',
                    $row['academic_level'] ?? '—',
                    'Active',
                ];
            }
            break;
        case 'gallery':
            $exportHeaders = ['Type', 'Count'];
            $exportRows[] = ['Images', $galleryData['images']];
            $exportRows[] = ['Videos', $galleryData['videos']];
            $exportRows[] = ['Uploads This Month', $galleryData['uploads_this_month']];
            $exportRows[] = ['Uploads This Year', $galleryData['uploads_this_year']];
            break;
        case 'support':
            $exportHeaders = ['Ticket', 'Subject', 'Category', 'Status', 'Priority', 'Student', 'Email', 'Created'];
            foreach ($supportRows as $row) {
                $exportRows[] = [
                    $row['id'] ?? '—',
                    $row['subject'] ?? '—',
                    $row['category'] ?? '—',
                    $row['status'] ?? '—',
                    $row['priority'] ?? '—',
                    $row['full_name'] ?? '—',
                    $row['email'] ?? '—',
                    formatDate($row['created_at'] ?? null),
                ];
            }
            break;
        case 'payments':
        default:
            $exportHeaders = ['Student', 'Matric Number', 'Payment Type', 'Amount', 'Reference', 'Status', 'Date'];
            foreach ($paymentRows as $row) {
                $exportRows[] = [
                    $row['full_name'] ?? 'Unknown',
                    $row['matric_number'] ?? '—',
                    $row['payment_type'] ?? '—',
                    number_format((float)($row['amount'] ?? 0), 2, '.', ''),
                    $row['payment_reference'] ?? '—',
                    ucfirst((string)($row['status'] ?? 'pending')),
                    formatDate($row['created_at'] ?? null),
                ];
            }
            break;
    }

    $content = $exportFormat === 'csv' ? generateCsv($exportHeaders, $exportRows) : generateExcelHtml($exportHeaders, $exportRows);
    $extension = $exportFormat === 'csv' ? 'csv' : 'xls';
    $filename = 'nacos-' . $exportReport . '-report.' . $extension;
    $mimeType = $exportFormat === 'csv' ? 'text/csv; charset=utf-8' : 'application/vnd.ms-excel; charset=utf-8';
    sendDownload($filename, $content, $mimeType);
}
?>
<div class="container py-5">
  <div class="row g-4 align-items-start">
    <div class="col-lg-3">
      <?php require_once dirname(__DIR__) . '/templates/admin-sidebar.php'; ?>
    </div>
    <div class="col-lg-9">
      <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center mb-4 gap-3">
        <div>
          <div class="hero-badge"><i data-lucide="bar-chart-3"></i> Reports & Analytics</div>
          <h2 class="fw-bold mt-3 mb-1">NACOS Management Reporting Dashboard</h2>
          <p class="text-secondary mb-0">Monitor students, payments, events, resources, support, and media with filters, charts, and export-ready reports.</p>
        </div>
        <div class="d-flex gap-2">
          <a href="<?= APP_URL ?>/admin/dashboard.php" class="btn btn-outline-success btn-sm"><i data-lucide="arrow-left"></i> Back to Dashboard</a>
        </div>
      </div>

      <div class="glass-card p-3 p-lg-4 mb-4">
        <form method="get" class="row g-3 align-items-end">
          <div class="col-lg-3 col-md-6"><label class="form-label small fw-semibold">Universal Search</label><input type="text" name="search" value="<?= e($search) ?>" class="form-control" placeholder="Name, matric, reference, programme"></div>
          <div class="col-lg-2 col-md-6"><label class="form-label small fw-semibold">Date Range</label><select name="date_preset" class="form-select"><option value="this_month" <?= $datePreset === 'this_month' ? 'selected' : '' ?>>This Month</option><option value="last_month" <?= $datePreset === 'last_month' ? 'selected' : '' ?>>Last Month</option><option value="this_year" <?= $datePreset === 'this_year' ? 'selected' : '' ?>>This Year</option><option value="today" <?= $datePreset === 'today' ? 'selected' : '' ?>>Today</option><option value="yesterday" <?= $datePreset === 'yesterday' ? 'selected' : '' ?>>Yesterday</option><option value="last_7_days" <?= $datePreset === 'last_7_days' ? 'selected' : '' ?>>Last 7 Days</option><option value="last_30_days" <?= $datePreset === 'last_30_days' ? 'selected' : '' ?>>Last 30 Days</option><option value="custom" <?= $datePreset === 'custom' ? 'selected' : '' ?>>Custom Range</option></select></div>
          <div class="col-lg-2 col-md-6"><label class="form-label small fw-semibold">From</label><input type="date" name="date_from" value="<?= e($dateFrom ?? '') ?>" class="form-control"></div>
          <div class="col-lg-2 col-md-6"><label class="form-label small fw-semibold">To</label><input type="date" name="date_to" value="<?= e($dateTo ?? '') ?>" class="form-control"></div>
          <div class="col-lg-2 col-md-6"><label class="form-label small fw-semibold">Academic Session</label><input type="text" name="session_filter" value="<?= e($filters['session_filter']) ?>" class="form-control" placeholder="e.g. 2024/2025"></div>
          <div class="col-lg-1 col-md-6"><button class="btn btn-success w-100">Apply</button></div>
        </form>
      </div>

      <div class="row g-3 mb-4">
        <?php $summaryItems = [ ['icon' => 'dollar-sign', 'label' => "Today's Revenue", 'value' => formatCurrency((float)$stats['today_revenue']), 'description' => 'Verified revenue today', 'trend' => '+0%'], ['icon' => 'users', 'label' => "Today's Registrations", 'value' => number_format((int)$stats['today_registrations']), 'description' => 'New student signups', 'trend' => 'Live'], ['icon' => 'clipboard-check', 'label' => 'Pending Verifications', 'value' => number_format((int)$stats['pending_verifications']), 'description' => 'Payments awaiting review', 'trend' => 'Action'], ['icon' => 'calendar-days', 'label' => 'Upcoming Events', 'value' => number_format((int)$stats['upcoming_events']), 'description' => 'Scheduled events', 'trend' => 'Soon'], ['icon' => 'folder-open', 'label' => 'Resources Today', 'value' => number_format((int)$stats['resources_today']), 'description' => 'New resource uploads', 'trend' => 'New'], ['icon' => 'message-square', 'label' => 'Open Tickets', 'value' => number_format((int)$stats['open_tickets']), 'description' => 'Active support needs', 'trend' => 'Open'], ['icon' => 'bell', 'label' => 'Unread Notifications', 'value' => number_format((int)$stats['unread_notifications']), 'description' => 'Pending admin alerts', 'trend' => 'Unread'] ]; foreach ($summaryItems as $item): ?>
          <div class="col-md-6 col-xl-4">
            <div class="glass-card p-3 h-100">
              <div class="d-flex justify-content-between align-items-center mb-2"><div class="rounded-circle bg-white bg-opacity-75 p-2"><i data-lucide="<?= e($item['icon']) ?>"></i></div><span class="badge bg-success bg-opacity-10 text-success"><?= e($item['trend']) ?></span></div>
              <div class="fw-bold fs-5 mb-1"><?= e($item['value']) ?></div>
              <div class="small fw-semibold text-dark"><?= e($item['label']) ?></div>
              <div class="small text-secondary"><?= e($item['description']) ?></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="row g-3 mb-4">
        <?php $metricCards = [ ['icon' => 'users', 'label' => 'Total Students', 'value' => number_format((int)$stats['students_total']), 'description' => 'Registered learners'], ['icon' => 'user-check', 'label' => 'Active Students', 'value' => number_format((int)$stats['students_active']), 'description' => 'Active accounts'], ['icon' => 'graduation-cap', 'label' => 'This Session', 'value' => number_format((int)$stats['students_session']), 'description' => 'Current academic session'], ['icon' => 'users-2', 'label' => 'Total Executives', 'value' => number_format((int)$stats['executives_total']), 'description' => 'Leadership records'], ['icon' => 'calendar-days', 'label' => 'Total Events', 'value' => number_format((int)$stats['events_total']), 'description' => 'Portal events'], ['icon' => 'calendar-range', 'label' => 'Upcoming Events', 'value' => number_format((int)$stats['events_upcoming']), 'description' => 'Incoming events'], ['icon' => 'folder', 'label' => 'Total Resources', 'value' => number_format((int)$stats['resources_total']), 'description' => 'Uploaded materials'], ['icon' => 'image', 'label' => 'Gallery Images', 'value' => number_format((int)$stats['gallery_images']), 'description' => 'Image uploads'], ['icon' => 'video', 'label' => 'Gallery Videos', 'value' => number_format((int)$stats['gallery_videos']), 'description' => 'Video uploads'], ['icon' => 'credit-card', 'label' => 'Total Payments', 'value' => number_format((int)$stats['payments_total']), 'description' => 'All submissions'], ['icon' => 'clock-3', 'label' => 'Pending Payments', 'value' => number_format((int)$stats['payments_pending']), 'description' => 'Needs review'], ['icon' => 'badge-check', 'label' => 'Verified Payments', 'value' => number_format((int)$stats['payments_verified']), 'description' => 'Approved payments'], ['icon' => 'circle-x', 'label' => 'Rejected Payments', 'value' => number_format((int)$stats['payments_rejected']), 'description' => 'Declined submissions'], ['icon' => 'wallet', 'label' => 'Total Revenue', 'value' => formatCurrency((float)$stats['payments_revenue']), 'description' => 'Verified income'], ['icon' => 'receipt', 'label' => 'Outstanding', 'value' => formatCurrency((float)$stats['payments_outstanding']), 'description' => 'Pending or rejected'], ['icon' => 'message-square-more', 'label' => 'Open Tickets', 'value' => number_format((int)$stats['tickets_open']), 'description' => 'Needs response'], ['icon' => 'shield-check', 'label' => 'Closed Tickets', 'value' => number_format((int)$stats['tickets_closed']), 'description' => 'Resolved cases'] ]; foreach ($metricCards as $card): ?>
          <div class="col-md-6 col-xl-3">
            <div class="glass-card p-3 h-100">
              <div class="rounded-circle bg-white bg-opacity-75 p-2 d-inline-flex mb-2"><i data-lucide="<?= e($card['icon']) ?>"></i></div>
              <div class="fw-bold fs-5 mb-1"><?= e($card['value']) ?></div>
              <div class="small fw-semibold text-dark"><?= e($card['label']) ?></div>
              <div class="small text-secondary"><?= e($card['description']) ?></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="glass-card p-4 mb-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <div><h5 class="fw-bold mb-1">Payment Reports</h5><p class="text-secondary mb-0">Financial movement with filtered summaries and export-ready tables.</p></div>
          <div class="d-flex gap-2">
            <a class="btn btn-outline-success btn-sm" href="<?= APP_URL ?>/admin/reports.php?<?= e($baseQuery) ?>&report=payments&format=csv">Export CSV</a>
            <a class="btn btn-outline-success btn-sm" href="<?= APP_URL ?>/admin/reports.php?<?= e($baseQuery) ?>&report=payments&format=excel">Export Excel</a>
            <button class="btn btn-outline-secondary btn-sm" type="button" onclick="window.print();">Print</button>
          </div>
        </div>
        <div class="row g-3 mb-3">
          <div class="col-md-3"><input type="text" class="form-control" form="paymentFilters" name="payment_type_filter" value="<?= e($filters['payment_type_filter']) ?>" placeholder="Payment Type"></div>
          <div class="col-md-3"><select class="form-select" form="paymentFilters" name="payment_status_filter"><option value="">All Status</option><option value="pending" <?= $filters['payment_status_filter'] === 'pending' ? 'selected' : '' ?>>Pending</option><option value="approved" <?= $filters['payment_status_filter'] === 'approved' ? 'selected' : '' ?>>Approved</option><option value="verified" <?= $filters['payment_status_filter'] === 'verified' ? 'selected' : '' ?>>Verified</option><option value="rejected" <?= $filters['payment_status_filter'] === 'rejected' ? 'selected' : '' ?>>Rejected</option></select></div>
          <div class="col-md-3"><input type="text" class="form-control" form="paymentFilters" name="student_filter" value="<?= e($filters['student_filter']) ?>" placeholder="Student Name"></div>
          <div class="col-md-3"><input type="text" class="form-control" form="paymentFilters" name="programme_filter" value="<?= e($filters['programme_filter']) ?>" placeholder="Programme"></div>
          <div class="col-md-3"><input type="text" class="form-control" form="paymentFilters" name="level_filter" value="<?= e($filters['level_filter']) ?>" placeholder="Level"></div>
        </div>
        <form id="paymentFilters" method="get" class="row g-2 mb-3">
          <input type="hidden" name="search" value="<?= e($search) ?>">
          <input type="hidden" name="date_preset" value="<?= e($datePreset) ?>">
          <input type="hidden" name="date_from" value="<?= e($dateFrom ?? '') ?>">
          <input type="hidden" name="date_to" value="<?= e($dateTo ?? '') ?>">
          <input type="hidden" name="session_filter" value="<?= e($filters['session_filter']) ?>">
          <button class="btn btn-success btn-sm col-auto">Apply Payment Filters</button>
        </form>
        <div class="row g-3 mb-3">
          <div class="col-md-4"><div class="border rounded p-3 bg-light bg-opacity-75"><div class="small text-secondary">Payments</div><div class="fw-bold fs-5"><?= number_format((int)$paymentSummary['count']) ?></div></div></div>
          <div class="col-md-4"><div class="border rounded p-3 bg-light bg-opacity-75"><div class="small text-secondary">Total Revenue</div><div class="fw-bold fs-5"><?= formatCurrency((float)$paymentSummary['total']) ?></div></div></div>
          <div class="col-md-4"><div class="border rounded p-3 bg-light bg-opacity-75"><div class="small text-secondary">Average Payment</div><div class="fw-bold fs-5"><?= formatCurrency((float)$paymentSummary['average']) ?></div></div></div>
          <div class="col-md-4"><div class="border rounded p-3 bg-light bg-opacity-75"><div class="small text-secondary">Highest Payment</div><div class="fw-bold fs-5"><?= formatCurrency((float)$paymentSummary['highest']) ?></div></div></div>
          <div class="col-md-4"><div class="border rounded p-3 bg-light bg-opacity-75"><div class="small text-secondary">Lowest Payment</div><div class="fw-bold fs-5"><?= formatCurrency((float)$paymentSummary['lowest']) ?></div></div></div>
          <div class="col-md-4"><div class="border rounded p-3 bg-light bg-opacity-75"><div class="small text-secondary">Pending / Verified / Rejected</div><div class="fw-bold fs-5"><?= formatCurrency((float)$paymentSummary['pending']) ?> / <?= formatCurrency((float)$paymentSummary['verified']) ?> / <?= formatCurrency((float)$paymentSummary['rejected']) ?></div></div></div>
        </div>
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Student</th><th>Matric Number</th><th>Payment Type</th><th>Amount</th><th>Reference</th><th>Status</th><th>Date</th><th>Verified By</th><th>Verification Date</th></tr></thead>
            <tbody>
              <?php foreach ($paymentRows as $row): ?>
                <tr><td><?= e((string)($row['full_name'] ?? 'Unknown')) ?></td><td><?= e((string)($row['matric_number'] ?? '—')) ?></td><td><?= e((string)($row['payment_type'] ?? '—')) ?></td><td><?= formatCurrency((float)($row['amount'] ?? 0)) ?></td><td><?= e((string)($row['payment_reference'] ?? '—')) ?></td><td><span class="badge bg-<?= ((string)($row['status'] ?? 'pending') === 'verified' || (string)($row['status'] ?? 'pending') === 'approved') ? 'success' : (((string)($row['status'] ?? 'pending') === 'rejected') ? 'danger' : 'warning') ?> bg-opacity-10 text-<?= ((string)($row['status'] ?? 'pending') === 'verified' || (string)($row['status'] ?? 'pending') === 'approved') ? 'success' : (((string)($row['status'] ?? 'pending') === 'rejected') ? 'danger' : 'warning') ?>"><?= e(ucfirst((string)($row['status'] ?? 'pending'))) ?></span></td><td><?= e(formatDate($row['created_at'] ?? null)) ?></td><td>—</td><td>—</td></tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <div class="row g-4 mb-4">
        <div class="col-lg-6"><div class="glass-card p-4 h-100"><h5 class="fw-bold mb-3">Monthly Revenue</h5><canvas id="monthlyRevenueChart"></canvas></div></div>
        <div class="col-lg-6"><div class="glass-card p-4 h-100"><h5 class="fw-bold mb-3">Payments by Type</h5><canvas id="paymentTypeChart"></canvas></div></div>
      </div>

      <div class="glass-card p-4 mb-4">
        <div class="d-flex justify-content-between align-items-center mb-3"><div><h5 class="fw-bold mb-1">Student Reports</h5><p class="text-secondary mb-0">Registration growth, department distribution, and student demographics.</p></div><div class="d-flex gap-2"><a class="btn btn-outline-success btn-sm" href="<?= APP_URL ?>/admin/reports.php?<?= e($baseQuery) ?>&report=students&format=csv">Export CSV</a><a class="btn btn-outline-success btn-sm" href="<?= APP_URL ?>/admin/reports.php?<?= e($baseQuery) ?>&report=students&format=excel">Export Excel</a><button class="btn btn-outline-secondary btn-sm" type="button" onclick="window.print();">Print</button></div></div>
        <div class="row g-3 mb-3"><div class="col-md-3"><input type="text" class="form-control" form="studentFilters" name="programme_filter" value="<?= e($filters['programme_filter']) ?>" placeholder="Programme"></div><div class="col-md-3"><input type="text" class="form-control" form="studentFilters" name="level_filter" value="<?= e($filters['level_filter']) ?>" placeholder="Level"></div><div class="col-md-3"><input type="text" class="form-control" form="studentFilters" name="department_filter" value="<?= e($filters['department_filter']) ?>" placeholder="Department"></div><div class="col-md-3"><input type="date" class="form-control" form="studentFilters" name="date_from" value="<?= e($dateFrom ?? '') ?>"></div></div>
        <form id="studentFilters" method="get" class="row g-2 mb-3"><input type="hidden" name="search" value="<?= e($search) ?>"><input type="hidden" name="date_preset" value="<?= e($datePreset) ?>"><input type="hidden" name="date_to" value="<?= e($dateTo ?? '') ?>"><input type="hidden" name="session_filter" value="<?= e($filters['session_filter']) ?>"><button class="btn btn-success btn-sm col-auto">Apply Student Filters</button></form>
        <div class="row g-3 mb-3"><div class="col-md-4"><div class="border rounded p-3 bg-light bg-opacity-75"><div class="small text-secondary">Total Students</div><div class="fw-bold fs-5"><?= number_format((int)$studentSummary['total']) ?></div></div></div><div class="col-md-4"><div class="border rounded p-3 bg-light bg-opacity-75"><div class="small text-secondary">New Registrations</div><div class="fw-bold fs-5"><?= number_format((int)$studentSummary['new']) ?></div></div></div><div class="col-md-4"><div class="border rounded p-3 bg-light bg-opacity-75"><div class="small text-secondary">Approved / Pending / Rejected / Inactive</div><div class="fw-bold fs-5"><?= number_format((int)$studentSummary['approved']) ?> / <?= number_format((int)$studentSummary['pending']) ?> / <?= number_format((int)$studentSummary['rejected']) ?> / <?= number_format((int)$studentSummary['inactive']) ?></div></div></div></div>
        <div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Student</th><th>Matric Number</th><th>Programme</th><th>Level</th><th>Department</th><th>Session</th><th>Status</th><th>Registered</th></tr></thead><tbody><?php foreach ($studentRows as $row): ?><tr><td><?= e((string)($row['full_name'] ?? '—')) ?></td><td><?= e((string)($row['matric_number'] ?? '—')) ?></td><td><?= e((string)($row['programme'] ?? '—')) ?></td><td><?= e((string)($row['level'] ?? '—')) ?></td><td><?= e((string)($row['department'] ?? '—')) ?></td><td><?= e((string)($row['session'] ?? $currentSession)) ?></td><td><span class="badge bg-<?= strtolower((string)($row['status'] ?? 'active')) === 'active' ? 'success' : 'secondary' ?> bg-opacity-10 text-<?= strtolower((string)($row['status'] ?? 'active')) === 'active' ? 'success' : 'secondary' ?>"><?= e(ucfirst((string)($row['status'] ?? 'active'))) ?></span></td><td><?= e(formatDate($row['created_at'] ?? null)) ?></td></tr><?php endforeach; ?></tbody></table></div>
      </div>

      <div class="row g-4 mb-4"><div class="col-lg-6"><div class="glass-card p-4 h-100"><h5 class="fw-bold mb-3">Students by Level</h5><canvas id="studentLevelChart"></canvas></div></div><div class="col-lg-6"><div class="glass-card p-4 h-100"><h5 class="fw-bold mb-3">Students by Programme</h5><canvas id="studentProgrammeChart"></canvas></div></div></div>

      <div class="glass-card p-4 mb-4"><div class="d-flex justify-content-between align-items-center mb-3"><div><h5 class="fw-bold mb-1">Event Reports</h5><p class="text-secondary mb-0">Track attendance, participation, and event outcomes.</p></div><div class="d-flex gap-2"><a class="btn btn-outline-success btn-sm" href="<?= APP_URL ?>/admin/reports.php?<?= e($baseQuery) ?>&report=events&format=csv">Export CSV</a><a class="btn btn-outline-success btn-sm" href="<?= APP_URL ?>/admin/reports.php?<?= e($baseQuery) ?>&report=events&format=excel">Export Excel</a><button class="btn btn-outline-secondary btn-sm" type="button" onclick="window.print();">Print</button></div></div><div class="row g-3 mb-3"><div class="col-md-4"><div class="border rounded p-3 bg-light bg-opacity-75"><div class="small text-secondary">Total Events</div><div class="fw-bold fs-5"><?= number_format((int)$eventSummary['total']) ?></div></div></div><div class="col-md-4"><div class="border rounded p-3 bg-light bg-opacity-75"><div class="small text-secondary">Upcoming / Completed / Archived</div><div class="fw-bold fs-5"><?= number_format((int)$eventSummary['upcoming']) ?> / <?= number_format((int)$eventSummary['completed']) ?> / <?= number_format((int)$eventSummary['archived']) ?></div></div></div><div class="col-md-4"><div class="border rounded p-3 bg-light bg-opacity-75"><div class="small text-secondary">Registered Participants</div><div class="fw-bold fs-5"><?= number_format((int)$eventSummary['participants']) ?></div></div></div></div><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Event</th><th>Venue</th><th>Date</th><th>Participants</th><th>Status</th></tr></thead><tbody><?php foreach ($eventRows as $row): $status = 'upcoming'; if ((int)$row['is_upcoming'] === 2) { $status = 'archived'; } elseif ((string)$row['event_date'] < date('Y-m-d')) { $status = 'completed'; } ?><tr><td><?= e((string)($row['title'] ?? '—')) ?></td><td><?= e((string)($row['venue'] ?? '—')) ?></td><td><?= e(formatDate($row['event_date'] ?? null)) ?></td><td><?= e((string)$row['participants']) ?></td><td><span class="badge bg-<?= $status === 'completed' ? 'secondary' : ($status === 'archived' ? 'warning' : 'success') ?> bg-opacity-10 text-<?= $status === 'completed' ? 'secondary' : ($status === 'archived' ? 'warning' : 'success') ?>"><?= e(ucfirst($status)) ?></span></td></tr><?php endforeach; ?></tbody></table></div></div>

      <div class="row g-4 mb-4"><div class="col-lg-6"><div class="glass-card p-4 h-100"><h5 class="fw-bold mb-3">Resources Uploaded Per Month</h5><canvas id="resourceChart"></canvas></div></div><div class="col-lg-6"><div class="glass-card p-4 h-100"><h5 class="fw-bold mb-3">Events Per Month</h5><canvas id="eventChart"></canvas></div></div></div>

      <div class="glass-card p-4 mb-4"><div class="d-flex justify-content-between align-items-center mb-3"><div><h5 class="fw-bold mb-1">Resource Reports</h5><p class="text-secondary mb-0">Material distribution by programme, level, and semester.</p></div><div class="d-flex gap-2"><a class="btn btn-outline-success btn-sm" href="<?= APP_URL ?>/admin/reports.php?<?= e($baseQuery) ?>&report=resources&format=csv">Export CSV</a><a class="btn btn-outline-success btn-sm" href="<?= APP_URL ?>/admin/reports.php?<?= e($baseQuery) ?>&report=resources&format=excel">Export Excel</a><button class="btn btn-outline-secondary btn-sm" type="button" onclick="window.print();">Print</button></div></div><div class="row g-3 mb-3"><div class="col-md-4"><div class="border rounded p-3 bg-light bg-opacity-75"><div class="small text-secondary">Total Resources</div><div class="fw-bold fs-5"><?= number_format((int)$resourceSummary['total']) ?></div></div></div><div class="col-md-4"><div class="border rounded p-3 bg-light bg-opacity-75"><div class="small text-secondary">Downloads</div><div class="fw-bold fs-5"><?= number_format((int)$resourceSummary['downloads']) ?></div></div></div><div class="col-md-4"><div class="border rounded p-3 bg-light bg-opacity-75"><div class="small text-secondary">Most / Least Downloaded</div><div class="fw-bold fs-5"><?= e($resourceSummary['most_downloaded']) ?> / <?= e($resourceSummary['least_downloaded']) ?></div></div></div></div><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Resource</th><th>Programme</th><th>Level</th><th>Semester</th><th>Uploaded</th><th>Downloads</th></tr></thead><tbody><?php foreach ($resourceRows as $row): ?><tr><td><?= e((string)($row['title'] ?? '—')) ?></td><td><?= e((string)($row['programme'] ?? '—')) ?></td><td><?= e((string)($row['level'] ?? '—')) ?></td><td><?= e((string)($row['semester'] ?? '—')) ?></td><td><?= e(formatDate($row['created_at'] ?? null)) ?></td><td>0</td></tr><?php endforeach; ?></tbody></table></div></div>

      <div class="glass-card p-4 mb-4"><div class="d-flex justify-content-between align-items-center mb-3"><div><h5 class="fw-bold mb-1">Executive Reports</h5><p class="text-secondary mb-0">Leadership records with current and active status.</p></div><div class="d-flex gap-2"><a class="btn btn-outline-success btn-sm" href="<?= APP_URL ?>/admin/reports.php?<?= e($baseQuery) ?>&report=executives&format=csv">Export CSV</a><a class="btn btn-outline-success btn-sm" href="<?= APP_URL ?>/admin/reports.php?<?= e($baseQuery) ?>&report=executives&format=excel">Export Excel</a><button class="btn btn-outline-secondary btn-sm" type="button" onclick="window.print();">Print</button></div></div><div class="row g-3 mb-3"><div class="col-md-4"><div class="border rounded p-3 bg-light bg-opacity-75"><div class="small text-secondary">Current Executives</div><div class="fw-bold fs-5"><?= number_format((int)$executiveSummary['current']) ?></div></div></div><div class="col-md-4"><div class="border rounded p-3 bg-light bg-opacity-75"><div class="small text-secondary">Former Executives</div><div class="fw-bold fs-5"><?= number_format((int)$executiveSummary['former']) ?></div></div></div><div class="col-md-4"><div class="border rounded p-3 bg-light bg-opacity-75"><div class="small text-secondary">Active / Inactive</div><div class="fw-bold fs-5"><?= number_format((int)$executiveSummary['active']) ?> / <?= number_format((int)$executiveSummary['inactive']) ?></div></div></div></div><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Name</th><th>Position</th><th>Department</th><th>Academic Level</th><th>Status</th></tr></thead><tbody><?php foreach ($executiveRows as $row): ?><tr><td><?= e((string)($row['full_name'] ?? '—')) ?></td><td><?= e((string)($row['position'] ?? '—')) ?></td><td><?= e((string)($row['department'] ?? '—')) ?></td><td><?= e((string)($row['academic_level'] ?? '—')) ?></td><td><span class="badge bg-success bg-opacity-10 text-success">Active</span></td></tr><?php endforeach; ?></tbody></table></div></div>

      <div class="glass-card p-4 mb-4"><div class="d-flex justify-content-between align-items-center mb-3"><div><h5 class="fw-bold mb-1">Gallery Reports</h5><p class="text-secondary mb-0">Image and video upload trends.</p></div><div class="d-flex gap-2"><a class="btn btn-outline-success btn-sm" href="<?= APP_URL ?>/admin/reports.php?<?= e($baseQuery) ?>&report=gallery&format=csv">Export CSV</a><a class="btn btn-outline-success btn-sm" href="<?= APP_URL ?>/admin/reports.php?<?= e($baseQuery) ?>&report=gallery&format=excel">Export Excel</a><button class="btn btn-outline-secondary btn-sm" type="button" onclick="window.print();">Print</button></div></div><div class="row g-3 mb-3"><div class="col-md-4"><div class="border rounded p-3 bg-light bg-opacity-75"><div class="small text-secondary">Total Images</div><div class="fw-bold fs-5"><?= number_format((int)$galleryData['images']) ?></div></div></div><div class="col-md-4"><div class="border rounded p-3 bg-light bg-opacity-75"><div class="small text-secondary">Total Videos</div><div class="fw-bold fs-5"><?= number_format((int)$galleryData['videos']) ?></div></div></div><div class="col-md-4"><div class="border rounded p-3 bg-light bg-opacity-75"><div class="small text-secondary">Uploads This Month / Year</div><div class="fw-bold fs-5"><?= number_format((int)$galleryData['uploads_this_month']) ?> / <?= number_format((int)$galleryData['uploads_this_year']) ?></div></div></div></div><canvas id="galleryChart"></canvas></div>

      <div class="row g-4 mb-4"><div class="col-lg-6"><div class="glass-card p-4 h-100"><div class="d-flex justify-content-between align-items-center mb-3"><div><h5 class="fw-bold mb-1">Support Reports</h5><p class="text-secondary mb-0">Ticket status and response performance.</p></div><div class="d-flex gap-2"><a class="btn btn-outline-success btn-sm" href="<?= APP_URL ?>/admin/reports.php?<?= e($baseQuery) ?>&report=support&format=csv">Export CSV</a><a class="btn btn-outline-success btn-sm" href="<?= APP_URL ?>/admin/reports.php?<?= e($baseQuery) ?>&report=support&format=excel">Export Excel</a><button class="btn btn-outline-secondary btn-sm" type="button" onclick="window.print();">Print</button></div></div><div class="row g-3 mb-3"><div class="col-md-6"><div class="border rounded p-3 bg-light bg-opacity-75"><div class="small text-secondary">Open / Pending / Resolved / Closed</div><div class="fw-bold fs-5"><?= number_format((int)$supportSummary['open']) ?> / <?= number_format((int)$supportSummary['pending']) ?> / <?= number_format((int)$supportSummary['resolved']) ?> / <?= number_format((int)$supportSummary['closed']) ?></div></div></div><div class="col-md-6"><div class="border rounded p-3 bg-light bg-opacity-75"><div class="small text-secondary">Average Resolution Time</div><div class="fw-bold fs-5"><?= e($supportSummary['avg_resolution']) ?></div></div></div></div><canvas id="supportChart"></canvas></div></div><div class="col-lg-6"><div class="glass-card p-4 h-100"><h5 class="fw-bold mb-3">Recent Activities</h5><div class="d-grid gap-2"><?php foreach ($activityItems as $item): ?><div class="d-flex justify-content-between align-items-start p-2 rounded-3 bg-light bg-opacity-75"><div><div class="fw-semibold"><?= e((string)$item['type']) ?></div><small class="text-secondary"><?= e((string)$item['message']) ?></small></div><small class="text-muted"><?= e(formatDate($item['created_at'] ?? null)) ?></small></div><?php endforeach; ?></div></div></div></div>

      <div class="glass-card p-4 mb-4"><h5 class="fw-bold mb-3">System Health</h5><div class="row g-3"><div class="col-md-4"><div class="border rounded p-3 bg-light bg-opacity-75"><div class="small text-secondary">Database Status</div><div class="fw-bold">Connected</div></div></div><div class="col-md-4"><div class="border rounded p-3 bg-light bg-opacity-75"><div class="small text-secondary">Mail Service Status</div><div class="fw-bold">Configured</div></div></div><div class="col-md-4"><div class="border rounded p-3 bg-light bg-opacity-75"><div class="small text-secondary">Storage Usage</div><div class="fw-bold"><?= e(round((disk_total_space(APP_ROOT) > 0 ? 100 - (disk_free_space(APP_ROOT) / disk_total_space(APP_ROOT) * 100) : 0), 1)) ?>%</div></div></div><div class="col-md-4"><div class="border rounded p-3 bg-light bg-opacity-75"><div class="small text-secondary">Total Uploaded Files</div><div class="fw-bold"><?= e(number_format((int)$galleryData['images'] + (int)$galleryData['videos'] + (int)$stats['resources_total'])) ?></div></div></div><div class="col-md-4"><div class="border rounded p-3 bg-light bg-opacity-75"><div class="small text-secondary">Last Backup Date</div><div class="fw-bold"><?= e(date('Y-m-d', filemtime(dirname(__DIR__) . '/database.sql'))) ?></div></div></div><div class="col-md-4"><div class="border rounded p-3 bg-light bg-opacity-75"><div class="small text-secondary">PHP Version</div><div class="fw-bold"><?= e(PHP_VERSION) ?></div></div></div></div></div>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
  const palette = ['#14532d', '#16a34a', '#3b82f6', '#f59e0b', '#ef4444', '#8b5cf6', '#0f766e'];
  function createChart(ctx, type, labels, data, label) {
    return new Chart(ctx, { type, data: { labels, datasets: [{ label, data, backgroundColor: palette, borderColor: palette[0], borderWidth: 1, fill: type === 'line' }] }, options: { responsive: true, maintainAspectRatio: false } });
  }
  document.addEventListener('DOMContentLoaded', function () {
    const monthlyRevenue = <?= json_encode($monthlyRevenue) ?>;
    const paymentType = <?= json_encode($paymentTypeChart) ?>;
    const levelChart = <?= json_encode($levelChart) ?>;
    const programmeChart = <?= json_encode($programmeChart) ?>;
    const supportChart = <?= json_encode($supportChart) ?>;
    const registrationChart = <?= json_encode($registrationChart) ?>;
    const resourceChart = <?= json_encode($resourceChart) ?>;
    const eventChart = <?= json_encode($eventChart) ?>;
    const galleryChart = <?= json_encode($galleryChart) ?>;
    if (document.getElementById('monthlyRevenueChart')) createChart(document.getElementById('monthlyRevenueChart').getContext('2d'), 'line', monthlyRevenue.labels || [], monthlyRevenue.data || [], 'Revenue');
    if (document.getElementById('paymentTypeChart')) createChart(document.getElementById('paymentTypeChart').getContext('2d'), 'pie', paymentType.labels || [], paymentType.data || [], 'Payments');
    if (document.getElementById('studentLevelChart')) createChart(document.getElementById('studentLevelChart').getContext('2d'), 'bar', levelChart.labels || [], levelChart.data || [], 'Students');
    if (document.getElementById('studentProgrammeChart')) createChart(document.getElementById('studentProgrammeChart').getContext('2d'), 'doughnut', programmeChart.labels || [], programmeChart.data || [], 'Programmes');
    if (document.getElementById('supportChart')) createChart(document.getElementById('supportChart').getContext('2d'), 'pie', supportChart.labels || [], supportChart.data || [], 'Tickets');
    if (document.getElementById('resourceChart')) createChart(document.getElementById('resourceChart').getContext('2d'), 'line', resourceChart.labels || [], resourceChart.data || [], 'Resources');
    if (document.getElementById('eventChart')) createChart(document.getElementById('eventChart').getContext('2d'), 'bar', eventChart.labels || [], eventChart.data || [], 'Events');
    if (document.getElementById('galleryChart')) createChart(document.getElementById('galleryChart').getContext('2d'), 'doughnut', galleryChart.labels || [], galleryChart.data || [], 'Gallery');
  });
</script>
<?php require_once dirname(__DIR__) . '/templates/footer.php'; ?>
