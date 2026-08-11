<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/config/db.php';

if (empty($_SESSION['user_id'])) {
    header('Location: auth/login.php');
    exit;
}

$message = null;
$error = null;
$isAdmin = (($_SESSION['user_role'] ?? '') === 'admin');
$profile = ['full_name' => $_SESSION['user_name'] ?? '', 'phone' => '', 'address' => ''];

try {
    $pdo = db();
    $q = $pdo->prepare('SELECT full_name, phone, email, address FROM users WHERE id = ?');
    $q->execute([$_SESSION['user_id']]);
    $profile = $q->fetch() ?: $profile;
} catch (Throwable $e) {
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST['csrf_token'] ?? null)) {
    $action = (string) ($_POST['action'] ?? 'profile');
    try {
        if ($action === 'profile') {
            $fullName = trim((string) ($_POST['full_name'] ?? ''));
            $phone = trim((string) ($_POST['phone'] ?? ''));
            $address = trim((string) ($_POST['address'] ?? ''));
            if (!in_array($address, shared_address_options(), true)) {
                $address = '';
            }
            $pdo->prepare('UPDATE users SET full_name = ?, phone = ?, address = ? WHERE id = ?')->execute([$fullName, $phone, $address ?: null, $_SESSION['user_id']]);
            $_SESSION['user_name'] = $fullName;
            $profile['full_name'] = $fullName;
            $profile['phone'] = $phone;
            $profile['address'] = $address;
            $message = 'Profile updated.';
        } elseif ($action === 'system' && $isAdmin) {
            $keys = [
                'site_name',
                'support_email',
                'app_url',
                'payment_gateway',
                'bkash_personal_number',
                'bkash_account_name',
                'stripe_public_key',
                'stripe_secret_key',
                'payment_gateway_endpoint',
                'payment_gateway_token',
                'payment_demo_mode',
                'video_domain',
                'smtp_host',
                'smtp_port',
                'smtp_username',
                'smtp_password',
                'smtp_encryption',
                'smtp_from_email',
                'smtp_from_name',
            ];
            foreach ($keys as $key) {
                set_setting($key, trim((string) ($_POST[$key] ?? '')));
            }
            if (!empty($_POST['app_secret'])) {
                set_setting('app_secret', trim((string) $_POST['app_secret']));
            }
            $message = 'System settings saved.';
        } elseif ($action === 'test_email' && $isAdmin) {
            $to = $profile['email'] ?? setting('support_email', '');
            if ($to === '') {
                throw new RuntimeException('Set a support email address first.');
            }
            $ok = send_app_mail($to, 'TutorLagbe test email', '<p>This is a test email from TutorLagbe settings.</p>', 'This is a test email from TutorLagbe settings.');
            $message = $ok ? 'Test email sent.' : 'We could not send the test email. Check SMTP settings.';
        }
    } catch (Throwable $e) {
        $error = $e->getMessage() ?: 'Unable to save settings.';
    }
}

