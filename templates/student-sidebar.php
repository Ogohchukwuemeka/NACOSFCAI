<?php
$studentPages = [
    ['href' => APP_URL . '/student/dashboard.php', 'icon' => 'layout-dashboard', 'label' => 'Dashboard'],
    ['href' => APP_URL . '/student/profile.php', 'icon' => 'user', 'label' => 'Profile'],
    ['href' => APP_URL . '/student/payments.php', 'icon' => 'credit-card', 'label' => 'Payments'],
    ['href' => APP_URL . '/student/certificate.php', 'icon' => 'award', 'label' => 'Certificate'],
    ['href' => APP_URL . '/student/resources.php', 'icon' => 'book-open', 'label' => 'Resources'],
    ['href' => APP_URL . '/student/community-hub.php', 'icon' => 'message-square-more', 'label' => 'Chat & Community'],
    ['href' => APP_URL . '/student/notifications.php', 'icon' => 'bell', 'label' => 'Notifications'],
    ['href' => APP_URL . '/student/voting.php', 'icon' => 'check-square', 'label' => 'Vote'],
    ['href' => APP_URL . '/student/elections-results.php', 'icon' => 'bar-chart-2', 'label' => 'Elections Results'],
    ['href' => APP_URL . '/student/id-card.php', 'icon' => 'id-card', 'label' => 'ID Card'],
    ['href' => APP_URL . '/student/support.php', 'icon' => 'help-circle', 'label' => 'Support'],
    ['href' => APP_URL . '/student/reset-password.php', 'icon' => 'lock', 'label' => 'Reset Password'],
    ['href' => APP_URL . '/student/logout.php', 'icon' => 'log-out', 'label' => 'Logout'],
];
$currentPage = basename($_SERVER['PHP_SELF']);
$profilePhoto = $user['profile_photo'] ?? '';
$studentInitials = strtoupper(substr(trim((string)($user['full_name'] ?? 'Student')), 0, 1));
$nameParts = preg_split('/\s+/', trim((string)($user['full_name'] ?? 'Student')));
if (count($nameParts) > 1) {
    $studentInitials .= strtoupper(substr($nameParts[count($nameParts) - 1], 0, 1));
}
$memberId = $user['registration_number'] ?? $user['matric_number'] ?? 'N/A';
?>
<div class="student-sidebar-card glass-card p-4">
    <button type="button" class="mobile-sidebar-toggle" aria-expanded="false" aria-controls="studentSidebarNav">
        <span><i data-lucide="menu"></i> Student menu</span><i data-lucide="chevron-down" class="mobile-sidebar-chevron"></i>
    </button>
    <div class="sidebar-profile text-center mb-4">
        <div class="sidebar-avatar mb-3">
            <?php if ($profilePhoto): ?>
                <img src="<?= e(APP_URL . '/' . ltrim($profilePhoto, '/')) ?>" alt="Profile photo" class="rounded-circle">
            <?php else: ?>
                <span class="sidebar-avatar-initials" aria-label="No profile photo"><?= e($studentInitials) ?></span>
            <?php endif; ?>
        </div>
        <h5 class="mb-1"><?= e($user['full_name'] ?? 'Student') ?></h5>
        <p class="text-secondary small mb-1"><?= e($user['course'] ?? 'Course not assigned') ?></p>
        <p class="text-secondary small mb-0">Member ID: <?= e($memberId) ?></p>
    </div>
    
    <nav id="studentSidebarNav" class="nav flex-column student-sidebar-nav">
        <?php foreach ($studentPages as $item): ?>
            <a class="nav-link d-flex align-items-center gap-2 <?= $currentPage === basename($item['href']) ? 'active' : '' ?>" href="<?= $item['href'] ?>">
                <i data-lucide="<?= $item['icon'] ?>" class="sidebar-icon"></i>
                <span><?= $item['label'] ?></span>
            </a>
        <?php endforeach; ?>
    </nav>
</div>
