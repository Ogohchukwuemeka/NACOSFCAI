<?php
require_once dirname(__DIR__) . '/config/bootstrap.php';
$auth = new Auth(Database::getInstance());
$auth->requireLogin('admin');
$portal = new Portal(Database::getInstance());
$errors = [];
$statusMessages = [
  'deleted' => 'Message deleted from the community chat.',
  'cleared' => 'Community chat history cleared.',
];
$success = $statusMessages[(string)($_GET['status'] ?? '')] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf();
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'delete' && (int)($_POST['message_id'] ?? 0) > 0) {
            if (!$portal->deleteCommunityPostAsAdmin((int)$_POST['message_id'])) {
                throw new RuntimeException('That message no longer exists.');
            }
          redirect('admin/community-chat.php?status=deleted');
        } elseif ($action === 'clear') {
          $portal->clearCommunityPosts();
          redirect('admin/community-chat.php?status=cleared');
        } else {
            throw new RuntimeException('Invalid moderation action.');
        }
    } catch (Throwable $e) {
        $errors[] = $e->getMessage();
    }
}

$messages = $portal->getCommunityPostsForAdmin();
require_once dirname(__DIR__) . '/templates/header.php';
?>
<div class="container py-5">
  <div class="row g-4 align-items-start">
    <div class="col-lg-3">
      <?php require_once dirname(__DIR__) . '/templates/admin-sidebar.php'; ?>
    </div>
    <div class="col-lg-9">
      <div class="d-flex justify-content-between align-items-start gap-3 mb-4">
        <div>
          <h2 class="fw-bold mb-1">Chat &amp; Community</h2>
          <p class="text-secondary mb-0">Review community messages and remove content when needed.</p>
        </div>
        <form method="post" onsubmit="return confirm('Permanently clear the entire community chat history? This cannot be undone.');">
          <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="action" value="clear">
          <button type="submit" class="btn btn-outline-danger" <?= $messages ? '' : 'disabled' ?>><i data-lucide="trash-2"></i> Clear history</button>
        </form>
      </div>

      <?php foreach ($errors as $error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endforeach; ?>
      <?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>

      <div class="glass-card p-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h5 class="fw-bold mb-0">Message history</h5>
          <span class="badge-soft"><?= count($messages) ?> messages</span>
        </div>
        <?php if (!$messages): ?>
          <p class="text-secondary mb-0">There are no community messages.</p>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead>
                <tr><th>Sender</th><th>Message</th><th>Date</th><th class="text-end">Action</th></tr>
              </thead>
              <tbody>
                <?php foreach ($messages as $message): ?>
                  <tr>
                    <td>
                      <div class="fw-semibold"><?= e((string)($message['sender'] ?? 'Student')) ?></div>
                      <div class="small text-secondary"><?= e((string)($message['full_name'] ?? 'Unknown student')) ?><?= !empty($message['email']) ? ' · ' . e((string)$message['email']) : '' ?></div>
                    </td>
                    <td class="text-break" style="min-width: 240px; max-width: 480px;"><?= nl2br(e((string)$message['content'])) ?></td>
                    <td class="text-nowrap"><?= e((string)$message['created_at']) ?></td>
                    <td class="text-end">
                      <form method="post" onsubmit="return confirm('Delete this message for everyone?');">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="message_id" value="<?= (int)$message['id'] ?>">
                        <button type="submit" class="btn btn-outline-danger btn-sm" aria-label="Delete message"><i data-lucide="trash-2"></i></button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php require_once dirname(__DIR__) . '/templates/footer.php'; ?>