$pageTitle = 'Settings';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/navbar.php';
?>
<main class="container py-5" style="max-width: 980px">
  <h1 class="h2">Settings</h1>
  <?php if ($message): ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

  <div class="row g-4 mt-1">
    <div class="col-lg-5">
      <form method="post" class="profile-section">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="profile">
        <h2 class="h5">Profile</h2>
        <label class="form-label">Full name</label>
        <input class="form-control mb-3" name="full_name" value="<?= e((string) ($profile['full_name'] ?? '')) ?>" required>
        <label class="form-label">Phone</label>
        <input class="form-control mb-3" name="phone" value="<?= e((string) ($profile['phone'] ?? '')) ?>">
        <label class="form-label">Address</label>
        <select class="form-select mb-3" name="address">
          <option value="">Select address</option>
          <?php foreach (shared_address_options() as $address): ?>
            <option value="<?= e($address) ?>" <?= (string) ($profile['address'] ?? '') === $address ? 'selected' : '' ?>><?= e($address) ?></option>
          <?php endforeach; ?>
        </select>
        <div class="form-check form-switch mb-3">
          <input class="form-check-input" type="checkbox" id="darkMode">
          <label class="form-check-label" for="darkMode">Dark mode</label>
        </div>
        <button class="btn btn-brand">Save changes</button>
      </form>
    </div>

    <?php if ($isAdmin): ?>
      <div class="col-lg-7">
        <form method="post" class="profile-section mb-4">
          <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="action" value="system">
          <h2 class="h5">System settings</h2>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Site name</label>
              <input class="form-control" name="site_name" value="<?= e((string) setting('site_name', 'TutorLagbe')) ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label">Support email</label>
              <input class="form-control" name="support_email" value="<?= e((string) setting('support_email', 'hello@tutorlagbe.com')) ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label">App URL</label>
              <input class="form-control" name="app_url" value="<?= e((string) setting('app_url', base_url(''))) ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label">Video domain</label>
              <input class="form-control" name="video_domain" value="<?= e((string) setting('video_domain', 'meet.jit.si')) ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label">Payment gateway</label>
              <select class="form-select" name="payment_gateway">
                <option value="" <?= (string) setting('payment_gateway', '') === '' ? 'selected' : '' ?>>Disabled</option>
                <option value="stripe" <?= (string) setting('payment_gateway', '') === 'stripe' ? 'selected' : '' ?>>Stripe</option>
                <option value="generic" <?= (string) setting('payment_gateway', '') === 'generic' ? 'selected' : '' ?>>Generic API</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">bKash personal number</label>
              <input class="form-control" name="bkash_personal_number" value="<?= e((string) setting('bkash_personal_number', '')) ?>" placeholder="01XXXXXXXXX">
              <div class="form-text">Shown to students for manual Send Money payments.</div>
            </div>
            <div class="col-md-6">
              <label class="form-label">bKash account name</label>
              <input class="form-control" name="bkash_account_name" value="<?= e((string) setting('bkash_account_name', '')) ?>" placeholder="Optional">
            </div>
            <div class="col-md-6">
              <label class="form-label">Payment demo mode</label>
              <select class="form-select" name="payment_demo_mode">
                <option value="0" <?= (string) setting('payment_demo_mode', '0') !== '1' ? 'selected' : '' ?>>Disabled</option>
                <option value="1" <?= (string) setting('payment_demo_mode', '0') === '1' ? 'selected' : '' ?>>Enabled</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Stripe public key</label>
              <input class="form-control" name="stripe_public_key" value="<?= e((string) setting('stripe_public_key', '')) ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label">Stripe secret key</label>
              <input class="form-control" name="stripe_secret_key" value="<?= e((string) setting('stripe_secret_key', '')) ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label">Generic payment endpoint</label>
              <input class="form-control" name="payment_gateway_endpoint" value="<?= e((string) setting('payment_gateway_endpoint', '')) ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label">Generic gateway token</label>
              <input class="form-control" name="payment_gateway_token" value="<?= e((string) setting('payment_gateway_token', '')) ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label">SMTP host</label>
              <input class="form-control" name="smtp_host" value="<?= e((string) setting('smtp_host', '')) ?>">
            </div>
            <div class="col-md-3">
              <label class="form-label">SMTP port</label>
              <input class="form-control" name="smtp_port" value="<?= e((string) setting('smtp_port', '587')) ?>">
            </div>
            <div class="col-md-3">
              <label class="form-label">Encryption</label>
              <select class="form-select" name="smtp_encryption">
                <option value="" <?= (string) setting('smtp_encryption', 'tls') === '' ? 'selected' : '' ?>>None</option>
                <option value="tls" <?= (string) setting('smtp_encryption', 'tls') === 'tls' ? 'selected' : '' ?>>TLS</option>
                <option value="ssl" <?= (string) setting('smtp_encryption', 'tls') === 'ssl' ? 'selected' : '' ?>>SSL</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">SMTP username</label>
              <input class="form-control" name="smtp_username" value="<?= e((string) setting('smtp_username', '')) ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label">SMTP password</label>
              <input class="form-control" name="smtp_password" type="password" value="<?= e((string) setting('smtp_password', '')) ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label">SMTP from email</label>
              <input class="form-control" name="smtp_from_email" value="<?= e((string) setting('smtp_from_email', setting('support_email', 'noreply@tutorlagbe.com'))) ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label">SMTP from name</label>
              <input class="form-control" name="smtp_from_name" value="<?= e((string) setting('smtp_from_name', 'TutorLagbe')) ?>">
            </div>
            <div class="col-12">
              <label class="form-label">App secret</label>
              <input class="form-control" name="app_secret" value="<?= e((string) setting('app_secret', '')) ?>">
              <div class="form-text">Used to derive secure meeting-room names and other app tokens.</div>
            </div>
          </div>
          <div class="d-flex gap-2 mt-4">
            <button class="btn btn-brand">Save system settings</button>
          </div>
        </form>
        <form method="post" class="profile-section">
          <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="action" value="test_email">
          <h2 class="h5">SMTP test</h2>
          <p class="text-secondary">Send a test email to your account using the current SMTP configuration.</p>
          <button class="btn btn-outline-brand">Send test email</button>
        </form>
      </div>
    <?php endif; ?>
  </div>
</main>
<script>
const darkToggle = document.querySelector('#darkMode');
darkToggle.checked = localStorage.theme === 'dark';
darkToggle.onchange = () => {
  localStorage.theme = darkToggle.checked ? 'dark' : 'light';
  document.body.classList.toggle('dark-mode', darkToggle.checked);
};
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
