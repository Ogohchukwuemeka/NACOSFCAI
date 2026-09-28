<?php require_once dirname(__DIR__) . '/templates/header.php'; ?>
<?php
$errors = [];
$success = '';
$emailStatus = null;
$portal = new Portal(Database::getInstance());
$academicSessions = $portal->getAcademicSessions();
$sessionLookup = [];
foreach ($academicSessions as $session) {
    $label = $session['session_name'] ?? '';
    if ($label !== '') {
        $sessionLookup[$label] = (string)($session['id'] ?? '');
    }
}
$currentYear = (int)date('Y');
$sessionOptions = [];
for ($offset = -10; $offset <= 20; $offset++) {
    $year = $currentYear + $offset;
    if ($year >= 2020 && $year <= 2040) {
        $label = $year . '/' . ($year + 1);
        $sessionOptions[] = [
            'label' => $label,
            'value' => $sessionLookup[$label] ?? '',
        ];
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf();
        if (empty($_FILES['profile_photo']['tmp_name'])) {
            throw new Exception('Please upload a profile photo to complete registration.');
        }
        $profileUpload = uploadFile($_FILES['profile_photo'], APP_ROOT . '/assets/uploads/profile_pictures', ['jpg', 'jpeg', 'png', 'webp'], 5 * 1024 * 1024);
        if (!$profileUpload['success']) {
            throw new Exception($profileUpload['message']);
        }
        $auth = new Auth(Database::getInstance());
        $id = $auth->registerStudent([
            'matric_number' => $_POST['matric_number'] ?? '',
            'full_name' => $_POST['full_name'] ?? '',
            'email' => $_POST['email'] ?? '',
            'phone' => $_POST['phone'] ?? '',
            'department' => $_POST['department'] ?? '',
            'course_of_study' => $_POST['course_of_study'] ?? '',
            'level' => $_POST['level'] ?? '',
            'semester' => $_POST['semester'] ?? '',
            'academic_session_id' => $_POST['academic_session_id'] ?? null,
            'address' => $_POST['address'] ?? null,
            'emergency_contact' => $_POST['emergency_contact'] ?? null,
            'blood_group' => $_POST['blood_group'] ?? null,
            'gender' => $_POST['gender'] ?? null,
            'password' => $_POST['password'] ?? '',
            'profile_photo' => 'assets/uploads/profile_pictures/' . $profileUpload['filename'],
        ]);
        $emailStatus = $_SESSION['registration_email_status'] ?? null;
        error_log('registration_email_status=' . ($emailStatus ?? 'null'));
        unset($_SESSION['registration_email_status']);
        if ($emailStatus === 'failed') {
            $success = 'Registration completed successfully, but we were unable to send your confirmation email at this time.';
        } elseif ($emailStatus === 'queued') {
            $success = 'Registration successful. Confirmation email has been queued and will be sent shortly.';
        } else {
            $success = 'Registration successful. Please login.';
        }
    } catch (Exception $e) {
        $errors[] = $e->getMessage();
    }
}
?>
<div class="register-page-shell">
    <div class="container py-5">
        <div class="register-card" data-aos="fade-up">
            <div class="text-center mb-4">
                <img src="<?= asset('images/logo/nacos-logo.jpeg') ?>" alt="NACOS logo" class="brand-logo mb-3">
                <h3 class="fw-bold mb-2">Create Student Account</h3>
                <p class="text-secondary mb-0">Fill in your details to create your student account.</p>
            </div>

            <?php foreach ($errors as $error): ?>
                <div class="notification-card notification-error" role="alert">
                    <div class="notification-icon"><i data-lucide="alert-circle"></i></div>
                    <div><?= e($error) ?></div>
                </div>
            <?php endforeach; ?>

            <?php if ($success): ?>
                <div class="register-success-state" role="status">
                    <div class="success-icon"><i data-lucide="check-circle-2"></i></div>
                    <h4 class="fw-bold mb-2">Registration successful</h4>
                    <p class="mb-4">Your account has been created and sent for admin approval. You can log in after an administrator activates your account.</p>
                    <a href="<?= APP_URL ?>/auth/login.php" class="btn register-submit-btn">
                        <span class="btn-label"><i data-lucide="log-in"></i> Continue to Login</span>
                    </a>
                </div>
            <?php else: ?>
                <form method="post" id="studentRegistrationForm" enctype="multipart/form-data" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <label class="form-label" for="full_name">Full Name</label>
                            <div class="input-group glass-field">
                                <span class="input-icon" aria-hidden="true"><i data-lucide="user"></i></span>
                                <input type="text" id="full_name" name="full_name" class="form-control" required value="<?= e($_POST['full_name'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="matric_number">Matric Number</label>
                            <div class="input-group glass-field">
                                <span class="input-icon" aria-hidden="true"><i data-lucide="badge-check"></i></span>
                                <input type="text" id="matric_number" name="matric_number" class="form-control" required value="<?= e($_POST['matric_number'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="email">Email Address</label>
                            <div class="input-group glass-field">
                                <span class="input-icon" aria-hidden="true"><i data-lucide="mail"></i></span>
                                <input type="email" id="email" name="email" class="form-control" required value="<?= e($_POST['email'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="phone">Phone Number</label>
                            <div class="input-group glass-field">
                                <span class="input-icon" aria-hidden="true"><i data-lucide="phone"></i></span>
                                <input type="tel" id="phone" name="phone" class="form-control" required value="<?= e($_POST['phone'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="department">Department</label>
                            <div class="input-group glass-field">
                                <span class="input-icon" aria-hidden="true"><i data-lucide="building-2"></i></span>
                                <select id="department" name="department" class="form-control" required>
                                    <option value="">Select department</option>
                                    <option value="Computer Science" <?= (($_POST['department'] ?? '') === 'Computer Science' ? 'selected' : '') ?>>Computer Science</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="course_of_study">Course of Study</label>
                            <div class="input-group glass-field">
                                <span class="input-icon" aria-hidden="true"><i data-lucide="book-open"></i></span>
                                <select id="course_of_study" name="course_of_study" class="form-control" required>
                                    <option value="">Select course</option>
                                    <option value="Computer Science" <?= (($_POST['course_of_study'] ?? '') === 'Computer Science' ? 'selected' : '') ?>>Computer Science</option>
                                    <option value="Software and Web Development" <?= (($_POST['course_of_study'] ?? '') === 'Software and Web Development' ? 'selected' : '') ?>>Software and Web Development</option>
                                    <option value="Artificial Intelligence" <?= (($_POST['course_of_study'] ?? '') === 'Artificial Intelligence' ? 'selected' : '') ?>>Artificial Intelligence</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="level">Level</label>
                            <div class="input-group glass-field">
                                <span class="input-icon" aria-hidden="true"><i data-lucide="graduation-cap"></i></span>
                                <select id="level" name="level" class="form-control" required>
                                    <option value="">Select your level</option>
                                    <option value="ND I" <?= (($_POST['level'] ?? '') === 'ND I' ? 'selected' : '') ?>>ND I</option>
                                    <option value="ND II" <?= (($_POST['level'] ?? '') === 'ND II' ? 'selected' : '') ?>>ND II</option>
                                    <option value="HND I" <?= (($_POST['level'] ?? '') === 'HND I' ? 'selected' : '') ?>>HND I</option>
                                    <option value="HND II" <?= (($_POST['level'] ?? '') === 'HND II' ? 'selected' : '') ?>>HND II</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="semester">Semester</label>
                            <div class="input-group glass-field">
                                <span class="input-icon" aria-hidden="true"><i data-lucide="layers"></i></span>
                                <select id="semester" name="semester" class="form-control">
                                    <option value="">Select semester</option>
                                    <option value="First Semester" <?= (($_POST['semester'] ?? '') === 'First Semester' ? 'selected' : '') ?>>First Semester</option>
                                    <option value="Second Semester" <?= (($_POST['semester'] ?? '') === 'Second Semester' ? 'selected' : '') ?>>Second Semester</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="academic_session_id">Academic Session</label>
                            <div class="input-group glass-field">
                                <span class="input-icon" aria-hidden="true"><i data-lucide="calendar-days"></i></span>
                                <select id="academic_session_id" name="academic_session_id" class="form-control">
                                    <option value="">Select academic session</option>
                                    <?php foreach ($sessionOptions as $sessionOption): ?>
                                        <option value="<?= e($sessionOption['value'] ?: '') ?>" <?= ((string)($_POST['academic_session_id'] ?? '') === (string)($sessionOption['value'] ?? '')) ? 'selected' : '' ?>><?= e($sessionOption['label']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="address">Address</label>
                            <div class="input-group glass-field">
                                <span class="input-icon" aria-hidden="true"><i data-lucide="map-pin"></i></span>
                                <input type="text" id="address" name="address" class="form-control" value="<?= e($_POST['address'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="emergency_contact">Emergency Contact</label>
                            <div class="input-group glass-field">
                                <span class="input-icon" aria-hidden="true"><i data-lucide="phone-forwarded"></i></span>
                                <input type="text" id="emergency_contact" name="emergency_contact" class="form-control" value="<?= e($_POST['emergency_contact'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="blood_group">Blood Group</label>
                            <div class="input-group glass-field">
                                <span class="input-icon" aria-hidden="true"><i data-lucide="droplet"></i></span>
                                <input type="text" id="blood_group" name="blood_group" class="form-control" value="<?= e($_POST['blood_group'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="gender">Gender</label>
                            <div class="input-group glass-field">
                                <span class="input-icon" aria-hidden="true"><i data-lucide="user-check"></i></span>
                                <select id="gender" name="gender" class="form-control">
                                    <option value="">Select gender</option>
                                    <option value="Male" <?= (($_POST['gender'] ?? '') === 'Male' ? 'selected' : '') ?>>Male</option>
                                    <option value="Female" <?= (($_POST['gender'] ?? '') === 'Female' ? 'selected' : '') ?>>Female</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="profile_photo">Profile Photo <span class="text-danger">*</span></label>
                            <div class="input-group glass-field">
                                <span class="input-icon" aria-hidden="true"><i data-lucide="camera"></i></span>
                                <input type="file" id="profile_photo" name="profile_photo" class="form-control" accept="image/jpeg,image/png,image/webp" required>
                            </div>
                            <div class="form-text">Upload a clear passport photograph, maximum 5 MB.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="password">Password</label>
                            <div class="input-group glass-field">
                                <span class="input-icon" aria-hidden="true"><i data-lucide="lock"></i></span>
                                <input type="password" id="password" name="password" class="form-control" required autocomplete="new-password">
                                <button type="button" class="password-toggle" data-target="password" aria-label="Show password">
                                    <i data-lucide="eye"></i>
                                </button>
                            </div>
                            <div class="password-strength mt-2" id="passwordStrength" aria-live="polite">Weak</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="confirm_password">Confirm Password</label>
                            <div class="input-group glass-field">
                                <span class="input-icon" aria-hidden="true"><i data-lucide="lock-keyhole"></i></span>
                                <input type="password" id="confirm_password" name="confirm_password" class="form-control" required autocomplete="new-password">
                                <button type="button" class="password-toggle" data-target="confirm_password" aria-label="Show confirm password">
                                    <i data-lucide="eye"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn register-submit-btn mt-4 w-100" id="registerSubmitBtn">
                        <span class="btn-label"><i data-lucide="user-plus"></i> Create Account</span>
                    </button>
                    <p class="text-center text-secondary small mt-3">Already have an account? <a href="<?= APP_URL ?>/auth/login.php" class="fw-semibold">Login</a></p>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById('studentRegistrationForm');
        const passwordInput = document.getElementById('password');
        const confirmInput = document.getElementById('confirm_password');
        const submitButton = document.getElementById('registerSubmitBtn');
        const strengthText = document.getElementById('passwordStrength');
        const toggleButtons = Array.from(document.querySelectorAll('.password-toggle'));

        if (!passwordInput || !confirmInput || !strengthText) {
            return;
        }

        const updateStrength = function () {
            const value = passwordInput.value || '';
            const score = Number(value.length >= 8) + Number(/[A-Z]/.test(value)) + Number(/[a-z]/.test(value)) + Number(/\d/.test(value)) + Number(/[^A-Za-z0-9]/.test(value));
            let label = 'Weak';
            let className = 'password-strength weak';
            if (score >= 4) {
                label = 'Strong';
                className = 'password-strength strong';
            } else if (score >= 2) {
                label = 'Medium';
                className = 'password-strength medium';
            }
            strengthText.textContent = label;
            strengthText.className = className;
        };

        const updateMatchStatus = function () {
            if (!confirmInput) {
                return;
            }
            if (!confirmInput.value) {
                confirmInput.setAttribute('aria-invalid', 'false');
                return;
            }
            const isMatch = passwordInput.value === confirmInput.value;
            confirmInput.setAttribute('aria-invalid', isMatch ? 'false' : 'true');
        };

        passwordInput.addEventListener('input', function () {
            updateStrength();
            updateMatchStatus();
        });
        confirmInput.addEventListener('input', updateMatchStatus);

        toggleButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                const targetId = button.dataset.target;
                const input = document.getElementById(targetId);
                if (!input) {
                    return;
                }
                const isPassword = input.type === 'password';
                input.type = isPassword ? 'text' : 'password';
                const icon = button.querySelector('i');
                if (icon) {
                    icon.setAttribute('data-lucide', isPassword ? 'eye-off' : 'eye');
                    if (window.faIconFactory) {
                        window.faIconFactory.render();
                    }
                }
            });
        });

        if (form) {
            form.addEventListener('submit', function (event) {
                const password = passwordInput.value || '';
                const confirm = confirmInput.value || '';
                if (password !== confirm) {
                    event.preventDefault();
                    confirmInput.focus();
                    confirmInput.setAttribute('aria-invalid', 'true');
                    return;
                }
                if (submitButton) {
                    submitButton.disabled = true;
                    submitButton.innerHTML = '<span class="btn-label"><i data-lucide="loader-circle"></i> Creating account...</span>';
                    if (window.faIconFactory) {
                        window.faIconFactory.render();
                    }
                }
            });
        }

        updateStrength();
        updateMatchStatus();
    });
</script>
<?php if ($success): ?>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (window.Swal) {
            Swal.fire({
                icon: 'success',
                title: 'Registration Successful',
                text: 'Your account has been created successfully.',
                confirmButtonColor: '#14532d',
                showConfirmButton: false,
                timer: 2000,
                timerProgressBar: true
            }).then(function () {
                window.location.href = '<?= APP_URL ?>/auth/login.php';
            });
        } else {
            setTimeout(function () {
                window.location.href = '<?= APP_URL ?>/auth/login.php';
            }, 2000);
        }
    });
</script>
<?php endif; ?>
<?php require_once dirname(__DIR__) . '/templates/footer.php'; ?>
