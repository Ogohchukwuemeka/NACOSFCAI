<?php require_once dirname(__DIR__, 2) . '/templates/header.php'; ?>
<?php
$auth = new Auth(Database::getInstance());
$auth->requireLogin('admin');
$election = new Election(Database::getInstance());

$electionId = (int)($_GET['election_id'] ?? 0);
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

$uploadDir = dirname(__DIR__, 2) . '/assets/uploads/candidates';
if (!is_dir($uploadDir)) {
  mkdir($uploadDir, 0755, true);
}

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
    $errors[] = 'Candidate changes are locked because voting has started or this election already contains votes.';
  }
    
    if ($action === 'add_candidate') {
        $studentId = (int)($_POST['student_id'] ?? 0);
        $positionName = trim($_POST['position_name'] ?? '');
        $manifesto = trim($_POST['manifesto'] ?? '');
        
        $pdo = Database::getInstance()->getConnection();
        $stmt = $pdo->prepare('SELECT id, full_name, matric_number FROM users WHERE id = :id AND role = :role');
        $stmt->execute(['id' => $studentId, 'role' => 'student']);
        $student = $stmt->fetch();
        
        if (!$student) {
            $errors[] = 'Invalid student selected.';
        } elseif ($positionName === '') {
            $errors[] = 'Position is required.';
        } else {
            $imagePath = null;
            if (isset($_FILES['candidate_image']) && $_FILES['candidate_image']['error'] !== UPLOAD_ERR_NO_FILE) {
              $upload = $_FILES['candidate_image'];
              $allowedTypes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
              $mime = (new finfo(FILEINFO_MIME_TYPE))->file($upload['tmp_name']);
              if ($upload['error'] !== UPLOAD_ERR_OK || $upload['size'] > 2 * 1024 * 1024 || !isset($allowedTypes[$mime]) || @getimagesize($upload['tmp_name']) === false) {
                $errors[] = 'Candidate image must be a valid JPG, PNG, or WebP file under 2MB.';
              } else {
                $filename = 'candidate-' . bin2hex(random_bytes(12)) . '.' . $allowedTypes[$mime];
                $filepath = $uploadDir . '/' . $filename;
                if (move_uploaded_file($upload['tmp_name'], $filepath)) {
                  $imagePath = 'assets/uploads/candidates/' . $filename;
                } else {
                  $errors[] = 'Candidate image could not be saved.';
                }
                }
            }

            if (empty($errors)) {
              $election->createCandidate([
                'election_id' => $electionId,
                'position_name' => $positionName,
                'student_id' => $studentId,
                'manifesto' => $manifesto,
                'image_path' => $imagePath,
              ]);
              $success = 'Candidate registered successfully and is pending approval.';
            }
        }
    } elseif ($action === 'toggle_approval') {
      $candidateId = (int)($_POST['candidate_id'] ?? 0);
      $approved = (int)($_POST['approved'] ?? 0) === 1 ? 1 : 0;
      if ($candidateId > 0 && $election->updateCandidate($candidateId, ['approved' => $approved], $electionId)) {
        $success = $approved ? 'Candidate approved for voting.' : 'Candidate moved back to pending.';
      } else {
        $errors[] = 'Unable to update candidate approval.';
      }
    } elseif ($action === 'delete_candidate') {
        $deleteId = (int)($_POST['candidate_id'] ?? 0);
        if ($deleteId > 0) {
            if ($election->deleteCandidate($deleteId, $electionId)) {
              $success = 'Candidate removed.';
            } else {
              $errors[] = 'Candidate could not be removed. It may have recorded votes or the ballot is locked.';
            }
        } else {
            $errors[] = 'Invalid candidate selected for removal.';
        }
    }
}

