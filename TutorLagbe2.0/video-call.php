<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/config/db.php';

if (empty($_SESSION['user_id'])) {
    header('Location: auth/login.php');
    exit;
}

$bookingId = filter_input(INPUT_GET, 'booking_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$peerId = filter_input(INPUT_GET, 'peer_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$booking = null;

try {
    $pdo = db();
    if ($bookingId) {
        $q = $pdo->prepare(
            'SELECT b.*, s.name subject, u.full_name tutor_name
             FROM bookings b
             JOIN subjects s ON s.id = b.subject_id
             JOIN tutors t ON t.id = b.tutor_id
             JOIN users u ON u.id = t.user_id
             WHERE b.id = ? AND (b.student_id = ? OR t.user_id = ?)'
        );
        $q->execute([$bookingId, $_SESSION['user_id'], $_SESSION['user_id']]);
        $booking = $q->fetch();
    }
} catch (Throwable $e) {
}

if (!$booking && !$peerId) {
    http_response_code(404);
    exit('Video call not available.');
}

$roomSource = $booking ?: ['id' => 'peer-' . $peerId, 'booking_date' => date('Y-m-d'), 'start_time' => date('H:i:s')];
$callUrl = booking_meeting_url($roomSource);
$pageTitle = 'Video call';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/navbar.php';
?>
<main class="container py-5">
  <div class="booking-card">
    <h1 class="h3">Join video call</h1>
    <p class="text-secondary mb-2">This room opens in a hosted Jitsi meeting, so students and tutors can join from any browser.</p>
    <?php if ($booking): ?>
      <p class="mb-1"><strong><?= e($booking['subject']) ?></strong></p>
      <p class="text-secondary"><?= e($booking['booking_date']) ?> · <?= date('g:i A', strtotime($booking['start_time'])) ?></p>
    <?php endif; ?>
    <a class="btn btn-brand" href="<?= e($callUrl) ?>" target="_blank" rel="noopener">Open meeting room</a>
    <a class="btn btn-outline-brand ms-2" href="<?= base_url('chat.php' . ($peerId ? '?user_id=' . (int) $peerId : '')) ?>">Back to chat</a>
  </div>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
