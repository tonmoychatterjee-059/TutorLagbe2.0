<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../config/db.php';

$message = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $error = 'Your session expired. Please try again.';
    } elseif (!filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
        $error = 'Enter a valid email address.';
    } else {
        $email = trim((string) $_POST['email']);
        try {
            $pdo = db();
            $q = $pdo->prepare('SELECT id, full_name, email FROM users WHERE email = ? LIMIT 1');
            $q->execute([$email]);
            $user = $q->fetch();

            if ($user) {
                $token = bin2hex(random_bytes(32));
                $hash = hash('sha256', $token);
                $expiresAt = (new DateTimeImmutable('+30 minutes'))->format('Y-m-d H:i:s');

                $pdo->prepare('UPDATE password_resets SET used_at = NOW() WHERE email = ? AND used_at IS NULL')->execute([$email]);
                $pdo->prepare('INSERT INTO password_resets (user_id, email, token_hash, expires_at) VALUES (?, ?, ?, ?)')
                    ->execute([$user['id'], $email, $hash, $expiresAt]);

                $resetLink = base_url('auth/reset_password.php?token=' . urlencode($token) . '&email=' . urlencode($email));
                send_app_mail(
                    $email,
                    'Reset your TutorLagbe password',
                    '<p>Hello ' . e($user['full_name']) . ',</p><p>We received a password reset request for your TutorLagbe account.</p><p><a href="' . e($resetLink) . '">Reset your password</a></p><p>This link expires in 30 minutes.</p>',
                    "Hello {$user['full_name']},\n\nWe received a password reset request for your TutorLagbe account.\n\nReset it here: {$resetLink}\n\nThis link expires in 30 minutes."
                );
            }

            $message = 'If an account exists for that email, password-reset instructions have been sent.';
        } catch (Throwable $e) {
            $error = 'We could not process that request right now.';
        }
    }
}

$pageTitle = 'Reset password';
require __DIR__ . '/../includes/header.php';
?>
<main class="auth-page">
  <section class="auth-card">
    <a class="brand d-block text-center mb-4" href="<?= base_url('index.php') ?>"><span class="brand-mark">T</span>TutorLagbe</a>
    <h1 class="h3 text-center">Reset your password</h1>
    <p class="text-secondary text-center mb-4">Enter your email and we’ll help you get back in.</p>
    <?php if ($message): ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
      <label class="form-label">Email address</label>
      <input class="form-control mb-4" type="email" name="email" required autocomplete="email">
      <button class="btn btn-brand w-100 py-2">Send reset link</button>
    </form>
    <p class="text-center small mt-4 mb-0"><a class="text-success" href="login.php">← Back to login</a></p>
  </section>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
