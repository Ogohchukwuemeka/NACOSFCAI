<?php require_once dirname(__DIR__, 2) . '/templates/header.php'; ?>
<?php
$auth = new Auth(Database::getInstance());
$auth->requireLogin('admin');
$election = new Election(Database::getInstance());

$electionId = (int)($_GET['election_id'] ?? 0);

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

  if (!$election->isResultsPublished($electionId)) {
    http_response_code(403);
    echo '<div class="container py-5"><div class="alert alert-warning">Final results are available only after the election is closed.</div></div>';
    require_once dirname(__DIR__, 2) . '/templates/footer.php';
    exit;
  }

$results = $election->getElectionResults($electionId);
$stats = $election->getElectionStats($electionId);
$positions = $election->listPositions($electionId);
?>
<div class="container py-5">
  <div class="row g-4 align-items-start">
    <div class="col-lg-3">
      <?php require_once dirname(__DIR__, 2) . '/templates/admin-sidebar.php'; ?>
    </div>
    <div class="col-lg-9">
      <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
          <div class="hero-badge"><i data-lucide="bar-chart-3"></i> Results</div>
          <h2 class="fw-bold mt-3 mb-1"><?= e((string)($elec['title'] ?? '—')) ?></h2>
          <p class="text-secondary mb-0">Final election results and statistics.</p>
        </div>
        <div class="d-flex gap-2">
          <button class="btn btn-outline-secondary btn-sm" onclick="window.print();"><i data-lucide="printer"></i> Print</button>
          <a href="<?= APP_URL ?>/admin/elections.php?action=view&id=<?= $electionId ?>" class="btn btn-outline-secondary btn-sm"><i data-lucide="arrow-left"></i> Back</a>
        </div>
      </div>

      <!-- Election Status -->
      <div class="row g-3 mb-4">
        <div class="col-md-4">
          <div class="glass-card p-3">
            <small class="text-secondary">Eligible Voters</small>
            <h4 class="fw-bold mt-2"><?= number_format((int)$stats['total_registered_voters']) ?></h4>
          </div>
        </div>
        <div class="col-md-4">
          <div class="glass-card p-3">
            <small class="text-secondary">Ballots Cast</small>
            <h4 class="fw-bold mt-2"><?= number_format((int)$stats['total_votes_cast']) ?></h4>
          </div>
        </div>
        <div class="col-md-4">
          <div class="glass-card p-3">
            <small class="text-secondary">Voter Turnout</small>
            <h4 class="fw-bold mt-2"><?= number_format((float)$stats['voter_turnout_percentage'], 1) ?>%</h4>
          </div>
        </div>
      </div>

      <div class="small text-secondary mb-3">Unique voters: <?= number_format((int)$stats['unique_voters']) ?></div>

      <!-- Results by Position -->
      <?php foreach ($results as $positionId => $positionResult): ?>
        <div class="glass-card p-4 mb-4">
          <h5 class="fw-bold mb-3"><?= e((string)($positionResult['position'] ?? '—')) ?></h5>
          <div class="table-responsive mb-3">
            <table class="table table-hover align-middle">
              <thead>
                <tr><th>Candidate</th><th>Votes</th><th>Percentage</th><th>Bar</th></tr>
              </thead>
              <tbody>
                <?php foreach ($positionResult['candidates'] as $candidate): ?>
                  <tr>
                    <td>
                      <strong><?= e((string)($candidate['full_name'] ?? '—')) ?></strong>
                    </td>
                    <td><?= number_format((int)$candidate['votes']) ?></td>
                    <td><?= number_format((float)$candidate['percentage'], 1) ?>%</td>
                    <td>
                      <div class="progress" style="height: 20px;">
                        <div class="progress-bar bg-success" role="progressbar" style="width: <?= number_format((float)$candidate['percentage'], 1) ?>%" aria-label="<?= (float)$candidate['percentage'] ?>%"></div>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <small class="text-secondary">Total Votes: <?= number_format((int)$positionResult['total_votes']) ?></small>
        </div>
      <?php endforeach; ?>

      <!-- Export Options -->
      <div class="glass-card p-4 text-center">
        <h5 class="fw-bold mb-3">Export Results</h5>
        <div class="d-flex gap-2 justify-content-center flex-wrap">
          <a href="<?= APP_URL ?>/admin/elections/results-export.php?election_id=<?= $electionId ?>&format=pdf" class="btn btn-outline-danger btn-sm">
            <i data-lucide="file-pdf"></i> Export as PDF
          </a>
          <a href="<?= APP_URL ?>/admin/elections/results-export.php?election_id=<?= $electionId ?>&format=excel" class="btn btn-outline-success btn-sm">
            <i data-lucide="file-spreadsheet"></i> Export as Excel
          </a>
        </div>
      </div>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<?php require_once dirname(__DIR__, 2) . '/templates/footer.php'; ?>
