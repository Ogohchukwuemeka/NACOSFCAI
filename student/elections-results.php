<?php require_once dirname(__DIR__) . '/templates/header.php'; ?>
<?php
$auth = new Auth(Database::getInstance());
$auth->requireLogin('student');
$election = new Election(Database::getInstance());
$pdo = Database::getInstance()->getConnection();

$studentId = (int)($_SESSION['user_id'] ?? 0);
$electionId = (int)($_GET['election_id'] ?? 0);
$viewType = $_GET['view'] ?? 'all'; // 'all' or 'detailed'

// Get student details
$student = $pdo->query("SELECT s.*, u.full_name FROM students s JOIN users u ON u.id = s.user_id WHERE u.id = $studentId")->fetch();

// Refresh scheduled/expired states before listing published results.
$election->refreshAllElectionStatuses();
$allElections = $pdo->query("SELECT * FROM elections WHERE status = 'closed' ORDER BY start_time DESC")->fetchAll();
?>
<div class="container py-5">
  <div class="row g-4 align-items-start">
    <div class="col-lg-3">
      <?php require_once dirname(__DIR__) . '/templates/student-sidebar.php'; ?>
    </div>
    <div class="col-lg-9">
      <div>
        <div class="hero-badge"><i data-lucide="bar-chart-3"></i> Election Results</div>
        <h2 class="fw-bold mt-3 mb-1">View Election Results</h2>
        <p class="text-secondary mb-0">Final results from completed elections.</p>
      </div>

      <?php if (!$electionId): ?>
        <!-- All Elections Results Overview -->
        <div class="row g-3 mt-4">
          <?php if (empty($allElections)): ?>
            <div class="col-12">
              <div class="glass-card p-4 text-center">
                <i data-lucide="inbox" style="font-size: 48px; color: #999; margin-bottom: 15px;"></i>
                <p class="text-secondary">No elections found.</p>
              </div>
            </div>
          <?php else: ?>
            <?php foreach ($allElections as $elec): ?>
              <?php
              $results = $election->getElectionResults((int)$elec['id'], true);
              $stats = $election->getElectionStats($elec['id']);
              $status = $elec['status'];
              
              // Determine status badge color
              $statusColor = match($status) {
                'draft' => 'secondary',
                'scheduled' => 'info',
                'active' => 'success',
                'closed' => 'danger',
                default => 'secondary'
              };
              
              $statusText = 'Completed';
              ?>
              <div class="col-lg-6">
                <div class="glass-card p-4 h-100">
                  <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                      <h5 class="fw-bold mb-2"><?= e((string)($elec['title'] ?? '—')) ?></h5>
                      <small class="text-secondary"><?= e(substr((string)($elec['description'] ?? ''), 0, 80)) ?>...</small>
                    </div>
                    <span class="badge bg-<?= $statusColor ?>"><?= $statusText ?></span>
                  </div>

                  <!-- Quick Stats -->
                  <div class="row g-2 mb-3">
                    <div class="col-6">
                      <small class="text-secondary">Eligible voters</small>
                      <div class="fw-bold"><?= (int)($stats['total_registered_voters'] ?? 0) ?></div>
                    </div>
                    <div class="col-6">
                      <small class="text-secondary">Ballots cast</small>
                      <div class="fw-bold"><?= (int)($stats['total_votes_cast'] ?? 0) ?></div>
                    </div>
                  </div>

                  <div class="mb-3">
                    <small class="text-secondary">Turnout</small>
                    <div class="progress" style="height: 6px;">
                      <div class="progress-bar bg-success" style="width: <?= round((float)($stats['voter_turnout_percentage'] ?? 0)) ?>%;"></div>
                    </div>
                    <small class="text-muted"><?= round((float)($stats['voter_turnout_percentage'] ?? 0), 1) ?>%</small>
                  </div>

                  <a href="<?= APP_URL ?>/student/elections-results.php?election_id=<?= (int)$elec['id'] ?>" class="btn btn-sm btn-outline-primary w-100">
                    View Results <i data-lucide="arrow-right" style="width: 14px; height: 14px; margin-left: 5px;"></i>
                  </a>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

      <?php else: ?>
        <!-- Detailed Election Results -->
        <?php
        $elec = $election->getElection($electionId);
        $results = $election->getElectionResults($electionId, true);
        $stats = $election->getElectionStats($electionId);
        
        if (!$elec) {
            echo '<div class="alert alert-danger mt-4">Election not found.</div>';
        } elseif (!$election->isResultsPublished($electionId)) {
          echo '<div class="alert alert-warning mt-4">Final results are available after voting is closed.</div>';
        } else {
            $status = $elec['status'];
            $statusColor = match($status) {
                'draft' => 'secondary',
                'scheduled' => 'info',
                'active' => 'success',
                'closed' => 'danger',
                default => 'secondary'
            };
            
            $statusText = match($status) {
                'draft' => 'Draft',
                'scheduled' => 'Upcoming',
                'active' => 'Live Voting',
                'closed' => 'Completed',
                default => 'Unknown'
            };
        ?>
          <!-- Header -->
          <div class="glass-card p-4 mt-4 mb-4">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <h4 class="fw-bold"><?= e((string)($elec['title'] ?? '—')) ?></h4>
              <div class="d-flex gap-2">
                <a href="<?= APP_URL ?>/student/elections-results.php?election_id=<?= $electionId ?>" class="btn btn-outline-success btn-sm"><i data-lucide="refresh-cw"></i> Refresh</a>
                <button class="btn btn-outline-secondary btn-sm" onclick="window.print();">Print</button>
                <a href="<?= APP_URL ?>/student/elections-results.php" class="btn btn-outline-secondary btn-sm">Back</a>
              </div>
            </div>
            <p class="text-secondary mb-0"><?= e((string)($elec['description'] ?? '')) ?></p>
          </div>

          <!-- Statistics Cards -->
          <div class="row g-3 mb-4">
            <div class="col-md-6 col-lg-3">
              <div class="glass-card p-3 text-center">
                <small class="text-secondary d-block mb-2">Eligible Voters</small>
                <div class="fw-bold" style="font-size: 24px;"><?= (int)($stats['total_registered_voters'] ?? 0) ?></div>
              </div>
            </div>
            <div class="col-md-6 col-lg-3">
              <div class="glass-card p-3 text-center">
                <small class="text-secondary d-block mb-2">Ballots Cast</small>
                <div class="fw-bold" style="font-size: 24px;"><?= (int)($stats['total_votes_cast'] ?? 0) ?></div>
              </div>
            </div>
            <div class="col-md-6 col-lg-3">
              <div class="glass-card p-3 text-center">
                <small class="text-secondary d-block mb-2">Unique Voters</small>
                <div class="fw-bold" style="font-size: 24px;"><?= (int)($stats['unique_voters'] ?? 0) ?></div>
              </div>
            </div>
            <div class="col-md-6 col-lg-3">
              <div class="glass-card p-3 text-center">
                <small class="text-secondary d-block mb-2">Turnout Rate</small>
                <div class="fw-bold" style="font-size: 24px;"><?= round((float)($stats['voter_turnout_percentage'] ?? 0), 1) ?>%</div>
              </div>
            </div>
            <div class="col-md-6 col-lg-3">
              <div class="glass-card p-3 text-center">
                <small class="text-secondary d-block mb-2">Positions</small>
                <div class="fw-bold" style="font-size: 24px;"><?= count($results) ?></div>
              </div>
            </div>
          </div>

          <!-- Results by Position -->
          <?php if (empty($results)): ?>
            <div class="glass-card p-4 text-center">
              <i data-lucide="inbox" style="font-size: 48px; color: #999; margin-bottom: 15px;"></i>
              <p class="text-secondary">No results available yet.</p>
            </div>
          <?php else: ?>
            <?php foreach ($results as $positionIndex => $position): ?>
              <div class="glass-card p-4 mb-4">
                <h5 class="fw-bold mb-3"><?= e((string)($position['position_name'] ?? '—')) ?></h5>
                
                <?php if (empty($position['candidates'])): ?>
                  <div class="alert alert-warning">No candidates for this position.</div>
                <?php else: ?>
                  <!-- Chart Container -->
                  <div class="row mb-4">
                    <div class="col-lg-5">
                      <canvas id="chart-<?= $positionIndex ?>" style="max-height: 300px;"></canvas>
                    </div>
                    <div class="col-lg-7">
                      <!-- Candidates Table -->
                      <div class="table-responsive">
                        <table class="table table-sm table-hover" style="font-size: 13px;">
                          <thead>
                            <tr>
                              <th>Candidate</th>
                              <th class="text-end">Votes</th>
                              <th class="text-end">%</th>
                            </tr>
                          </thead>
                          <tbody>
                            <?php foreach ($position['candidates'] as $candidate): ?>
                              <tr>
                                <td>
                                  <strong><?= e((string)($candidate['full_name'] ?? '—')) ?></strong>
                                  <br>
                                  <small class="text-secondary"><?= e((string)($candidate['department'] ?? '—')) ?></small>
                                </td>
                                <td class="text-end">
                                  <strong><?= (int)($candidate['vote_count'] ?? 0) ?></strong>
                                </td>
                                <td class="text-end">
                                  <span class="badge bg-primary"><?= round((float)($candidate['vote_percentage'] ?? 0), 1) ?>%</span>
                                </td>
                              </tr>
                            <?php endforeach; ?>
                          </tbody>
                        </table>
                      </div>
                    </div>
                  </div>

                  <!-- Progress Bars for Candidates -->
                  <div class="mt-4">
                    <h6 class="fw-bold mb-3">Vote Distribution</h6>
                    <?php foreach ($position['candidates'] as $candidate): ?>
                      <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                          <small class="fw-bold"><?= e((string)($candidate['full_name'] ?? '—')) ?></small>
                          <small class="text-muted"><?= (int)($candidate['vote_count'] ?? 0) ?> votes (<?= round((float)($candidate['vote_percentage'] ?? 0), 1) ?>%)</small>
                        </div>
                        <div class="progress" style="height: 8px;">
                          <div class="progress-bar" style="width: <?= round((float)($candidate['vote_percentage'] ?? 0)) ?>%; background-color: #14532d;"></div>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  </div>

                  <!-- Chart.js Script for this Position -->
                  <script>
                    document.addEventListener('DOMContentLoaded', function() {
                      const ctx = document.getElementById('chart-<?= $positionIndex ?>');
                      if (ctx && typeof Chart !== 'undefined') {
                        const candidates = <?= json_encode(array_map(function($c) { 
                          return $c['full_name'] ?? '—'; 
                        }, $position['candidates'])) ?>;
                        const votes = <?= json_encode(array_map(function($c) { 
                          return (int)($c['vote_count'] ?? 0); 
                        }, $position['candidates'])) ?>;
                        const colors = [
                          '#14532d', '#16a34a', '#22c55e', '#86efac',
                          '#1e40af', '#2563eb', '#60a5fa', '#93c5fd',
                          '#7c2d12', '#ea580c', '#fb923c', '#fbbf24'
                        ];

                        new Chart(ctx, {
                          type: 'doughnut',
                          data: {
                            labels: candidates,
                            datasets: [{
                              data: votes,
                              backgroundColor: colors.slice(0, candidates.length),
                              borderColor: '#fff',
                              borderWidth: 2
                            }]
                          },
                          options: {
                            responsive: true,
                            maintainAspectRatio: true,
                            plugins: {
                              legend: {
                                position: 'bottom',
                                labels: {
                                  font: { size: 12 },
                                  padding: 15,
                                  usePointStyle: true
                                }
                              },
                              tooltip: {
                                callbacks: {
                                  label: function(context) {
                                    const label = context.label || '';
                                    const value = context.parsed;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                                    return label + ': ' + value + ' votes (' + percentage + '%)';
                                  }
                                }
                              }
                            }
                          }
                        });
                      }
                    });
                  </script>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>

          <!-- Auto-refresh Note -->
          <div class="alert alert-info alert-sm mt-4">
            <i data-lucide="info"></i> 
            <small>Results are shown as recorded. Refresh this page to see the latest vote counts.</small>
          </div>
        <?php } ?>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php require_once dirname(__DIR__) . '/templates/footer.php'; ?>
