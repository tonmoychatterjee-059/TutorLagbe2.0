<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/config/db.php';

if (empty($_SESSION['user_id'])) {
    header('Location: auth/login.php');
    exit;
}

$rows = [];
try {
    $p = db();
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST['csrf_token'] ?? null)) {
        if (!empty($_POST['notification_id'])) {
            $q = $p->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?');
            $q->execute([(int) $_POST['notification_id'], $_SESSION['user_id']]);
        } else {
            $q = $p->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ?');
            $q->execute([$_SESSION['user_id']]);
        }
    }

    $q = $p->prepare('SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 100');
    $q->execute([$_SESSION['user_id']]);
    $rows = $q->fetchAll();
} catch (Throwable $e) {
}

$pageTitle = 'Notifications';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/navbar.php';
?>
<main class="container py-5">
  <div class="d-flex justify-content-between align-items-center">
    <h1 class="h2 mb-0">Notifications</h1>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
      <button class="btn btn-outline-brand">Mark all as read</button>
    </form>
  </div>

  <div class="mt-4">
    <?php foreach ($rows as $n): ?>
      <?php $href = notification_href($n); ?>
      <div class="profile-section my-2 <?= $n['is_read'] ? '' : 'border-success' ?>">
        <div class="d-flex justify-content-between gap-3">
          <div>
            <span class="badge text-bg-light"><?= e(str_replace('_', ' ', $n['type'])) ?></span>
            <div class="mt-2">
              <a class="text-decoration-none" href="<?= e($href) ?>"><?= e($n['message']) ?></a>
            </div>
            <small class="d-block text-secondary mt-1"><?= e($n['created_at']) ?></small>
          </div>
          <?php if (!$n['is_read']): ?>
            <form method="post">
              <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="notification_id" value="<?= (int) $n['id'] ?>">
              <button class="btn btn-sm btn-outline-brand">Read</button>
            </form>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
    <?php if (!$rows): ?>
      <p class="text-secondary">No notifications.</p>
    <?php endif; ?>
  </div>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
