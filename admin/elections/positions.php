<?php require_once dirname(__DIR__, 2) . '/templates/header.php'; ?>
<?php
$auth = new Auth(Database::getInstance());
$auth->requireLogin('admin');
$election = new Election(Database::getInstance());

$electionId = (int)($_GET['election_id'] ?? 0);
$editId = (int)($_GET['edit'] ?? 0);
$errors = [];
$success = '';

if (!$electionId) {
    echo '<div class="container py-5"><div class="alert alert-danger">Election not found.</div></div>';
    require_once dirname(__DIR__, 2) . '/templates/footer.php';
    exit;
}

$elec = $election->getElection($electionId);
if (!$elec) {
    echo '<div class="container py-5"><div class="alert alert-danger">Election not found.</div></div>';
    require_once dirname(__DIR__, 2) . '/templates/footer.php';
    exit;
}
  $ballotLocked = $election->isBallotLocked($electionId);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $csrfValid = true;
    try {
        verify_csrf();
    } catch (Exception $e) {
        $errors[] = 'Invalid CSRF token.';
    $csrfValid = false;
    }

  $action = $csrfValid && !$ballotLocked ? ($_POST['action'] ?? '') : '';
  if ($csrfValid && $ballotLocked) {
    $errors[] = 'Position changes are locked because voting has started or this election already contains votes.';
  }

    if ($action === 'save_position') {
        $positionName = trim($_POST['position_name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $displayOrder = (int)($_POST['display_order'] ?? 0);
        
        if (empty($positionName)) {
            $errors[] = 'Position name is required.';
        }
        
        if (empty($errors)) {
            if ($editId) {
              if (!$election->updatePosition($editId, [
                    'position_name' => $positionName,
                    'description' => $description,
                    'display_order' => $displayOrder,
              ], $electionId)) {
                $errors[] = 'Position could not be updated; verify it belongs to this election and the ballot is not locked.';
              } else {
                $success = 'Position updated successfully.';
                $editId = 0;
              }
            } else {
                $election->createPosition([
                    'election_id' => $electionId,
                    'position_name' => $positionName,
                    'description' => $description,
                    'display_order' => $displayOrder,
                ]);
                $success = 'Position created successfully.';
            }
        }
    } elseif ($action === 'delete_position') {
        $deleteId = (int)($_POST['position_id'] ?? 0);
        if ($deleteId > 0 && $election->deletePosition($deleteId, $electionId)) {
            $success = 'Position deleted.';
            if ($editId === $deleteId) {
                $editId = 0;
            }
        } else {
          $errors[] = 'Position could not be deleted; verify it belongs to this election and the ballot is not locked.';
        }
    }
}

$positions = $election->listPositions($electionId);
$editPosition = $editId ? $election->getPosition($editId) : null;
if ($editPosition && (int)$editPosition['election_id'] !== $electionId) {
  $editPosition = null;
}
?>
<div class="container py-5">
  <div class="row g-4 align-items-start">
    <div class="col-lg-3">
      <?php require_once dirname(__DIR__, 2) . '/templates/admin-sidebar.php'; ?>
    </div>
    <div class="col-lg-9">
      <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
          <div class="hero-badge"><i data-lucide="briefcase"></i> Positions</div>
          <h2 class="fw-bold mt-3 mb-1"><?= e((string)($elec['title'] ?? '—')) ?></h2>
          <p class="text-secondary mb-0">Create and manage election positions.</p>
        </div>
        <a href="<?= APP_URL ?>/admin/elections.php?action=view&id=<?= $electionId ?>" class="btn btn-outline-secondary btn-sm"><i data-lucide="arrow-left"></i> Back</a>
      </div>

      <?php if ($ballotLocked): ?>
        <div class="alert alert-warning">Positions are locked after voting starts to protect the ballot and preserve results.</div>
      <?php endif; ?>

      <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
          <?= e($success) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>

      <?php if ($errors): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
          <?php foreach ($errors as $error): ?>
            <div><?= e($error) ?></div>
          <?php endforeach; ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>

      <div class="row g-4 mb-4">
        <?php if (!$ballotLocked): ?><div class="col-lg-6">
          <div class="glass-card p-4">
            <h5 class="fw-bold mb-3"><?= $editPosition ? 'Edit Position' : 'Create New Position' ?></h5>
            <form method="POST">
              <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="action" value="save_position">
              <div class="mb-3">
                <label class="form-label">Position Name*</label>
                <input type="text" name="position_name" class="form-control" value="<?= e((string)($editPosition['position_name'] ?? '')) ?>" placeholder="e.g. President" required>
              </div>
              <div class="mb-3">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-control" rows="3" placeholder="Position responsibilities..."><?= e((string)($editPosition['description'] ?? '')) ?></textarea>
              </div>
              <div class="mb-3">
                <label class="form-label">Display Order</label>
                <input type="number" name="display_order" class="form-control" value="<?= (int)($editPosition['display_order'] ?? 0) ?>">
              </div>
              <div class="d-flex gap-2">
                <button type="submit" class="btn btn-success"><?= $editPosition ? 'Update Position' : 'Create Position' ?></button>
                <?php if ($editPosition): ?>
                  <a href="<?= APP_URL ?>/admin/elections/positions.php?election_id=<?= $electionId ?>" class="btn btn-outline-secondary">Cancel</a>
                <?php endif; ?>
              </div>
            </form>
          </div>
        </div><?php endif; ?>

        <div class="col-lg-6">
          <div class="glass-card p-4">
            <h5 class="fw-bold mb-3">Positions (<?= count($positions) ?>)</h5>
            <?php if (empty($positions)): ?>
              <p class="text-secondary">No positions created yet.</p>
            <?php else: ?>
              <div class="d-grid gap-2">
                <?php foreach ($positions as $pos): ?>
                  <div class="d-flex justify-content-between align-items-center p-2 rounded bg-light">
                    <div>
                      <div class="fw-bold"><?= e((string)($pos['position_name'] ?? '—')) ?></div>
                      <small class="text-secondary"><?= e(substr((string)($pos['description'] ?? ''), 0, 60)) ?></small>
                    </div>
                    <?php if (!$ballotLocked): ?><div class="btn-group btn-group-sm">
                      <a href="<?= APP_URL ?>/admin/elections/positions.php?election_id=<?= $electionId ?>&edit=<?= (int)$pos['id'] ?>" class="btn btn-outline-primary">Edit</a>
                      <form method="POST" class="d-inline" onsubmit="return confirm('Delete this position?');">
                          <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                          <input type="hidden" name="action" value="delete_position">
                          <input type="hidden" name="position_id" value="<?= (int)$pos['id'] ?>">
                          <button type="submit" class="btn btn-outline-danger">Delete</button>
                      </form>
                    </div><?php endif; ?>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<?php require_once dirname(__DIR__, 2) . '/templates/footer.php'; ?>
