<?php require_once dirname(__DIR__) . '/templates/header.php'; ?>
<?php
$auth = new Auth(Database::getInstance());
$auth->requireLogin('admin');
$election = new Election(Database::getInstance());
$pdo = Database::getInstance()->getConnection();

$action = $_GET['action'] ?? 'list';
$errors = [];
$success = '';
$electionId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$elections = $election->listElections();
$postAction = $_POST['action'] ?? '';
$actionToProcess = $postAction ?: $action;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  try {
    verify_csrf();
  } catch (Exception $e) {
    $errors[] = 'Invalid CSRF token.';
  }

  if (!empty($errors)) {
    $actionToProcess = '';
  }
    if ($actionToProcess === 'create') {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $start_time = trim($_POST['start_time'] ?? '');
        $end_time = trim($_POST['end_time'] ?? '');
        $status = $_POST['status'] ?? 'draft';
        
        if (empty($title)) {
            $errors[] = 'Election title is required.';
        }
        if (empty($start_time) || empty($end_time)) {
            $errors[] = 'Start and end dates are required.';
        }
        if (strtotime($start_time) === false || strtotime($end_time) === false) {
          $errors[] = 'Enter valid start and end dates.';
        } elseif (strtotime($start_time) >= strtotime($end_time)) {
            $errors[] = 'End date must be after start date.';
        }
        if (!in_array($status, ['draft', 'scheduled', 'active', 'closed'], true)) {
          $errors[] = 'Invalid election status selected.';
        }
        
        if (empty($errors)) {
            $id = $election->createElection([
                'title' => $title,
                'description' => $description,
                'start_time' => $start_time,
                'end_time' => $end_time,
                'status' => $status,
                'created_by' => $_SESSION['user_id'] ?? null,
            ]);
            $success = 'Election created successfully.';
            header('Location: ' . APP_URL . '/admin/elections.php?action=view&id=' . $id);
            exit;
        }
    } elseif ($actionToProcess === 'update' && $electionId) {
        $status = $_POST['status'] ?? 'draft';
        $election->updateElection($electionId, ['status' => $status]);
        $success = 'Election updated successfully.';
    } elseif ($actionToProcess === 'delete' && $electionId) {
        $election->deleteElection($electionId);
        $success = 'Election deleted successfully.';
        header('Location: ' . APP_URL . '/admin/elections.php');
        exit;
    } elseif ($actionToProcess === 'view' && $electionId && isset($_POST['update_status'])) {
        $status = $_POST['status'] ?? '';
        $allowed = ['draft', 'scheduled', 'active', 'closed'];
        if (in_array($status, $allowed, true)) {
            $election->updateElection($electionId, ['status' => $status]);
            $success = 'Election status updated to ' . ucfirst($status) . '.';
        } else {
            $errors[] = 'Invalid election status selected.';
        }
    } elseif ($actionToProcess === 'view' && $electionId && isset($_POST['send_results_email'])) {
        if ($election->sendElectionResultsEmail($electionId)) {
            $success = 'Election results were emailed to all active students.';
        } else {
            $errors[] = 'Unable to send election results email. Please check mail settings.';
        }
    }
}
?>

