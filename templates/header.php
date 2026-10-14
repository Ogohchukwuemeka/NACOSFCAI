<?php require_once dirname(__DIR__) . '/config/bootstrap.php'; ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>NACOSFCAI</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="<?= asset('css/style.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/community-chat-ux.css') ?>">
    <script>
        window.APP_URL = <?= json_encode(APP_URL, JSON_UNESCAPED_SLASHES) ?>;
        window.CSRF_TOKEN = <?= json_encode(csrf_token()) ?>;
        window.NACOS_IDLE_TIMEOUT_MS = <?= !empty($_SESSION['user_role']) ? (int)$sessionTimeout * 1000 : 0 ?>;
        window.NACOS_LAST_ACTIVITY_MS = <?= !empty($_SESSION['user_role']) ? (int)($_SESSION['last_activity'] ?? time()) * 1000 : 0 ?>;
        window.NACOS_ACTIVITY_URL = <?= json_encode(APP_URL . '/api/session-activity.php', JSON_UNESCAPED_SLASHES) ?>;
        window.NACOS_TIMEOUT_URL = <?= json_encode(APP_URL . '/student/logout.php?timeout=1', JSON_UNESCAPED_SLASHES) ?>;
    </script>
</head>
<?php
    $currentPath = $_SERVER['PHP_SELF'] ?? '';
    $isHome = basename($currentPath) === 'index.php' || $currentPath === '/' || $currentPath === '';
    $isStudent = str_contains($currentPath, '/student/');
    $bodyClasses = [];
    if ($isHome) $bodyClasses[] = 'homepage';
    if ($isStudent) $bodyClasses[] = 'student-page';
    $bodyClassAttr = $bodyClasses ? 'class="' . implode(' ', $bodyClasses) . '"' : '';
?>
<body <?= $bodyClassAttr ?>>
<script>
(function(){
    try {
        if (!document.body.classList.contains('student-page')) {
            return;
        }
        const theme = localStorage.getItem('nacos_theme');
        const isDark = theme === 'dark';
        document.documentElement.classList.toggle('dark-mode', isDark);
        document.body.classList.toggle('dark-mode', isDark);
    } catch (e) {
        // ignore
    }
})();
</script>
<nav class="navbar navbar-expand-lg sticky-top navbar-glass">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center gap-3" href="<?= APP_URL ?>/index.php">
            <img src="<?= asset('images/logo/nacos-logo.jpeg') ?>" alt="nacos-logo" class="brand-logo" loading="lazy" width="72" height="72">
            <span class="fw-bold fs-4">NACOSFCAI</span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
            <i data-lucide="menu"></i>
        </button>
                <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav ms-auto align-items-lg-center gap-2">
                <li class="nav-item"><a class="nav-link <?= isActive('index.php') ? 'active' : '' ?>" href="<?= APP_URL ?>/index.php">Home</a></li>
                <li class="nav-item"><a class="nav-link <?= isActive('about.php') ? 'active' : '' ?>" href="<?= APP_URL ?>/about.php">About</a></li>
                <li class="nav-item"><a class="nav-link <?= isActive('programmes.php') ? 'active' : '' ?>" href="<?= APP_URL ?>/programmes.php">Programmes</a></li>
                <li class="nav-item"><a class="nav-link <?= isActive('executives.php') ? 'active' : '' ?>" href="<?= APP_URL ?>/executives.php">Executives</a></li>
                <li class="nav-item"><a class="nav-link <?= isActive('events.php') ? 'active' : '' ?>" href="<?= APP_URL ?>/events.php">Events</a></li>
                <li class="nav-item"><a class="nav-link <?= isActive('gallery.php') ? 'active' : '' ?>" href="<?= APP_URL ?>/gallery.php">Gallery</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= APP_URL ?>/auth/register.php">Register</a></li>
            </ul>
        </div>
    </div>
</nav>
<?php
$showBackButton = false;
$backLink = '';
$backLabel = '';
$path = $_SERVER['PHP_SELF'] ?? '';
if (str_ends_with($path, '/student/forgot-password.php')) {
    $showBackButton = true;
    $backLink = APP_URL . '/auth/login.php';
    $backLabel = 'Back to Login';
} elseif (str_contains($path, '/student/') && !str_ends_with($path, '/dashboard.php')) {
    $showBackButton = true;
    $backLink = APP_URL . '/student/dashboard.php';
    $backLabel = 'Back to Student Dashboard';
} elseif (str_contains($path, '/admin/') && !str_ends_with($path, '/dashboard.php')) {
    $showBackButton = true;
    $backLink = APP_URL . '/admin/dashboard.php';
    $backLabel = 'Back to Admin Dashboard';
}
?>
<?php if ($showBackButton): ?>
    <div class="container py-3">
        <a href="<?= $backLink ?>" class="btn btn-outline-secondary btn-sm">
            <i data-lucide="arrow-left"></i> <?= $backLabel ?>
        </a>
    </div>
<?php endif; ?>
