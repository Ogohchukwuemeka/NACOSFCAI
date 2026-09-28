<?php require_once dirname(__DIR__) . '/templates/header.php'; ?>
<?php
$auth = new Auth(Database::getInstance());
$auth->requireLogin('student');
$user = $auth->currentUser();
$portal = new Portal(Database::getInstance());
$errors = [];
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf();
        $profilePhoto = $user['profile_photo'] ?? '';
        if (!empty($_FILES['profile_photo']['tmp_name'])) {
            $result = uploadFile($_FILES['profile_photo'], APP_ROOT . '/assets/uploads/profile_pictures', ['jpg', 'jpeg', 'png', 'webp'], 5 * 1024 * 1024);
            if (!$result['success']) {
                throw new Exception($result['message']);
            }
            $profilePhoto = 'assets/uploads/profile_pictures/' . $result['filename'];
        }
        $portal->updateStudentProfile((int)($user['id'] ?? 0), [
            'email' => trim($_POST['email'] ?? $user['email']),
            'phone' => trim($_POST['phone'] ?? $user['phone']),
            'profile_photo' => $profilePhoto,
        ]);
        $success = 'Profile updated successfully.';
        $user = (new Auth(Database::getInstance()))->currentUser();
    } catch (Exception $e) {
        $errors[] = $e->getMessage();
    }
}
$memberId = $user['registration_number'] ?? $user['matric_number'] ?? 'N/A';
?>
<div class="student-page-shell container py-5">
    <div class="row g-4">
        <div class="col-lg-3">
            <?php require_once dirname(__DIR__) . '/templates/student-sidebar.php'; ?>
        </div>
        <div class="col-lg-9">
            <div class="card shadow-sm border-0 rounded-4 p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h4 class="fw-bold mb-1">Profile Settings</h4>
                        <p class="text-secondary mb-0">Keep your academic profile current.</p>
                    </div>
                    <a href="<?= APP_URL ?>/student/dashboard.php" class="btn btn-outline-success btn-sm"><i data-lucide="arrow-left"></i> Back to Dashboard</a>
                </div>
                <?php foreach ($errors as $error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endforeach; ?>
                <?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
                <div class="mb-4">
                    <p class="text-secondary mb-2"><strong>Membership ID:</strong> <?= e($memberId) ?></p>
                    <p class="text-secondary mb-0">This value is permanent and cannot be edited.</p>
                </div>
                <form method="post" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Full Name</label>
                            <input type="text" class="form-control" value="<?= e($user['full_name'] ?? '') ?>" disabled>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email Address</label>
                            <input type="email" name="email" class="form-control" value="<?= e($user['email'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone Number</label>
                            <input type="text" name="phone" class="form-control" value="<?= e($user['phone'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Profile Photo</label>
                            <input type="file" name="profile_photo" class="form-control" accept="image/*">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Current Photo</label>
                            <div class="d-flex align-items-center gap-3">
                                <?php if (!empty($user['profile_photo'])): ?>
                                    <img id="profilePhotoPreview" src="<?= e(APP_URL . '/' . ltrim($user['profile_photo'], '/')) ?>" alt="Profile photo" class="rounded-circle" width="80" height="80" style="object-fit: cover;">
                                <?php else: ?>
                                    <div id="profilePhotoPreview" class="rounded-circle bg-success-subtle text-success d-flex align-items-center justify-content-center fw-bold" style="width: 80px; height: 80px;">No photo</div>
                                <?php endif; ?>
                                <span class="text-secondary small">Upload a new photo to replace this image.</span>
                            </div>
                        </div>
                    </div>
                    <button class="btn btn-primary mt-4">Save Settings</button>
                </form>
            </div>
        </div>
    </div>
</div>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var input = document.querySelector('input[name="profile_photo"]');
        var preview = document.getElementById('profilePhotoPreview');

        if (input && preview) {
            input.addEventListener('change', function (event) {
                var file = event.target.files && event.target.files[0];
                if (!file) {
                    return;
                }

                if (!file.type.startsWith('image/')) {
                    return;
                }

                var reader = new FileReader();
                reader.onload = function (e) {
                    preview.src = e.target.result;
                };
                reader.readAsDataURL(file);
            });
        }
    });
</script>
<?php require_once dirname(__DIR__) . '/templates/footer.php'; ?>
