<?php require_once dirname(__DIR__) . '/templates/header.php'; ?>
<?php
$auth = new Auth(Database::getInstance());
$auth->requireLogin('student');
$portal = new Portal(Database::getInstance());
$election = new Election(Database::getInstance());
$pdo = Database::getInstance()->getConnection();

$studentId = (int)($_SESSION['account_user_id'] ?? 0);
if (!$studentId) {
  $studentId = (int)$pdo->query('SELECT user_id FROM students WHERE id = ' . (int)($_SESSION['user_id'] ?? 0) . ' LIMIT 1')->fetchColumn();
}
$electionId = (int)($_GET['election_id'] ?? 0);
$positionId = (int)($_GET['position_id'] ?? 0);
$errors = [];
$success = '';

// Get student details
$student = $pdo->query("SELECT s.*, u.full_name, u.email FROM students s JOIN users u ON u.id = s.user_id WHERE u.id = $studentId")->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $csrfValid = true;
    try {
        verify_csrf();
    } catch (Exception $e) {
        $errors[] = 'Invalid CSRF token.';
    $csrfValid = false;
    }

  $candidateId = $csrfValid ? (int)($_POST['candidate_id'] ?? 0) : 0;
  $posId = $csrfValid ? (int)($_POST['position_id'] ?? 0) : 0;
  $elecId = $csrfValid ? (int)($_POST['election_id'] ?? 0) : 0;

  if ($csrfValid && (!$candidateId || !$posId || !$elecId)) {
        $errors[] = 'Invalid vote submission.';
  } elseif ($csrfValid) {
    $election->refreshElectionStatus($elecId);
        $elecRecord = $election->getElection($elecId);
    if (!$elecRecord || $elecRecord['status'] !== 'active' || strtotime($elecRecord['start_time']) > time() || strtotime($elecRecord['end_time']) <= time()) {
            $errors[] = 'Election is not open for voting.';
        } else {
            $candidate = $election->getCandidate($candidateId);
            if (!$candidate || (int)$candidate['election_id'] !== $elecId || (int)$candidate['position_id'] !== $posId) {
                $errors[] = 'Invalid candidate selection.';
            } elseif ($election->hasStudentVoted($studentId, $posId, $elecId)) {
                $errors[] = 'You have already voted for this position.';
            } else {
                if ($election->recordVote($elecId, $posId, $candidateId, $studentId)) {
                    $success = 'Your vote has been recorded. Thank you!';
                } else {
                    $errors[] = 'Failed to record your vote. Please try again.';
                }
            }
        }
    }

    $electionId = $elecId;
}

