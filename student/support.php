<?php require_once dirname(__DIR__) . '/templates/header.php'; ?>
<?php
$auth = new Auth(Database::getInstance());
$auth->requireLogin('student');
$user = $auth->currentUser();
$portal = new Portal(Database::getInstance());
$errors = [];
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf();
        $portal->createSupportTicket([
            'student_id' => (int)($user['id'] ?? 0),
            'subject' => $_POST['subject'] ?? '',
            'message' => $_POST['message'] ?? '',
            'priority' => $_POST['priority'] ?? 'normal',
            'status' => 'open',
        ]);
        $success = 'Support ticket submitted.';
    } catch (Exception $e) {
        $errors[] = $e->getMessage();
    }
}
$tickets = $portal->getStudentTickets((int)($user['id'] ?? 0));
?>
<div class="container py-5">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h4 class="fw-bold mb-1">Support Centre</h4>
      <p class="text-secondary mb-0">Raise a ticket and track your requests.</p>
    </div>
    
  </div>
  <div class="row g-4">
    <div class="col-lg-5">
      <div class="card shadow-sm border-0 rounded-4 p-4">
        <h4 class="fw-bold">Create Support Ticket</h4>
        <form method="post">
          <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
          <?php foreach ($errors as $error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endforeach; ?>
          <?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
          <div class="mb-3">
            <label class="form-label">Subject</label>
            <input type="text" name="subject" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Priority</label>
            <select name="priority" class="form-select">
              <option value="low">Low</option>
              <option value="normal" selected>Normal</option>
              <option value="high">High</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Message</label>
            <textarea name="message" class="form-control" rows="5" required></textarea>
          </div>
          <button class="btn btn-primary">Submit Ticket</button>
        </form>
      </div>
    </div>
    <div class="col-lg-7">
      <div class="card shadow-sm border-0 rounded-4 p-4">
        <h4 class="fw-bold">My Tickets</h4>
        <div class="list-group mt-3">
          <?php foreach ($tickets as $ticket): ?>
            <div class="list-group-item">
              <div class="d-flex justify-content-between align-items-start gap-3">
                <div>
                  <h6 class="fw-bold mb-1"><?= e($ticket['subject']) ?></h6>
                  <p class="mb-0 text-secondary"><?= e($ticket['message']) ?></p>
                </div>
                <span class="badge bg-primary-subtle text-primary"><?= e($ticket['status']) ?></span>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
</div>
<?php require_once dirname(__DIR__) . '/templates/footer.php'; ?>
