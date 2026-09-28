<?php require_once dirname(__DIR__) . '/templates/header.php'; ?>
<?php
$errors = [];
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf();
        $auth = new Auth(Database::getInstance());
        $auth->requestPasswordReset($_POST['email'] ?? '');
        $success = 'If that email is registered, a password reset link has been sent. Please check your inbox and spam folder.';
    } catch (Exception $e) {
        $errors[] = $e->getMessage();
    }
}
?>
<div class="container py-5">
  <div class="row justify-content-center">
    <div class="col-lg-5">
      <div class="card shadow-sm border-0 rounded-4 p-4">
        <h4 class="fw-bold">Forgot Password</h4>
        <form method="post">
          <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
          <?php foreach ($errors as $error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endforeach; ?>
          <?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
          <div class="mb-3">
            <label class="form-label">Email Address</label>
            <input type="email" name="email" class="form-control" required>
          </div>
          <button class="btn btn-primary w-100">Send Reset Link</button>
          <div class="text-center mt-3">
            <a href="<?= APP_URL ?>/auth/login.php" class="small">Back to login</a>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
<?php require_once dirname(__DIR__) . '/templates/footer.php'; ?>
