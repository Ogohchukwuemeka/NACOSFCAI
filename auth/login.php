<?php require_once dirname(__DIR__) . '/templates/header.php'; ?>
<?php
$errors = [];
$loginError = $_SESSION['login_error'] ?? null;
unset($_SESSION['login_error']);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf();
        $auth = new Auth(Database::getInstance());
        $ok = $auth->login($_POST['identifier'] ?? '', $_POST['password'] ?? '', 'student');
        if ($ok) {
            redirect('student/dashboard.php');
        }
        $errors[] = $_SESSION['login_error'] ?? $loginError ?: 'Invalid student credentials.';
        unset($_SESSION['login_error']);
    } catch (Exception $e) {
        $errors[] = $e->getMessage();
    }
}
?>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-5">
            <div class="card shadow-lg border-0 rounded-4">
                <div class="card-body p-4 p-lg-5">
                    <div class="text-center mb-4">
                        <img src="<?= asset('images/logo/nacos-logo.jpeg') ?>" alt="nacos-logo" class="brand-logo mb-3">
                        <h3 class="fw-bold">Welcome Back</h3>
                        <p class="text-secondary">Sign in to your NACOSFCAIshiagu dashboard.</p>
                    </div>
                    <?php foreach ($errors as $error): ?>
                        <div class="alert alert-danger"><?= e($error) ?></div>
                    <?php endforeach; ?>
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <div class="mb-3">
                            <label class="form-label">Email or Matric Number</label>
                            <input type="text" name="identifier" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Login</button>
                        <div class="text-center mt-3">
                            <a href="<?= APP_URL ?>/student/forgot-password.php" class="small">Forgot your password?</a>
                        </div>
                        <div class="text-center mt-3">
                            <span class="text-secondary small">Don&rsquo;t have an account? <a href="<?= APP_URL ?>/auth/register.php" class="fw-semibold">Register</a></span>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require_once dirname(__DIR__) . '/templates/footer.php'; ?>