$candidates = $election->listCandidates($electionId);
$positions = $election->listPositions($electionId);
$pdo = Database::getInstance()->getConnection();
$students = $pdo->query("SELECT id, full_name, matric_number, email FROM users WHERE role = 'student' AND status = 'active' ORDER BY full_name ASC")->fetchAll();
?>
<div class="container py-5">
  <div class="row g-4 align-items-start">
    <div class="col-lg-3">
      <?php require_once dirname(__DIR__, 2) . '/templates/admin-sidebar.php'; ?>
    </div>
    <div class="col-lg-9">
      <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
          <div class="hero-badge"><i data-lucide="users"></i> Candidates</div>
          <h2 class="fw-bold mt-3 mb-1"><?= e((string)($elec['title'] ?? '—')) ?></h2>
          <p class="text-secondary mb-0">Register and manage election candidates.</p>
        </div>
        <a href="<?= APP_URL ?>/admin/elections.php?action=view&id=<?= $electionId ?>" class="btn btn-outline-secondary btn-sm"><i data-lucide="arrow-left"></i> Back</a>
      </div>

      <?php if ($ballotLocked): ?>
        <div class="alert alert-warning">Candidate approvals and records are locked after voting starts to protect ballot integrity.</div>
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
        <?php if (!$ballotLocked): ?>
        <div class="col-lg-5">
          <div class="glass-card p-4">
            <h5 class="fw-bold mb-3">Register Candidate</h5>
            <form method="POST" enctype="multipart/form-data">
              <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="action" value="add_candidate">
              <div class="mb-3">
                <label class="form-label">Student*</label>
                <select name="student_id" class="form-select" required>
                  <option value="">Select Student</option>
                  <?php foreach ($students as $student): ?>
                    <option value="<?= (int)$student['id'] ?>"><?= e((string)($student['full_name'] ?? '—')) ?> (<?= e((string)($student['matric_number'] ?? '—')) ?>)</option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="mb-3">
                <label class="form-label">Position / Office*</label>
                <input type="text" name="position_name" class="form-control" placeholder="e.g. President, Secretary" required>
                <?php if (!empty($positions)): ?>
                  <small class="text-secondary">Choose an existing office or enter a new one.</small>
                <?php endif; ?>
              </div>
              <div class="mb-3">
                <label class="form-label">Passport Photograph</label>
                <input type="file" name="candidate_image" class="form-control" accept="image/*">
                <small class="text-secondary">JPG, PNG (max 2MB recommended)</small>
              </div>
              <div class="mb-3">
                <label class="form-label">Manifesto</label>
                <textarea name="manifesto" class="form-control" rows="4" placeholder="Campaign manifesto or position statement..."></textarea>
              </div>
              <button type="submit" class="btn btn-success w-100">Register Candidate</button>
            </form>
          </div>
        </div>
        <?php endif; ?>

        <div class="col-lg-7">
          <div class="glass-card p-4">
            <h5 class="fw-bold mb-3">Candidates (<?= count($candidates) ?>)</h5>
            <?php if (empty($candidates)): ?>
              <p class="text-secondary">No candidates registered yet.</p>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table table-sm table-hover">
                  <thead>
                    <tr><th>Student</th><th>Position</th><th>Status</th><th>Actions</th></tr>
                  </thead>
                  <tbody>
                    <?php foreach ($candidates as $cand): ?>
                      <tr>
                        <td>
                          <div class="fw-bold"><?= e((string)($cand['full_name'] ?? '—')) ?></div>
                          <small class="text-secondary"><?= e((string)($cand['matric_number'] ?? '—')) ?></small>
                        </td>
                        <td><?= e((string)($cand['position_name'] ?? '—')) ?></td>
                        <td><span class="badge bg-<?= ((int)($cand['approved'] ?? 0) === 1) ? 'success' : 'warning' ?>"><?= ((int)($cand['approved'] ?? 0) === 1) ? 'Approved' : 'Pending' ?></span></td>
                        <td>
                          <div class="btn-group btn-group-sm">
                            <a href="<?= APP_URL ?>/admin/elections/candidates.php?election_id=<?= $electionId ?>&view=<?= (int)$cand['id'] ?>" class="btn btn-outline-primary">View</a>
                            <?php if (!$ballotLocked): ?><form method="POST" class="d-inline">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="action" value="toggle_approval">
                                <input type="hidden" name="candidate_id" value="<?= (int)$cand['id'] ?>">
                                <input type="hidden" name="approved" value="<?= ((int)$cand['approved'] === 1) ? '0' : '1' ?>">
                                <button type="submit" class="btn btn-outline-<?= ((int)$cand['approved'] === 1) ? 'warning' : 'success' ?>">
                                  <?= ((int)$cand['approved'] === 1) ? 'Unapprove' : 'Approve' ?>
                                </button>
                            </form>
                            <form method="POST" class="d-inline" onsubmit="return confirm('Remove this candidate?');">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="action" value="delete_candidate">
                                <input type="hidden" name="candidate_id" value="<?= (int)$cand['id'] ?>">
                                <button type="submit" class="btn btn-outline-danger">Remove</button>
                            </form><?php endif; ?>
                          </div>
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
  </div>
</div>
<?php require_once dirname(__DIR__, 2) . '/templates/footer.php'; ?>
