<?php require_once dirname(__DIR__) . '/templates/header.php'; ?>
<?php
$auth = new Auth(Database::getInstance());
$auth->requireLogin('admin');
$portal = new Portal(Database::getInstance());
$settings = $portal->getSettings();
$errors = [];
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf();
        $portal->saveSettings([
            'department_name' => $_POST['department_name'] ?? '',
            'department_logo' => $_POST['department_logo'] ?? '',
            'address' => $_POST['address'] ?? '',
            'contact_email' => $_POST['contact_email'] ?? '',
            'contact_phone' => $_POST['contact_phone'] ?? '',
            'current_academic_session' => $_POST['current_academic_session'] ?? '',
            'registration_status' => $_POST['registration_status'] ?? 'Open',
            'maintenance_mode' => (int)($_POST['maintenance_mode'] ?? 0),
            'bank_name' => $_POST['bank_name'] ?? '',
            'account_name' => $_POST['account_name'] ?? '',
            'account_number' => $_POST['account_number'] ?? '',
            'payment_instructions' => $_POST['payment_instructions'] ?? '',
            'smtp_host' => $_POST['smtp_host'] ?? '',
            'smtp_port' => $_POST['smtp_port'] ?? '',
            'smtp_username' => $_POST['smtp_username'] ?? '',
            'smtp_password' => $_POST['smtp_password'] ?? '',
            'smtp_encryption' => $_POST['smtp_encryption'] ?? '',
            'facebook' => $_POST['facebook'] ?? '',
            'tiktok' => $_POST['tiktok'] ?? '',
            'x_handle' => $_POST['x_handle'] ?? '',
        ]);
        $success = 'Settings updated successfully.';
        $settings = $portal->getSettings();
    } catch (Exception $e) {
        $errors[] = $e->getMessage();
    }
}
?>
<div class="container py-5">
  <div class="card shadow-sm border-0 rounded-4 p-4">
    <h4 class="fw-bold">Portal Settings</h4>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
      <?php foreach ($errors as $error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endforeach; ?>
      <?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
      <div class="row g-3 mt-2">
        <div class="col-md-6"><label class="form-label">Department Name</label><input type="text" name="department_name" class="form-control" value="<?= e($settings['department_name'] ?? '') ?>"></div>
        <div class="col-md-6"><label class="form-label">Department Logo</label><input type="text" name="department_logo" class="form-control" value="<?= e($settings['department_logo'] ?? '') ?>"></div>
        <div class="col-md-6"><label class="form-label">Address</label><input type="text" name="address" class="form-control" value="<?= e($settings['address'] ?? '') ?>"></div>
        <div class="col-md-6"><label class="form-label">Contact Email</label><input type="email" name="contact_email" class="form-control" value="<?= e($settings['contact_email'] ?? '') ?>"></div>
        <div class="col-md-6"><label class="form-label">Contact Phone</label><input type="text" name="contact_phone" class="form-control" value="<?= e($settings['contact_phone'] ?? '') ?>"></div>
        <div class="col-md-6"><label class="form-label">Current Academic Session</label><input type="text" name="current_academic_session" class="form-control" value="<?= e($settings['current_academic_session'] ?? '') ?>"></div>
        <div class="col-md-6"><label class="form-label">Registration Status</label><select name="registration_status" class="form-select"><option value="Open" <?= ($settings['registration_status'] ?? 'Open') === 'Open' ? 'selected' : '' ?>>Open</option><option value="Closed" <?= ($settings['registration_status'] ?? '') === 'Closed' ? 'selected' : '' ?>>Closed</option></select></div>
        <div class="col-md-6"><label class="form-label">Maintenance Mode</label><select name="maintenance_mode" class="form-select"><option value="0" <?= empty($settings['maintenance_mode']) ? 'selected' : '' ?>>Off</option><option value="1" <?= !empty($settings['maintenance_mode']) ? 'selected' : '' ?>>On</option></select></div>
        <div class="col-md-6"><label class="form-label">Bank Name</label><input type="text" name="bank_name" class="form-control" value="<?= e($settings['bank_name'] ?? '') ?>"></div>
        <div class="col-md-6"><label class="form-label">Account Name</label><input type="text" name="account_name" class="form-control" value="<?= e($settings['account_name'] ?? '') ?>"></div>
        <div class="col-md-6"><label class="form-label">Account Number</label><input type="text" name="account_number" class="form-control" value="<?= e($settings['account_number'] ?? '') ?>"></div>
        <div class="col-12"><label class="form-label">Payment Instructions</label><textarea name="payment_instructions" class="form-control" rows="3"><?= e($settings['payment_instructions'] ?? '') ?></textarea></div>
        <div class="col-md-6"><label class="form-label">SMTP Host</label><input type="text" name="smtp_host" class="form-control" value="<?= e($settings['smtp_host'] ?? '') ?>"></div>
        <div class="col-md-6"><label class="form-label">SMTP Port</label><input type="text" name="smtp_port" class="form-control" value="<?= e($settings['smtp_port'] ?? '') ?>"></div>
        <div class="col-md-6"><label class="form-label">SMTP Username</label><input type="text" name="smtp_username" class="form-control" value="<?= e($settings['smtp_username'] ?? '') ?>"></div>
        <div class="col-md-6"><label class="form-label">SMTP Password</label><input type="password" name="smtp_password" class="form-control" value="<?= e($settings['smtp_password'] ?? '') ?>"></div>
        <div class="col-md-6"><label class="form-label">SMTP Encryption</label><input type="text" name="smtp_encryption" class="form-control" value="<?= e($settings['smtp_encryption'] ?? '') ?>"></div>
        <div class="col-md-6"><label class="form-label">Facebook</label><input type="text" name="facebook" class="form-control" value="<?= e($settings['facebook'] ?? '') ?>"></div>
        <div class="col-md-6"><label class="form-label">TikTok</label><input type="text" name="tiktok" class="form-control" value="<?= e($settings['tiktok'] ?? '') ?>"></div>
        <div class="col-md-6"><label class="form-label">X</label><input type="text" name="x_handle" class="form-control" value="<?= e($settings['x_handle'] ?? '') ?>"></div>
      </div>
      <button class="btn btn-primary mt-3">Save Settings</button>
    </form>
  </div>
</div>
<?php require_once dirname(__DIR__) . '/templates/footer.php'; ?>
