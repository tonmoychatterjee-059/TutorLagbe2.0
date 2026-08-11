<?php
require_once __DIR__ . '/includes/student_auth_check.php';
require_once __DIR__ . '/config/db.php';

$bookingId = filter_input(INPUT_GET, 'booking_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$booking = null;
$payment = null;

try {
    $pdo = db();
    $q = $pdo->prepare(
        'SELECT b.*, s.name subject, u.full_name tutor_name
         FROM bookings b
         JOIN tutors t ON t.id = b.tutor_id
         JOIN users u ON u.id = t.user_id
         JOIN subjects s ON s.id = b.subject_id
         WHERE b.id = ? AND b.student_id = ?'
    );
    $q->execute([$bookingId, $_SESSION['user_id']]);
    $booking = $q->fetch();

    if ($booking) {
        $q = $pdo->prepare(
            'SELECT method, transaction_id, status, created_at
             FROM payments
             WHERE booking_id = ? AND student_id = ?
             ORDER BY created_at DESC
             LIMIT 1'
        );
        $q->execute([$bookingId, $_SESSION['user_id']]);
        $payment = $q->fetch() ?: null;
    }
} catch (Throwable $e) {
}

$pageTitle = 'Payment status';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/navbar.php';
?>
<main class="container py-5 text-center">
  <div class="booking-card mx-auto" style="max-width: 720px">
    <div class="display-4">✓</div>
    <h1 class="h3 mt-3">
      <?= $payment && $payment['status'] === 'pending' ? 'Payment recorded' : 'Payment successful' ?>
    </h1>
    <p class="text-secondary">
      <?= $payment && $payment['status'] === 'pending'
        ? 'Your booking is recorded and the payment is waiting for verification.'
        : 'Your booking is now recorded. Once the tutor accepts it, you’ll see the meeting link and updates in your dashboard.' ?>
    </p>

    <?php if ($booking): ?>
      <div class="profile-section text-start mb-4">
        <strong><?= e($booking['subject']) ?></strong> with <?= e($booking['tutor_name']) ?><br>
        <small class="text-secondary"><?= e($booking['booking_date']) ?> · <?= date('g:i A', strtotime($booking['start_time'])) ?></small>
      </div>
    <?php endif; ?>

    <?php if ($payment): ?>
      <div class="alert alert-info text-start">
        <div><strong>Method:</strong> <?= e(ucfirst($payment['method'])) ?></div>
        <?php if (!empty($payment['transaction_id'])): ?>
          <div><strong>Transaction ID:</strong> <?= e($payment['transaction_id']) ?></div>
        <?php endif; ?>
        <div><strong>Status:</strong> <?= e(ucfirst($payment['status'])) ?></div>
      </div>
    <?php endif; ?>

    <div class="d-flex gap-2 justify-content-center flex-wrap">
      <a class="btn btn-brand" href="student/dashboard.php">Go to dashboard</a>
      <a class="btn btn-outline-brand" href="student/my-bookings.php">View bookings</a>
    </div>
  </div>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
