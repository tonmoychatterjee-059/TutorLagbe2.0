<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/config/db.php';

if (empty($_SESSION['user_id'])) {
    header('Location: auth/login.php');
    exit;
}

$me = (int) $_SESSION['user_id'];
$other = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT);
$messages = [];
$users = [];

try {
    $p = db();
    $q = $p->prepare(
        'SELECT DISTINCT u.id, u.full_name
         FROM users u
         JOIN messages m ON (m.sender_id = u.id OR m.receiver_id = u.id)
         WHERE (m.sender_id = ? OR m.receiver_id = ?) AND u.id <> ?
         ORDER BY u.full_name'
    );
    $q->execute([$me, $me, $me]);
    $users = $q->fetchAll();

    if ($other) {
        $q = $p->prepare(
            'SELECT * FROM messages
             WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)
             ORDER BY created_at'
        );
        $q->execute([$me, $other, $other, $me]);
        $messages = $q->fetchAll();

        $q = $p->prepare('UPDATE messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ?');
        $q->execute([$other, $me]);

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST['csrf_token'] ?? null) && trim((string) ($_POST['message'] ?? '')) !== '') {
            $body = trim((string) $_POST['message']);
            $q = $p->prepare('INSERT INTO messages (sender_id, receiver_id, message) VALUES (?, ?, ?)');
            $q->execute([$me, $other, $body]);
            $p->prepare(
                'INSERT INTO notifications (user_id, type, message, related_id)
                 VALUES (?, "new_message", ?, ?)'
            )->execute([
                $other,
                'You received a new message.',
                $me,
            ]);
            header('Location: chat.php?user_id=' . $other);
            exit;
        }
    }
} catch (Throwable $e) {
}

$pageTitle = 'Messages';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/navbar.php';
?>
<main class="container py-5">
  <div class="row g-3">
    <aside class="col-md-4">
      <div class="profile-section">
        <div class="d-flex justify-content-between align-items-center">
          <h1 class="h5 mb-0">Conversations</h1>
          <a class="small text-success" href="notifications.php">Notifications</a>
        </div>
        <?php foreach ($users as $u): ?>
          <a class="d-block border-top py-2 text-decoration-none" href="chat.php?user_id=<?= (int) $u['id'] ?>"><?= e($u['full_name']) ?></a>
        <?php endforeach; ?>
      </div>
    </aside>
    <section class="col-md-8">
      <div class="profile-section">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <h1 class="h5 mb-0">Chat</h1>
          <?php if ($other): ?>
            <a class="btn btn-sm btn-outline-brand" href="video-call.php?user_id=<?= (int) $other ?>">Start video call</a>
          <?php endif; ?>
        </div>

        <div id="chatMessages" style="min-height: 280px">
          <?php foreach ($messages as $m): ?>
            <div class="text-<?= $m['sender_id'] == $me ? 'end' : '' ?> my-2">
              <span class="d-inline-block p-2 rounded bg-<?= $m['sender_id'] == $me ? 'success text-white' : 'light' ?>"><?= e($m['message']) ?></span>
            </div>
          <?php endforeach; ?>
        </div>

        <?php if ($other): ?>
          <form method="post" class="d-flex gap-2 mt-3">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input class="form-control" name="message" placeholder="Type a message" required>
            <button class="btn btn-brand">Send</button>
          </form>
          <form method="post" action="chat-upload.php" enctype="multipart/form-data" class="d-flex gap-2 mt-2">
            <input type="hidden" name="receiver_id" value="<?= (int) $other ?>">
            <input class="form-control" type="file" name="file" required>
            <button class="btn btn-outline-brand">Send file</button>
          </form>
        <?php endif; ?>
      </div>
    </section>
  </div>
</main>
<?php if ($other): ?>
<script>
const stream = new EventSource('chat-stream.php?user_id=<?= (int) $other ?>');
stream.onmessage = () => { location.reload(); };
</script>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
