<?php
$adminPages = [
    ['href' => APP_URL . '/admin/dashboard.php', 'icon' => 'layout-dashboard', 'label' => 'Dashboard'],
    ['href' => APP_URL . '/admin/students.php', 'icon' => 'users', 'label' => 'Students'],
    ['href' => APP_URL . '/admin/payments.php', 'icon' => 'credit-card', 'label' => 'Payments'],
    ['href' => APP_URL . '/admin/id-cards.php', 'icon' => 'id-card', 'label' => 'ID Cards'],
    ['href' => APP_URL . '/admin/certificates.php', 'icon' => 'award', 'label' => 'Certificates'],
    ['href' => APP_URL . '/admin/elections.php', 'icon' => 'vote', 'label' => 'Voting'],
    ['href' => APP_URL . '/admin/content.php', 'icon' => 'layers-3', 'label' => 'Content'],
    ['href' => APP_URL . '/admin/support/tickets.php', 'icon' => 'message-square-more', 'label' => 'Support'],
    ['href' => APP_URL . '/admin/visitor-messages.php', 'icon' => 'message-circle', 'label' => 'Visitor Messages'],
    ['href' => APP_URL . '/admin/reports.php', 'icon' => 'bar-chart-3', 'label' => 'Reports'],
    ['href' => APP_URL . '/admin/settings.php', 'icon' => 'settings', 'label' => 'Settings'],
    ['href' => APP_URL . '/auth/logout.php', 'icon' => 'log-out', 'label' => 'Logout'],
];
$currentPage = basename($_SERVER['PHP_SELF'] ?? 'dashboard.php');
?>
<div class="admin-sidebar-card glass-card p-4">
    <button type="button" class="mobile-sidebar-toggle" aria-expanded="false" aria-controls="adminSidebarNav">
        <span><i data-lucide="menu"></i> Admin menu</span><i data-lucide="chevron-down" class="mobile-sidebar-chevron"></i>
    </button>
    <nav id="adminSidebarNav" class="nav flex-column admin-sidebar-nav">
        <?php foreach ($adminPages as $item): ?>
            <a class="nav-link <?= $currentPage === basename($item['href']) ? 'active' : '' ?>" href="<?= $item['href'] ?>">
                <i data-lucide="<?= $item['icon'] ?>" class="sidebar-icon"></i>
                <span><?= $item['label'] ?></span>
            </a>
        <?php endforeach; ?>
    </nav>
</div>
