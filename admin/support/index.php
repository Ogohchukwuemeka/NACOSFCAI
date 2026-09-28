<?php require_once dirname(__DIR__, 2) . '/templates/header.php'; ?>
<?php
$auth = new Auth(Database::getInstance());
$auth->requireLogin('admin');
$portal = new Portal(Database::getInstance());
$pdo = Database::getInstance()->getConnection();
$portal->ensureSupportSchema();

$errors = [];
$success = '';
$flashMessage = $_SESSION['flash_message'] ?? '';
$flashType = $_SESSION['flash_type'] ?? 'success';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);

$allowedCategories = ['Registration', 'Payment', 'ID Card', 'Certificate', 'Resources', 'Events', 'Technical Issue', 'Account', 'Others'];
$allowedStatuses = ['Open', 'Pending', 'In Progress', 'Awaiting Student Response', 'Resolved', 'Closed', 'Reopened'];
$allowedPriorities = ['Low', 'Medium', 'High', 'Critical'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf();
        $action = $_POST['action'] ?? '';
        $adminId = (int)($_SESSION['user_id'] ?? 0);

        if ($action === 'reply_ticket') {
            $ticketId = (int)($_POST['ticket_id'] ?? 0);
            $message = trim((string)($_POST['reply_message'] ?? ''));
            $status = trim((string)($_POST['status'] ?? 'in_progress'));
            $attachments = [];
            if (isset($_FILES['attachments']) && is_array($_FILES['attachments'])) {
                foreach ($_FILES['attachments']['tmp_name'] as $index => $tmpName) {
                    if (!is_uploaded_file($tmpName)) {
                        continue;
                    }
                    $originalName = $_FILES['attachments']['name'][$index] ?? 'file';
                    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
                    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'doc', 'docx', 'zip'];
                    if (!in_array($extension, $allowedExtensions, true)) {
                        throw new RuntimeException('Unsupported attachment type.');
                    }
                    $targetDir = dirname(__DIR__, 2) . '/assets/uploads/support';
                    if (!is_dir($targetDir)) {
                        mkdir($targetDir, 0777, true);
                    }
                    $fileName = uniqid('ticket_', true) . '.' . $extension;
                    $destination = $targetDir . '/' . $fileName;
                    if (!move_uploaded_file($tmpName, $destination)) {
                        throw new RuntimeException('Could not save the attachment.');
                    }
                    $attachments[] = ['path' => str_replace(dirname(__DIR__, 2) . '/', '', $destination), 'name' => $originalName, 'type' => $extension];
                }
            }
            if ($ticketId <= 0 || $message === '') {
                throw new RuntimeException('Please enter a reply before submitting.');
            }
            $portal->createSupportReply($ticketId, $adminId, $message, strtolower(str_replace(' ', '_', $status)), $attachments, true);
            $portal->updateSupportTicketStatus($ticketId, strtolower(str_replace(' ', '_', $status)), $adminId);
            $success = 'Reply sent successfully.';
        } elseif ($action === 'change_status') {
            $ticketId = (int)($_POST['ticket_id'] ?? 0);
            $status = trim((string)($_POST['status'] ?? 'open'));
            if ($ticketId > 0) {
                $portal->updateSupportTicketStatus($ticketId, strtolower(str_replace(' ', '_', $status)), $adminId);
                $success = 'Ticket status updated.';
            }
        } elseif ($action === 'change_priority') {
            $ticketId = (int)($_POST['ticket_id'] ?? 0);
            $priority = trim((string)($_POST['priority'] ?? 'medium'));
            if ($ticketId > 0) {
                $portal->updateSupportTicketPriority($ticketId, strtolower($priority), $adminId);
                $success = 'Ticket priority updated.';
            }
        } elseif ($action === 'assign_ticket') {
            $ticketId = (int)($_POST['ticket_id'] ?? 0);
            $assignedTo = (int)($_POST['assigned_to'] ?? 0);
            if ($ticketId > 0 && $assignedTo > 0) {
                $portal->assignSupportTicket($ticketId, $assignedTo, $adminId);
                $success = 'Ticket assigned successfully.';
            }
        } elseif ($action === 'delete_ticket') {
            $ticketId = (int)($_POST['ticket_id'] ?? 0);
            if ($ticketId > 0) {
                $portal->deleteSupportTicket($ticketId);
                $success = 'Ticket deleted.';
            }
        }

        $_SESSION['flash_message'] = $success !== '' ? $success : 'Action completed.';
        $_SESSION['flash_type'] = 'success';
    } catch (Exception $e) {
        $_SESSION['flash_message'] = $e->getMessage();
        $_SESSION['flash_type'] = 'error';
    }
    header('Location: ' . APP_URL . '/admin/support/index.php');
    exit;
}

