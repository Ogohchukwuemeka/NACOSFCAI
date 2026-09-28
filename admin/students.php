<?php require_once dirname(__DIR__) . '/templates/header.php'; ?>
<?php
$auth = new Auth(Database::getInstance());
$auth->requireLogin('admin');
$pdo = Database::getInstance()->getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf();
        $studentId = (int)($_POST['student_id'] ?? 0);
        $action = $_POST['action'] ?? '';
        if ($studentId > 0) {
          if ($action === 'approve') {
            $pdo->prepare('UPDATE users SET status = :status WHERE id = (SELECT user_id FROM students WHERE id = :student_id)')->execute(['status' => 'active', 'student_id' => $studentId]);
          } elseif ($action === 'deactivate') {
                $pdo->prepare('UPDATE users SET status = :status WHERE id = (SELECT user_id FROM students WHERE id = :student_id)')->execute(['status' => 'inactive', 'student_id' => $studentId]);
            } elseif ($action === 'activate') {
                $pdo->prepare('UPDATE users SET status = :status WHERE id = (SELECT user_id FROM students WHERE id = :student_id)')->execute(['status' => 'active', 'student_id' => $studentId]);
            } elseif ($action === 'delete') {
                $pdo->prepare('DELETE FROM students WHERE id = :student_id')->execute(['student_id' => $studentId]);
            }
        }
        $_SESSION['flash'] = 'Student action completed.';
    } catch (Exception $e) {
        $_SESSION['flash'] = $e->getMessage();
    }
    redirect('admin/students.php');
}

$levelFilter = trim($_GET['level'] ?? '');
$courseFilter = trim($_GET['course'] ?? '');
$where = [];
$params = [];
if ($levelFilter !== '') {
    $where[] = 'u.level = :level';
    $params[':level'] = $levelFilter;
}
if ($courseFilter !== '') {
  $where[] = 's.course = :course';
  $params[':course'] = $courseFilter;
}
$sql = 'SELECT s.*, s.course AS course_of_study, u.full_name, u.email, u.department, u.level, u.status FROM students s JOIN users u ON u.id = s.user_id';
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY s.created_at DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$students = $stmt->fetchAll();

$levelOptions = $pdo->query('SELECT DISTINCT u.level FROM users u JOIN students s ON s.user_id = u.id WHERE u.level IS NOT NULL AND u.level <> "" ORDER BY u.level')->fetchAll(PDO::FETCH_COLUMN);
$courseOptions = $pdo->query('SELECT DISTINCT s.course FROM students s WHERE s.course IS NOT NULL AND s.course <> "" ORDER BY s.course')->fetchAll(PDO::FETCH_COLUMN);
?>
<div class="container py-5">
  <div class="row g-4 align-items-start">
    <div class="col-lg-3">
      <?php require_once dirname(__DIR__) . '/templates/admin-sidebar.php'; ?>
    </div>
    <div class="col-lg-9">
      <div class="glass-card p-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <div>
            <h4 class="fw-bold mb-1">Student Records</h4>
            <p class="text-secondary mb-0">View students by level, course of study, or see all records.</p>
          </div>
          <span class="badge-soft"><?= count($students) ?> records</span>
        </div>

        <form method="get" class="row g-2 mb-3">
          <div class="col-md-4">
            <select name="level" class="form-select">
              <option value="">All Levels</option>
              <?php foreach ($levelOptions as $option): ?>
                <option value="<?= e($option) ?>" <?= $levelFilter === $option ? 'selected' : '' ?>><?= e($option) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4">
            <select name="course" class="form-select">
              <option value="">All Courses</option>
              <?php foreach ($courseOptions as $option): ?>
                <option value="<?= e($option) ?>" <?= $courseFilter === $option ? 'selected' : '' ?>><?= e($option) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4 d-flex gap-2">
            <button class="btn btn-success">Filter</button>
            <a href="<?= APP_URL ?>/admin/students.php" class="btn btn-outline-secondary">Reset</a>
          </div>
        </form>

        <div class="table-responsive mt-3">
          <table class="table table-hover align-middle">
            <thead>
              <tr><th>Name</th><th>Email</th><th>Course of Study</th><th>Level</th><th>Status</th><th>Actions</th></tr>
            </thead>
            <tbody>
              <?php foreach ($students as $student): ?>
                <tr>
                  <td><?= e($student['full_name']) ?></td>
                  <td><?= e($student['email']) ?></td>
                  <td><?= e($student['course_of_study'] ?? $student['course'] ?? 'Not provided') ?></td>
                  <td><?= e($student['level'] ?? '') ?></td>
                  <td><span class="badge-soft"><?= e($student['status']) ?></span></td>
                  <td>
                    <div class="d-flex gap-2">
                      <?php if (($student['status'] ?? '') === 'pending'): ?>
                        <form method="post" class="d-inline">
                          <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                          <input type="hidden" name="student_id" value="<?= (int)$student['id'] ?>">
                          <input type="hidden" name="action" value="approve">
                          <button class="btn btn-success btn-sm">Approve</button>
                        </form>
                      <?php endif; ?>
                      <form method="post" class="d-inline">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="student_id" value="<?= (int)$student['id'] ?>">
                        <input type="hidden" name="action" value="deactivate">
                        <button class="btn btn-outline-warning btn-sm">Deactivate</button>
                      </form>
                      <form method="post" class="d-inline">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="student_id" value="<?= (int)$student['id'] ?>">
                        <input type="hidden" name="action" value="activate">
                        <button class="btn btn-outline-success btn-sm">Activate</button>
                      </form>
                      <form method="post" class="d-inline">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="student_id" value="<?= (int)$student['id'] ?>">
                        <input type="hidden" name="action" value="delete">
                        <button class="btn btn-outline-danger btn-sm">Delete</button>
                      </form>
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
<?php require_once dirname(__DIR__) . '/templates/footer.php'; ?>
