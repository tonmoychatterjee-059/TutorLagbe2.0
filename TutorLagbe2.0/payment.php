<?php
require_once __DIR__ . '/includes/student_auth_check.php';
require_once __DIR__ . '/config/db.php';

$id = filter_input(INPUT_GET, 'booking_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
    ?: filter_input(INPUT_POST, 'booking_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$booking = null;
$error = '';
$selectedMethod = (string) ($_POST['method'] ?? '');
$bkashTransactionId = trim((string) ($_POST['bkash_transaction_id'] ?? ''));
$bkashSenderNumber = trim((string) ($_POST['bkash_sender_number'] ?? ''));
$manualBkashNumber = trim((string) setting('bkash_personal_number', ''));
$manualBkashName = trim((string) setting('bkash_account_name', ''));

try {
    $pdo = db();
    $q = $pdo->prepare(
        'SELECT b.*, u.full_name tutor_name, u.email tutor_email, s.name subject
         FROM bookings b
         JOIN tutors t ON t.id = b.tutor_id
         JOIN users u ON u.id = t.user_id
         JOIN subjects s ON s.id = b.subject_id
         WHERE b.id = ? AND b.student_id = ?'
    );
    $q->execute([$id, $_SESSION['user_id']]);
    $booking = $q->fetch();
} catch (Throwable $e) {
}

if (!$booking) {
    http_response_code(404);
    exit('Booking not found.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $method = (string) ($_POST['method'] ?? '');
    $allowedMethods = ['bkash', 'nagad', 'rocket', 'visa', 'mastercard'];

    if (!verify_csrf($_POST['csrf_token'] ?? null) || !in_array($method, $allowedMethods, true)) {
        $error = 'Choose a valid payment method.';
    } else {
        try {
            $init = start_payment_flow($booking, $method);

            if (($init['mode'] ?? '') === 'manual' && $method === 'bkash') {
                if ($bkashTransactionId === '') {
                    $error = 'Enter your bKash transaction ID after sending money.';
                } else {
                    $pdo->beginTransaction();
                    $pdo->prepare(
                        'INSERT INTO payments (booking_id, student_id, amount, method, transaction_id, status)
                         VALUES (:booking, :student, :amount, :method, :transaction, :status)'
                    )->execute([
                        'booking' => $id,
                        'student' => $_SESSION['user_id'],
                        'amount' => $booking['price'],
                        'method' => $method,
                        'transaction' => $bkashTransactionId,
                        'status' => 'pending',
                    ]);
                    $pdo->prepare(
                        'INSERT INTO notifications (user_id, type, message, related_id)
                         VALUES (?, "payment_success", ?, ?)'
                    )->execute([
                        $_SESSION['user_id'],
                        'Your bKash payment has been recorded and is awaiting verification.',
                        $id,
                    ]);
                    $pdo->commit();
                    header('Location: payment-success.php?booking_id=' . $id);
                    exit;
                }
            } elseif (($init['mode'] ?? '') === 'redirect') {
                $pdo->prepare(
                    'INSERT INTO payments (booking_id, student_id, amount, method, transaction_id, status)
                     VALUES (:booking, :student, :amount, :method, :transaction, :status)'
                )->execute([
                    'booking' => $id,
                    'student' => $_SESSION['user_id'],
                    'amount' => $booking['price'],
                    'method' => $method,
                    'transaction' => $init['transaction_id'] ?: create_payment_reference($booking, $method),
                    'status' => $init['status'] ?? 'pending',
                ]);
                header('Location: ' . $init['url']);
                exit;
            } elseif (($init['mode'] ?? '') === 'record') {
                $pdo->beginTransaction();
                $pdo->prepare(
                    'INSERT INTO payments (booking_id, student_id, amount, method, transaction_id, status)
                     VALUES (:booking, :student, :amount, :method, :transaction, :status)'
                )->execute([
                    'booking' => $id,
                    'student' => $_SESSION['user_id'],
                    'amount' => $booking['price'],
                    'method' => $method,
                    'transaction' => $init['transaction_id'],
                    'status' => $init['status'] ?? 'success',
                ]);
                $pdo->prepare(
                    'INSERT INTO notifications (user_id, type, message, related_id)
                     VALUES (?, "payment_success", ?, ?)'
                )->execute([
                    $_SESSION['user_id'],
                    'Your payment has been completed.',
                    $id,
                ]);
                $pdo->commit();
                header('Location: payment-success.php?booking_id=' . $id);
                exit;
            }

            $error = $init['message'] ?? 'Payment gateway settings are not configured yet. Please update Settings first.';
        } catch (Throwable $e) {
            if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = 'Payment could not be started. Please check gateway settings and try again.';
        }
    }
}

$pageTitle = 'Payment';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/navbar.php';
?>
<main class="section-soft py-5">
  <div class="container" style="max-width: 850px">
    <div class="row g-4">
      <div class="col-md-7">
        <div class="booking-card">
          <h1 class="h3">Choose payment method</h1>
          <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
          <form method="post">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="booking_id" value="<?= (int) $id ?>">

            <div class="row g-2 mb-3">
              <?php foreach (['bkash' => 'bKash', 'nagad' => 'Nagad', 'rocket' => 'Rocket', 'visa' => 'Visa', 'mastercard' => 'Mastercard'] as $key => $label): ?>
                <div class="col-6">
                  <input class="btn-check" type="radio" name="method" id="<?= e($key) ?>" value="<?= e($key) ?>" required <?= $selectedMethod === $key ? 'checked' : '' ?>>
                  <label class="btn btn-outline-brand w-100 payment-method <?= e($key) ?>" for="<?= e($key) ?>"><?= e($label) ?></label>
                </div>
              <?php endforeach; ?>
            </div>

            <div class="alert alert-info small">
              <?php if ($manualBkashNumber !== ''): ?>
                bKash Send Money is enabled. Send the payment to <strong><?= e($manualBkashNumber) ?></strong><?= $manualBkashName !== '' ? ' (' . e($manualBkashName) . ')' : '' ?>, then paste the transaction ID below.
              <?php else: ?>
                Mobile wallets and cards are routed through the configured live gateway in Settings. If no gateway is configured, this page will tell you exactly what is missing.
              <?php endif; ?>
            </div>

            <div class="mb-3 <?= $selectedMethod === 'bkash' ? '' : 'd-none' ?>" id="bkashManualBox">
              <label class="form-label">bKash transaction ID</label>
              <input class="form-control" name="bkash_transaction_id" value="<?= e($bkashTransactionId) ?>" placeholder="Enter transaction ID">
              <label class="form-label mt-3">Your bKash number</label>
              <input class="form-control" name="bkash_sender_number" value="<?= e($bkashSenderNumber) ?>" placeholder="01XXXXXXXXX">
              <?php if ($manualBkashNumber !== ''): ?>
                <div class="form-text">Send money to <?= e($manualBkashNumber) ?><?= $manualBkashName !== '' ? ' (' . e($manualBkashName) . ')' : '' ?> from your bKash app, then enter the transaction ID here.</div>
              <?php endif; ?>
            </div>

            <button class="btn btn-brand w-100 mt-4">Continue to secure payment</button>
          </form>
        </div>
      </div>

      <div class="col-md-5">
        <div class="booking-summary">
          <h2 class="h5">Payment summary</h2>
          <p class="mb-1"><?= e($booking['subject']) ?> with <?= e($booking['tutor_name']) ?></p>
          <p class="small text-secondary"><?= e($booking['booking_date']) ?> · <?= date('g:i A', strtotime($booking['start_time'])) ?></p>
          <hr>
          <div class="d-flex justify-content-between">
            <span>Session fee</span>
            <strong>৳<?= number_format((float) $booking['price']) ?></strong>
          </div>
          <div class="d-flex justify-content-between">
            <span>Platform fee</span>
            <span>৳0</span>
          </div>
          <hr>
          <div class="d-flex justify-content-between">
            <strong>Total</strong>
            <strong>৳<?= number_format((float) $booking['price']) ?></strong>
          </div>
        </div>
      </div>
    </div>
  </div>
</main>

<script>
const bkashBox = document.querySelector('#bkashManualBox');
document.querySelectorAll('[name="method"]').forEach((radio) => {
  radio.addEventListener('change', () => {
    bkashBox.classList.toggle('d-none', document.querySelector('#bkash').checked === false);
  });
});
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