$filters = [
    'search' => trim((string)($_GET['search'] ?? '')),
    'status' => trim((string)($_GET['status'] ?? '')),
    'priority' => trim((string)($_GET['priority'] ?? '')),
    'category' => trim((string)($_GET['category'] ?? '')),
    'assigned_to' => trim((string)($_GET['assigned_to'] ?? '')),
    'date_from' => trim((string)($_GET['date_from'] ?? '')),
    'date_to' => trim((string)($_GET['date_to'] ?? '')),
];

$tickets = $portal->listSupportTickets($filters);
$stats = $portal->getSupportStats();
$admins = $pdo->query("SELECT id, full_name FROM users WHERE role = 'admin' ORDER BY full_name ASC")->fetchAll();
$ticketStatuses = $pdo->query("SELECT status, COUNT(*) AS count FROM support_tickets GROUP BY status ORDER BY count DESC")->fetchAll();
?>
<div class="container py-5">
  <div class="row g-4 align-items-start">
    <div class="col-lg-3">
      <?php require_once dirname(__DIR__, 2) . '/templates/admin-sidebar.php'; ?>
    </div>
    <div class="col-lg-9">
      <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
          <div class="hero-badge"><i data-lucide="message-square-more"></i> Student Support & Helpdesk</div>
          <h2 class="fw-bold mt-3 mb-1">Support Desk Management</h2>
          <p class="text-secondary mb-0">Manage student issues, assign staff, reply professionally, and track every ticket in one place.</p>
        </div>
        <a href="<?= APP_URL ?>/admin/dashboard.php" class="btn btn-outline-success btn-sm"><i data-lucide="arrow-left"></i> Back to Dashboard</a>
      </div>

      <?php if ($flashMessage): ?>
        <div class="alert alert-<?= $flashType === 'error' ? 'danger' : 'success' ?> mb-4"><?= e($flashMessage) ?></div>
      <?php endif; ?>

      <div class="row g-3 mb-4">
        <div class="col-md-6 col-xl-3">
          <div class="glass-card p-3 stat-card h-100">
            <div class="d-flex justify-content-between align-items-center"><div class="small text-secondary">Total Tickets</div><i data-lucide="messages-square" class="text-success"></i></div>
            <h3 class="fw-bold mt-2 mb-0"><?= e((string)$stats['total']) ?></h3>
          </div>
        </div>
        <div class="col-md-6 col-xl-3">
          <div class="glass-card p-3 stat-card h-100">
            <div class="d-flex justify-content-between align-items-center"><div class="small text-secondary">Open Tickets</div><i data-lucide="circle-dot" class="text-warning"></i></div>
            <h3 class="fw-bold mt-2 mb-0"><?= e((string)$stats['open']) ?></h3>
          </div>
        </div>
        <div class="col-md-6 col-xl-3">
          <div class="glass-card p-3 stat-card h-100">
            <div class="d-flex justify-content-between align-items-center"><div class="small text-secondary">Pending Tickets</div><i data-lucide="clock-3" class="text-info"></i></div>
            <h3 class="fw-bold mt-2 mb-0"><?= e((string)$stats['pending']) ?></h3>
          </div>
        </div>
        <div class="col-md-6 col-xl-3">
          <div class="glass-card p-3 stat-card h-100">
            <div class="d-flex justify-content-between align-items-center"><div class="small text-secondary">In Progress</div><i data-lucide="loader" class="text-primary"></i></div>
            <h3 class="fw-bold mt-2 mb-0"><?= e((string)$stats['in_progress']) ?></h3>
          </div>
        </div>
        <div class="col-md-6 col-xl-3">
          <div class="glass-card p-3 stat-card h-100">
            <div class="d-flex justify-content-between align-items-center"><div class="small text-secondary">Resolved</div><i data-lucide="check-circle-2" class="text-success"></i></div>
            <h3 class="fw-bold mt-2 mb-0"><?= e((string)$stats['resolved']) ?></h3>
          </div>
        </div>
        <div class="col-md-6 col-xl-3">
          <div class="glass-card p-3 stat-card h-100">
            <div class="d-flex justify-content-between align-items-center"><div class="small text-secondary">Closed</div><i data-lucide="archive" class="text-secondary"></i></div>
            <h3 class="fw-bold mt-2 mb-0"><?= e((string)$stats['closed']) ?></h3>
          </div>
        </div>
        <div class="col-md-6 col-xl-3">
          <div class="glass-card p-3 stat-card h-100">
            <div class="d-flex justify-content-between align-items-center"><div class="small text-secondary">High Priority</div><i data-lucide="flag" class="text-danger"></i></div>
            <h3 class="fw-bold mt-2 mb-0"><?= e((string)$stats['high_priority']) ?></h3>
          </div>
        </div>
        <div class="col-md-6 col-xl-3">
          <div class="glass-card p-3 stat-card h-100">
            <div class="d-flex justify-content-between align-items-center"><div class="small text-secondary">Created Today</div><i data-lucide="calendar-days" class="text-success"></i></div>
            <h3 class="fw-bold mt-2 mb-0"><?= e((string)$stats['today']) ?></h3>
          </div>
        </div>
      </div>

      <div class="glass-card p-4 mb-4">
        <form method="get" class="row g-3 align-items-end">
          <div class="col-lg-3 col-md-6">
            <label class="form-label small fw-semibold">Search</label>
            <input type="text" name="search" class="form-control" value="<?= e((string)($filters['search'] ?? '')) ?>" placeholder="Name, matric, email, subject, ID">
          </div>
          <div class="col-lg-2 col-md-6">
            <label class="form-label small fw-semibold">Status</label>
            <select name="status" class="form-select">
              <option value="">All</option>
              <?php foreach ($allowedStatuses as $status): ?>
                <option value="<?= e(strtolower(str_replace(' ', '_', $status))) ?>" <?= ($filters['status'] ?? '') === strtolower(str_replace(' ', '_', $status)) ? 'selected' : '' ?>><?= e($status) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-lg-2 col-md-6">
            <label class="form-label small fw-semibold">Priority</label>
            <select name="priority" class="form-select">
              <option value="">All</option>
              <?php foreach ($allowedPriorities as $priority): ?>
                <option value="<?= e(strtolower($priority)) ?>" <?= ($filters['priority'] ?? '') === strtolower($priority) ? 'selected' : '' ?>><?= e($priority) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-lg-2 col-md-6">
            <label class="form-label small fw-semibold">Category</label>
            <select name="category" class="form-select">
              <option value="">All</option>
              <?php foreach ($allowedCategories as $category): ?>
                <option value="<?= e($category) ?>" <?= ($filters['category'] ?? '') === $category ? 'selected' : '' ?>><?= e($category) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-lg-2 col-md-6">
            <label class="form-label small fw-semibold">Assigned To</label>
            <select name="assigned_to" class="form-select">
              <option value="">All</option>
              <?php foreach ($admins as $admin): ?>
                <option value="<?= (int)$admin['id'] ?>" <?= ($filters['assigned_to'] ?? '') === (string)$admin['id'] ? 'selected' : '' ?>><?= e((string)($admin['full_name'] ?? 'Admin')) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-lg-1 col-md-6">
            <button class="btn btn-success w-100">Filter</button>
          </div>
        </form>
      </div>

      <div class="glass-card p-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h5 class="fw-bold mb-0">Support Ticket Records</h5>
          <div class="small text-secondary"><?= e((string)count($tickets)) ?> ticket(s) found</div>
        </div>
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead>
              <tr>
                <th>ID</th>
                <th>Student</th>
                <th>Matric</th>
                <th>Email</th>
                <th>Subject</th>
                <th>Category</th>
                <th>Priority</th>
                <th>Status</th>
                <th>Date</th>
                <th>Assigned</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($tickets as $ticket): ?>
                <tr>
                  <td>#<?= (int)$ticket['id'] ?></td>
                  <td><?= e((string)($ticket['full_name'] ?? 'Unknown')) ?></td>
                  <td><?= e((string)($ticket['matric_number'] ?? '—')) ?></td>
                  <td><?= e((string)($ticket['email'] ?? '—')) ?></td>
                  <td><?= e((string)($ticket['subject'] ?? '—')) ?></td>
                  <td><?= e((string)($ticket['category'] ?? 'Others')) ?></td>
                  <td><span class="badge bg-<?= ((string)($ticket['priority'] ?? 'medium') === 'critical' || (string)($ticket['priority'] ?? 'medium') === 'high') ? 'danger' : (((string)($ticket['priority'] ?? 'medium') === 'medium') ? 'warning' : 'secondary') ?> bg-opacity-10 text-<?= ((string)($ticket['priority'] ?? 'medium') === 'critical' || (string)($ticket['priority'] ?? 'medium') === 'high') ? 'danger' : (((string)($ticket['priority'] ?? 'medium') === 'medium') ? 'warning' : 'secondary') ?>"><?= e(ucwords((string)($ticket['priority'] ?? 'medium'))) ?></span></td>
                  <td><span class="badge bg-<?= ((string)($ticket['status'] ?? 'open') === 'resolved' || (string)($ticket['status'] ?? 'open') === 'closed') ? 'success' : (((string)($ticket['status'] ?? 'open') === 'pending' || (string)($ticket['status'] ?? 'open') === 'awaiting_student_response') ? 'warning' : 'primary') ?> bg-opacity-10 text-<?= ((string)($ticket['status'] ?? 'open') === 'resolved' || (string)($ticket['status'] ?? 'open') === 'closed') ? 'success' : (((string)($ticket['status'] ?? 'open') === 'pending' || (string)($ticket['status'] ?? 'open') === 'awaiting_student_response') ? 'warning' : 'primary') ?>"><?= e(ucwords(str_replace('_', ' ', (string)($ticket['status'] ?? 'open')))) ?></span></td>
                  <td><?= e((string)($ticket['created_at'] ?? '')) ?></td>
                  <td><?= e((string)($ticket['assigned_admin_name'] ?? 'Unassigned')) ?></td>
                  <td>
                    <div class="dropdown">
                      <button class="btn btn-outline-success btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">Actions</button>
                      <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#ticketModal<?= (int)$ticket['id'] ?>">View</a></li>
                        <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#replyModal<?= (int)$ticket['id'] ?>">Reply</a></li>
                        <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#assignModal<?= (int)$ticket['id'] ?>">Assign</a></li>
                        <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#priorityModal<?= (int)$ticket['id'] ?>">Change Priority</a></li>
                        <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#statusModal<?= (int)$ticket['id'] ?>">Change Status</a></li>
                        <li>
                          <form method="post" class="d-inline" onsubmit="return confirm('Delete this ticket?');">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="action" value="delete_ticket">
                            <input type="hidden" name="ticket_id" value="<?= (int)$ticket['id'] ?>">
                            <button class="dropdown-item text-danger" type="submit">Delete</button>
                          </form>
                        </li>
                      </ul>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<?php foreach ($tickets as $ticket): ?>
  <?php $details = $portal->getSupportTicketDetails((int)$ticket['id']); $messages = $portal->getSupportTicketMessages((int)$ticket['id']); $attachments = $portal->listSupportTicketAttachments((int)$ticket['id']); $events = $portal->listSupportTicketEvents((int)$ticket['id']); ?>
  <div class="modal fade" id="ticketModal<?= (int)$ticket['id'] ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
      <div class="modal-content border-0 shadow-lg">
        <div class="modal-header border-0">
          <div>
            <h5 class="modal-title fw-bold">Ticket #<?= (int)$ticket['id'] ?> - <?= e((string)($details['subject'] ?? '')) ?></h5>
            <p class="text-secondary small mb-0">Student support conversation and details</p>
          </div>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body p-4">
          <div class="row g-4">
            <div class="col-lg-4">
              <div class="glass-card p-3 mb-3">
                <h6 class="fw-bold mb-3">Student Information</h6>
                <div class="d-flex align-items-center gap-3 mb-3">
                  <div class="rounded-circle bg-success bg-opacity-10 p-3"><i data-lucide="user"></i></div>
                  <div>
                    <div class="fw-semibold"><?= e((string)($details['full_name'] ?? 'Unknown')) ?></div>
                    <div class="small text-secondary"><?= e((string)($details['matric_number'] ?? '—')) ?></div>
                  </div>
                </div>
                <div class="small text-secondary">Email: <?= e((string)($details['email'] ?? '—')) ?></div>
                <div class="small text-secondary">Phone: <?= e((string)($details['phone'] ?? '—')) ?></div>
                <div class="small text-secondary">Course: <?= e((string)($details['course'] ?? '—')) ?></div>
                <div class="small text-secondary">Level: <?= e((string)($details['level'] ?? '—')) ?></div>
              </div>
              <div class="glass-card p-3 mb-3">
                <h6 class="fw-bold mb-3">Ticket Information</h6>
                <div class="small text-secondary mb-2">Ticket Number: #<?= (int)($details['id'] ?? 0) ?></div>
                <div class="small text-secondary mb-2">Subject: <?= e((string)($details['subject'] ?? '—')) ?></div>
                <div class="small text-secondary mb-2">Category: <?= e((string)($details['category'] ?? 'Others')) ?></div>
                <div class="small text-secondary mb-2">Priority: <?= e(ucwords((string)($details['priority'] ?? 'medium'))) ?></div>
                <div class="small text-secondary mb-2">Status: <?= e(ucwords(str_replace('_', ' ', (string)($details['status'] ?? 'open')))) ?></div>
                <div class="small text-secondary mb-2">Date Created: <?= e((string)($details['created_at'] ?? '')) ?></div>
                <div class="small text-secondary mb-2">Assigned To: <?= e((string)($details['assigned_admin_name'] ?? 'Unassigned')) ?></div>
              </div>
              <div class="glass-card p-3">
                <h6 class="fw-bold mb-3">Attachments</h6>
                <?php if ($attachments): ?>
                  <div class="d-grid gap-2">
                    <?php foreach ($attachments as $attachment): ?>
                      <a class="btn btn-outline-success btn-sm" href="<?= e((string)($attachment['file_path'] ?? '')) ?>" target="_blank"><?= e((string)($attachment['file_name'] ?? 'Attachment')) ?></a>
                    <?php endforeach; ?>
                  </div>
                <?php else: ?><div class="small text-secondary">No attachments.</div><?php endif; ?>
              </div>
            </div>
            <div class="col-lg-8">
              <div class="glass-card p-3 mb-3">
                <h6 class="fw-bold mb-3">Conversation History</h6>
                <div class="d-grid gap-2" style="max-height: 420px; overflow:auto;">
                  <?php foreach ($messages as $message): ?>
                    <?php $isAdmin = ((int)$message['sender_id'] ?? 0) !== (int)$details['user_id']; ?>
                    <div class="d-flex <?= $isAdmin ? 'justify-content-end' : 'justify-content-start' ?>">
                      <div class="p-3 rounded-4 <?= $isAdmin ? 'bg-success text-white' : 'bg-light' ?>" style="max-width: 80%;">
                        <div class="small fw-semibold mb-1"><?= e((string)($message['full_name'] ?? 'System')) ?></div>
                        <div><?= e((string)($message['message'] ?? '')) ?></div>
                        <div class="small mt-2 opacity-75"><?= e((string)($message['created_at'] ?? '')) ?></div>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              </div>
              <div class="glass-card p-3">
                <h6 class="fw-bold mb-3">Reply to Ticket</h6>
                <form method="post" enctype="multipart/form-data">
                  <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                  <input type="hidden" name="action" value="reply_ticket">
                  <input type="hidden" name="ticket_id" value="<?= (int)$ticket['id'] ?>">
                  <textarea name="reply_message" class="form-control" rows="4" placeholder="Write your response..."></textarea>
                  <input type="file" class="form-control mt-3" name="attachments[]" multiple>
                  <div class="d-flex gap-2 mt-3">
                    <button class="btn btn-success">Send Reply</button>
                    <select name="status" class="form-select w-auto">
                      <?php foreach ($allowedStatuses as $status): ?>
                        <option value="<?= e(strtolower(str_replace(' ', '_', $status))) ?>" <?= strtolower(str_replace(' ', '_', (string)($details['status'] ?? 'open'))) === strtolower(str_replace(' ', '_', $status)) ? 'selected' : '' ?>><?= e($status) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                </form>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="assignModal<?= (int)$ticket['id'] ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header"><h5 class="modal-title">Assign Ticket</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <form method="post">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="assign_ticket">
            <input type="hidden" name="ticket_id" value="<?= (int)$ticket['id'] ?>">
            <select name="assigned_to" class="form-select">
              <option value="">Select admin</option>
              <?php foreach ($admins as $admin): ?>
                <option value="<?= (int)$admin['id'] ?>"><?= e((string)($admin['full_name'] ?? 'Admin')) ?></option>
              <?php endforeach; ?>
            </select>
            <button class="btn btn-success mt-3">Assign</button>
          </form>
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="priorityModal<?= (int)$ticket['id'] ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header"><h5 class="modal-title">Change Priority</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <form method="post">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="change_priority">
            <input type="hidden" name="ticket_id" value="<?= (int)$ticket['id'] ?>">
            <select name="priority" class="form-select">
              <?php foreach ($allowedPriorities as $priority): ?>
                <option value="<?= e(strtolower($priority)) ?>"><?= e($priority) ?></option>
              <?php endforeach; ?>
            </select>
            <button class="btn btn-success mt-3">Update Priority</button>
          </form>
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="statusModal<?= (int)$ticket['id'] ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header"><h5 class="modal-title">Change Status</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <form method="post">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="change_status">
            <input type="hidden" name="ticket_id" value="<?= (int)$ticket['id'] ?>">
            <select name="status" class="form-select">
              <?php foreach ($allowedStatuses as $status): ?>
                <option value="<?= e(strtolower(str_replace(' ', '_', $status))) ?>"><?= e($status) ?></option>
              <?php endforeach; ?>
            </select>
            <button class="btn btn-success mt-3">Update Status</button>
          </form>
        </div>
      </div>
    </div>
  </div>
<?php endforeach; ?>

<?php require_once dirname(__DIR__, 2) . '/templates/footer.php'; ?>