<?php if (!empty($errors)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
          <?php foreach ($errors as $error): ?>
            <div><?= e($error) ?></div>
          <?php endforeach; ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
<?php endif; ?>

<?php if ($action === 'list'): ?>
        <div class="glass-card p-4">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="fw-bold mb-0">Elections</h5>
            <a href="<?= APP_URL ?>/admin/elections.php?action=create" class="btn btn-success btn-sm">Create Election</a>
          </div>
          <div class="table-responsive">
            <table class="table table-hover align-middle">
              <thead>
                <tr><th>Title</th><th>Start Date</th><th>End Date</th><th>Status</th><th>Actions</th></tr>
              </thead>
              <tbody>
                <?php foreach ($elections as $elec): ?>
                  <tr>
                    <td><strong><?= e((string)($elec['title'] ?? '—')) ?></strong></td>
                    <td><?= e(substr((string)($elec['start_time'] ?? ''), 0, 10)) ?></td>
                    <td><?= e(substr((string)($elec['end_time'] ?? ''), 0, 10)) ?></td>
                    <td><span class="badge bg-<?= ((string)($elec['status'] ?? 'draft') === 'active') ? 'success' : (((string)($elec['status'] ?? 'draft') === 'closed') ? 'secondary' : 'warning') ?>"><?= e(ucfirst((string)($elec['status'] ?? 'draft'))) ?></span></td>
                    <td>
                      <div class="btn-group btn-group-sm">
                        <a href="<?= APP_URL ?>/admin/elections.php?action=view&id=<?= (int)$elec['id'] ?>" class="btn btn-outline-primary">View</a>
                        <a href="<?= APP_URL ?>/admin/elections.php?action=edit&id=<?= (int)$elec['id'] ?>" class="btn btn-outline-secondary">Edit</a>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this election?');">
                          <input type="hidden" name="action" value="delete">
                          <input type="hidden" name="id" value="<?= (int)$elec['id'] ?>">
                          <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                          <button type="submit" class="btn btn-outline-danger btn-sm">Delete</button>
                        </form>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>

      <?php elseif ($action === 'create'): ?>
        <div class="glass-card p-4">
          <h5 class="fw-bold mb-3">Create New Election</h5>
          <form method="POST">
            <div class="mb-3">
              <label class="form-label">Election Title*</label>
              <input type="text" name="title" class="form-control" placeholder="e.g. NACOS Executive Election 2026" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Description</label>
              <textarea name="description" class="form-control" rows="3" placeholder="Election details and context..."></textarea>
            </div>
            <div class="row g-3 mb-3">
              <div class="col-md-6">
                <label class="form-label">Start Date & Time*</label>
                <input type="datetime-local" name="start_time" class="form-control" required>
              </div>
              <div class="col-md-6">
                <label class="form-label">End Date & Time*</label>
                <input type="datetime-local" name="end_time" class="form-control" required>
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label">Initial Status</label>
              <select name="status" class="form-select">
                <option value="draft">Draft</option>
                <option value="scheduled">Scheduled</option>
                <option value="active">Active</option>
                <option value="closed">Closed</option>
              </select>
            </div>
                <input type="hidden" name="action" value="create">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
              <div class="d-flex gap-2">
                <button type="submit" class="btn btn-success">Create Election</button>
                <a href="<?= APP_URL ?>/admin/elections.php" class="btn btn-outline-secondary">Cancel</a>
              </div>
            </form>
        </div>

      <?php elseif ($action === 'view' && $electionId): ?>
        <?php
        $election->refreshElectionStatus($electionId);
        $elec = $election->getElection($electionId);
        $positions = $election->listPositions($electionId);
        if (!$elec) {
            echo '<div class="alert alert-danger">Election not found.</div>';
        }
        if ($elec) {
            $now = time();
            $startsAt = strtotime($elec['start_time']);
            $endsAt = strtotime($elec['end_time']);
            $countdownLabel = $now < $startsAt ? 'Starts in' : 'Ends in';
            $countdownTarget = $now < $startsAt ? $startsAt : $endsAt;
        ?>
        <div class="glass-card p-4 mb-4">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
              <h5 class="fw-bold mb-1"><?= e((string)($elec['title'] ?? '—')) ?></h5>
              <p class="text-secondary mb-0"><?= e((string)($elec['description'] ?? '')) ?></p>
            </div>
            <span class="badge bg-<?= ((string)($elec['status'] ?? 'draft') === 'active') ? 'success' : (((string)($elec['status'] ?? 'draft') === 'closed') ? 'secondary' : 'warning') ?>"><?= e(ucfirst((string)($elec['status'] ?? 'draft'))) ?></span>
          </div>
          <div class="row g-3 mb-3">
            <div class="col-md-4"><small class="text-secondary">Starts:</small><div class="fw-bold"><?= e(substr((string)($elec['start_time'] ?? ''), 0, 16)) ?></div></div>
            <div class="col-md-4"><small class="text-secondary">Ends:</small><div class="fw-bold"><?= e(substr((string)($elec['end_time'] ?? ''), 0, 16)) ?></div></div>
            <div class="col-md-4"><small class="text-secondary">Offices:</small><div class="fw-bold"><?= count($positions) ?></div></div>
          </div>
          <div class="d-flex gap-2 align-items-center flex-wrap">
            <a href="<?= APP_URL ?>/admin/elections/candidates.php?election_id=<?= (int)$elec['id'] ?>" class="btn btn-primary btn-sm">Manage Candidates</a>
            <a href="<?= APP_URL ?>/admin/elections/results.php?election_id=<?= (int)$elec['id'] ?>" class="btn btn-info btn-sm">View Results</a>
            <a href="<?= APP_URL ?>/admin/elections/results-export.php?election_id=<?= (int)$elec['id'] ?>&format=pdf" class="btn btn-outline-danger btn-sm">Export PDF</a>
            <a href="<?= APP_URL ?>/admin/elections/results-export.php?election_id=<?= (int)$elec['id'] ?>&format=excel" class="btn btn-outline-success btn-sm">Export Excel</a>
            <form method="POST" class="d-inline-flex gap-2 align-items-center">
              <input type="hidden" name="action" value="view">
              <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="send_results_email" value="1">
              <button type="submit" class="btn btn-outline-primary btn-sm">Email Results</button>
            </form>
            <a href="<?= APP_URL ?>/admin/elections.php" class="btn btn-outline-secondary btn-sm">Back</a>
          </div>
          <div class="mt-3 d-flex flex-wrap gap-2 align-items-center">
            <?php $isEnded = strtotime($elec['end_time']) <= time(); ?>
            <form method="POST" class="d-inline-flex gap-2 align-items-center">
              <input type="hidden" name="action" value="view">
              <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="update_status" value="1">
              <input type="hidden" name="status" value="<?= ((string)($elec['status'] ?? 'draft') === 'active') ? 'closed' : 'active' ?>">
              <button type="submit" class="btn btn-<?= ((string)($elec['status'] ?? 'draft') === 'active') ? 'danger' : 'success' ?> btn-sm" <?= $isEnded ? 'disabled' : '' ?>>
                <?= ((string)($elec['status'] ?? 'draft') === 'active') ? 'Close Voting' : 'Open Voting' ?>
              </button>
            </form>
            <?php if ($isEnded): ?>
              <span class="badge bg-secondary">Election ended</span>
            <?php else: ?>
              <span class="text-secondary">Use this toggle to open or close voting manually.</span>
            <?php endif; ?>
          </div>
        </div>

        <div class="glass-card p-4">
          <h5 class="fw-bold mb-3">Election Summary</h5>
          <div class="row g-3">
            <div class="col-md-4">
              <div class="fw-bold h3"><?= count($positions) ?></div>
              <small class="text-secondary">Position offices</small>
            </div>
            <div class="col-md-4">
              <div class="fw-bold h3"><?= count($election->listCandidates($electionId)) ?></div>
              <small class="text-secondary">Registered candidates</small>
            </div>
            <div class="col-md-4">
              <div class="fw-bold h3"><?= number_format((int)$election->getElectionStats($electionId)['total_votes_cast']) ?></div>
              <small class="text-secondary">Votes cast</small>
            </div>
          </div>
          <div class="mt-3">
            <p class="text-secondary mb-1">Use the candidate panel to register or remove candidates. Toggle election status to open or close voting, then publish results and email updates to students.</p>
          </div>
        </div>
        <?php } ?>

      <?php elseif ($action === 'edit' && $electionId): ?>
        <?php
        $elec = $election->getElection($electionId);
        if (!$elec) {
            echo '<div class="alert alert-danger">Election not found.</div>';
        }
        if ($elec) {
        ?>
        <div class="glass-card p-4">
          <h5 class="fw-bold mb-3">Edit Election: <?= e((string)($elec['title'] ?? '—')) ?></h5>
          <form method="POST">
            <div class="mb-3">
              <label class="form-label">Status</label>
              <select name="status" class="form-select">
                <option value="draft" <?= ((string)($elec['status'] ?? 'draft') === 'draft') ? 'selected' : '' ?>>Draft</option>
                <option value="scheduled" <?= ((string)($elec['status'] ?? 'draft') === 'scheduled') ? 'selected' : '' ?>>Scheduled</option>
                <option value="active" <?= ((string)($elec['status'] ?? 'draft') === 'active') ? 'selected' : '' ?>>Active</option>
                <option value="closed" <?= ((string)($elec['status'] ?? 'draft') === 'closed') ? 'selected' : '' ?>>Closed</option>
              </select>
            </div>
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
              <div class="d-flex gap-2">
                <button type="submit" class="btn btn-success">Update Election</button>
                <a href="<?= APP_URL ?>/admin/elections.php?action=view&id=<?= (int)$elec['id'] ?>" class="btn btn-outline-secondary">Back</a>
              </div>
            </form>
        </div>
        <?php } ?>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php require_once dirname(__DIR__) . '/templates/footer.php'; ?>
