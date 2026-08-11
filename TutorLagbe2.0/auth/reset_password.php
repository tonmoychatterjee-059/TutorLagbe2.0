<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../config/db.php';

$error = null;
$message = null;
$email = trim((string) ($_GET['email'] ?? $_POST['email'] ?? ''));
$token = trim((string) ($_GET['token'] ?? $_POST['token'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['confirm_password'] ?? '');
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $error = 'Your session expired. Please try again.';
    } elseif ($password === '' || strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        try {
            $pdo = db();
            $q = $pdo->prepare(
                'SELECT pr.id, pr.user_id, pr.token_hash, pr.expires_at
                 FROM password_resets pr
                 WHERE pr.email = ? AND pr.token_hash = ? AND pr.used_at IS NULL
                 ORDER BY pr.created_at DESC LIMIT 1'
            );
            $q->execute([$email, hash('sha256', $token)]);
            $row = $q->fetch();

            if (!$row || strtotime((string) $row['expires_at']) < time()) {
                $error = 'That reset link is invalid or has expired.';
            } else {
                $pdo->beginTransaction();
                $pdo->prepare('UPDATE users SET password = ? WHERE id = ?')->execute([password_hash($password, PASSWORD_DEFAULT), $row['user_id']]);
                $pdo->prepare('UPDATE password_resets SET used_at = NOW() WHERE id = ?')->execute([$row['id']]);
                $pdo->commit();
                $message = 'Your password has been updated. You can log in now.';
            }
        } catch (Throwable $e) {
            if (function_exists('db')) {
                try {
                    $pdo = db();
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                } catch (Throwable $inner) {
                }
            }
            $error = 'We could not reset the password right now.';
        }
    }
}

$pageTitle = 'Set new password';
require __DIR__ . '/../includes/header.php';
?>
<main class="auth-page">
  <section class="auth-card">
    <h1 class="h3 text-center">Set a new password</h1>
    <?php if ($message): ?>
      <div class="alert alert-success"><?= e($message) ?></div>
      <div class="text-center"><a class="btn btn-brand" href="login.php">Go to login</a></div>
    <?php else: ?>
      <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
      <form method="post">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="email" value="<?= e($email) ?>">
        <input type="hidden" name="token" value="<?= e($token) ?>">
        <label class="form-label">New password</label>
        <input class="form-control mb-3" type="password" name="password" minlength="8" required>
        <label class="form-label">Confirm password</label>
        <input class="form-control mb-4" type="password" name="confirm_password" minlength="8" required>
        <button class="btn btn-brand w-100 py-2">Update password</button>
      </form>
    <?php endif; ?>
  </section>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
