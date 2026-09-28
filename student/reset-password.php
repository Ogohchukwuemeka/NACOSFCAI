<?php require_once dirname(__DIR__) . '/templates/header.php'; ?>
<?php
$errors = [];
$success = '';
$token = $_GET['token'] ?? '';
$auth = new Auth(Database::getInstance());
$isTokenValid = $auth->validatePasswordResetToken($token);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf();
        $ok = $auth->resetPassword($_POST['token'] ?? '', $_POST['password'] ?? '');
        if ($ok) {
            $success = 'Password reset successfully. You may now log in with your new password.';
        } else {
            $errors[] = 'The reset link is invalid or expired. Please request a new password reset link.';
            $isTokenValid = false;
        }
    } catch (Exception $e) {
        $errors[] = $e->getMessage();
        $isTokenValid = false;
    }
}
?>
<div class="container py-5">
  <div class="row justify-content-center">
    <div class="col-lg-5">
      <div class="card shadow-sm border-0 rounded-4 p-4">
        <h4 class="fw-bold">Reset Password</h4>
        <?php if (!$isTokenValid && !$success): ?>
          <div class="alert alert-danger">The reset link is invalid or expired. Please request a new password reset link.</div>
          <div class="text-center mt-3">
            <a href="<?= APP_URL ?>/student/forgot-password.php" class="btn btn-outline-secondary">Request a new reset link</a>
          </div>
        <?php else: ?>
          <form method="post">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="token" value="<?= e($token) ?>">
            <?php foreach ($errors as $error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endforeach; ?>
            <?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
            <?php if (!$success): ?>
              <div class="mb-3">
                <label class="form-label">New Password</label>
                <input type="password" name="password" class="form-control" required>
              </div>
              <button class="btn btn-primary w-100">Update Password</button>
            <?php endif; ?>
            <?php if ($success): ?>
              <div class="text-center mt-3">
                <a href="<?= APP_URL ?>/auth/login.php" class="small">Return to login</a>
              </div>
            <?php endif; ?>
          </form>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php require_once dirname(__DIR__) . '/templates/footer.php'; ?>