// Get active elections
$activeElections = $election->getActiveElections();
?>
<div class="container py-5">
  <div class="row g-4 align-items-start">
    <div class="col-lg-3">
      <?php require_once dirname(__DIR__) . '/templates/student-sidebar.php'; ?>
    </div>
    <div class="col-lg-9">
      <div>
        <div class="hero-badge"><i data-lucide="vote"></i> E-Voting</div>
        <h2 class="fw-bold mt-3 mb-1">Cast Your Vote</h2>
        <p class="text-secondary mb-0">Participate in departmental elections. Select your preferred candidates.</p>
      </div>

      <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show mt-4" role="alert">
          <?= e($success) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>

      <?php if ($errors): ?>
        <div class="alert alert-danger alert-dismissible fade show mt-4" role="alert">
          <?php foreach ($errors as $error): ?>
            <div><?= e($error) ?></div>
          <?php endforeach; ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>

      <?php if (empty($activeElections) && !$electionId): ?>
        <div class="glass-card p-4 mt-4 text-center">
          <i data-lucide="inbox" style="font-size: 48px; color: #999; margin-bottom: 15px;"></i>
          <p class="text-secondary">No active elections at this time. Check back later.</p>
        </div>
      <?php else: ?>
        <?php if (!$electionId): ?>
          <!-- Election Selection -->
          <div class="row g-3 mt-4">
            <?php foreach ($activeElections as $elec): ?>
              <div class="col-lg-6">
                <a href="<?= APP_URL ?>/student/voting.php?election_id=<?= (int)$elec['id'] ?>" class="text-decoration-none">
                  <div class="glass-card p-4 h-100 card-hover">
                    <h5 class="fw-bold mb-2"><?= e((string)($elec['title'] ?? '—')) ?></h5>
                    <p class="text-secondary mb-3"><?= e(substr((string)($elec['description'] ?? ''), 0, 100)) ?>...</p>
                    <div class="d-flex justify-content-between align-items-center">
                      <small class="text-muted">Click to vote</small>
                      <span class="badge bg-success">Active</span>
                    </div>
                  </div>
                </a>
              </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <!-- Election Voting Interface -->
          <?php
          $elec = $election->getElection($electionId);
          $positions = $election->listPositions($electionId);
          $votingOpen = $elec && $election->isElectionOpen($electionId);
          if ($elec && !empty($positions)):
          ?>
            <div class="glass-card election-header p-4 mt-4 mb-4">
              <div class="d-flex justify-content-between align-items-center">
                <div>
                  <h4 class="fw-bold mb-1"><?= e((string)($elec['title'] ?? '—')) ?></h4>
                  <p class="text-secondary mb-0"><?= e((string)($elec['description'] ?? '')) ?></p>
                </div>
                <div class="text-end">
                  <span class="badge bg-<?= $votingOpen ? 'success' : 'secondary' ?> d-block mb-2"><?= $votingOpen ? 'Voting is open' : 'Voting is closed' ?></span>
                  <a href="<?= APP_URL ?>/student/voting.php" class="btn btn-outline-secondary btn-sm">Back</a>
                </div>
              </div>
            </div>

            <?php if (!$votingOpen): ?>
              <div class="alert alert-info">This election is not currently accepting votes. You can review the ballot, but submissions are closed.</div>
            <?php endif; ?>

            <!-- Voting by Position -->
            <?php foreach ($positions as $position): ?>
              <?php
              $candidates = $election->listCandidates($electionId, (int)$position['id'], true);
              $votedCandidateId = $election->getStudentVoteCandidateId($studentId, (int)$position['id'], $electionId);
              $hasVoted = $votedCandidateId !== null;
              ?>
              <div class="glass-card election-position p-4 mb-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                  <h5 class="fw-bold"><?= e((string)($position['position_name'] ?? '—')) ?></h5>
                  <?php if ($hasVoted): ?>
                    <span class="badge bg-success"><i data-lucide="check"></i> Voted</span>
                  <?php endif; ?>
                </div>
                <p class="text-secondary mb-4"><?= e((string)($position['description'] ?? '')) ?></p>

                <?php if (empty($candidates)): ?>
                  <div class="alert alert-warning">No candidates for this position.</div>
                <?php else: ?>
                  <div class="row g-3">
                    <?php foreach ($candidates as $candidate): ?>
                      <div class="col-md-6 col-lg-4">
                        <div class="glass-card candidate-card p-3 h-100 position-relative <?= (int)$candidate['id'] === $votedCandidateId ? 'candidate-selected' : '' ?>">
                          <?php if ($hasVoted): ?>
                            <div class="position-absolute top-0 end-0 p-2">
                              <span class="badge bg-success-subtle text-success"><i data-lucide="check"></i></span>
                            </div>
                          <?php endif; ?>
                          
                          <?php if ($candidate['image_path']): ?>
                            <img src="<?= APP_URL ?>/<?= e((string)$candidate['image_path']) ?>" alt="<?= e((string)($candidate['full_name'] ?? '—')) ?>" class="img-fluid rounded-3 mb-3" style="height: 180px; object-fit: cover; width: 100%;">
                          <?php else: ?>
                            <div class="bg-light rounded-3 mb-3 d-flex align-items-center justify-content-center" style="height: 180px;">
                              <i data-lucide="user" style="font-size: 48px; color: #ccc;"></i>
                            </div>
                          <?php endif; ?>
                          
                          <h6 class="fw-bold mb-1"><?= e((string)($candidate['full_name'] ?? '—')) ?></h6>
                          <small class="text-secondary d-block mb-2">
                            <?= e((string)($candidate['level'] ?? '—')) ?> • <?= e((string)($candidate['department'] ?? '—')) ?>
                          </small>
                          
                          <?php if ($candidate['manifesto']): ?>
                            <div class="mb-3 p-2 bg-light rounded-2" style="max-height: 100px; overflow-y: auto;">
                              <small class="text-secondary"><?= e(substr((string)$candidate['manifesto'], 0, 150)) ?>...</small>
                            </div>
                          <?php endif; ?>

                          <?php if (!$hasVoted && $votingOpen): ?>
                            <form method="POST" class="d-grid">
                              <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                              <input type="hidden" name="candidate_id" value="<?= (int)$candidate['id'] ?>">
                              <input type="hidden" name="position_id" value="<?= (int)$position['id'] ?>">
                              <input type="hidden" name="election_id" value="<?= $electionId ?>">
                              <button type="submit" class="btn btn-success btn-sm"><i data-lucide="vote"></i> Vote</button>
                            </form>
                          <?php elseif ($hasVoted): ?>
                            <div class="alert <?= (int)$candidate['id'] === $votedCandidateId ? 'alert-success' : 'alert-light' ?> alert-sm mb-0">
                              <small><?= (int)$candidate['id'] === $votedCandidateId ? 'Your vote' : 'Not selected' ?></small>
                            </div>
                          <?php else: ?>
                            <div class="alert alert-light alert-sm mb-0"><small>Voting is closed</small></div>
                          <?php endif; ?>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          <?php else: ?>
            <div class="alert alert-danger mt-4">Election or positions not found.</div>
          <?php endif; ?>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php require_once dirname(__DIR__) . '/templates/footer.php'; ?>
