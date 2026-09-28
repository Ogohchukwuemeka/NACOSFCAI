<?php require_once dirname(__DIR__, 2) . '/templates/header.php'; ?>
<?php
$auth = new Auth(Database::getInstance());
$auth->requireLogin('admin');
$portal = new Portal(Database::getInstance());
$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf();
        $ticketId = (int)($_POST['ticket_id'] ?? 0);
        $message = trim((string)($_POST['reply_message'] ?? ''));
        if ($ticketId <= 0 || $message === '') {
            throw new RuntimeException('Please enter a reply before submitting.');
        }
        $portal->createSupportReply($ticketId, (int)($_SESSION['user_id'] ?? 0), $message);
        $success = 'Reply sent successfully.';
    } catch (Exception $e) {
        $errors[] = $e->getMessage();
    }
}

$tickets = $portal->listSupportTickets();
?>
<div class="container py-5">
  <div class="row g-4 align-items-start">
    <div class="col-lg-3">
      <?php require_once dirname(__DIR__, 2) . '/templates/admin-sidebar.php'; ?>
    </div>
    <div class="col-lg-9">
      <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
          <div class="hero-badge"><i data-lucide="message-square-more"></i> Support Operations</div>
          <h2 class="fw-bold mt-3 mb-1">Support Requests</h2>
          <p class="text-secondary mb-0">Review student tickets, respond with updates, and keep communication moving.</p>
        </div>
        <a href="<?= APP_URL ?>/admin/dashboard.php" class="btn btn-outline-success btn-sm"><i data-lucide="arrow-left"></i> Back to Dashboard</a>
      </div>

  <?php foreach ($errors as $error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endforeach; ?>
  <?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>

  <div class="row g-4">
    <?php foreach ($tickets as $ticket): ?>
      <div class="col-lg-6">
        <div class="glass-card p-4 h-100">
          <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
            <div>
              <h5 class="fw-bold mb-1"><?= e($ticket['subject']) ?></h5>
              <p class="text-secondary small mb-0">By <?= e($ticket['full_name'] ?? 'Unknown') ?> · <?= e($ticket['email'] ?? '') ?></p>
            </div>
            <span class="badge-soft"><?= e($ticket['status']) ?></span>
          </div>
          <p class="text-secondary mb-3"><?= e($ticket['message']) ?></p>
          <form method="post" class="mt-3">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="ticket_id" value="<?= (int)$ticket['id'] ?>">
            <textarea name="reply_message" class="form-control" rows="3" placeholder="Provide feedback or next steps..."></textarea>
            <button type="submit" class="btn btn-success mt-3">Send Reply</button>
          </form>
        </div>
      </div>
    <?php endforeach; ?>
    </div>
  </div>
</div>
<?php require_once dirname(__DIR__, 2) . '/templates/footer.php'; ?>
