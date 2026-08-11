<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/config/db.php';

if (empty($_SESSION['user_id'])) {
    header('Location: ' . base_url('auth/login.php?return_to=' . rawurlencode('booking.php' . (isset($_GET['tutor_id']) ? '?tutor_id=' . rawurlencode((string) $_GET['tutor_id']) : ''))));
    exit;
}

if (($_SESSION['user_role'] ?? '') !== 'student') {
    $pageTitle = 'Book tutor';
    require __DIR__ . '/includes/header.php';
    require __DIR__ . '/includes/navbar.php';
    echo '<main class="container py-5"><div class="alert alert-warning">Only students can book tutors from this page.</div></main>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$tutorId = filter_input(INPUT_GET, 'tutor_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
    ?: filter_input(INPUT_POST, 'tutor_id', FILTER_VALIDATE_INT);
$errors = [];
$tutor = null;
$subjects = [];
$slots = [];
$selectedDate = $_GET['booking_date'] ?? $_POST['booking_date'] ?? date('Y-m-d');
$selectedSlot = $_POST['slot'] ?? '';
$selectedType = (string) ($_POST['session_type'] ?? 'online');
$selectedAddress = trim((string) ($_POST['address'] ?? ''));
$studentAddress = '';
$selectedSubjectId = filter_input(INPUT_POST, 'subject_id', FILTER_VALIDATE_INT) ?: null;

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate)) {
    $selectedDate = date('Y-m-d');
}
$weekdayShort = date('D', strtotime($selectedDate));
$weekdayFull = date('l', strtotime($selectedDate));

try {
    $pdo = db();
    $q = $pdo->prepare('SELECT t.id, t.hourly_rate, u.full_name FROM tutors t JOIN users u ON u.id = t.user_id WHERE t.id = :id AND u.is_verified = 1');
    $q->execute(['id' => $tutorId]);
    $tutor = $q->fetch();

    if ($tutor) {
        $q = $pdo->prepare('SELECT s.id, s.name FROM tutor_subjects ts JOIN subjects s ON s.id = ts.subject_id WHERE ts.tutor_id = :id GROUP BY s.id ORDER BY s.name');
        $q->execute(['id' => $tutorId]);
        $subjects = $q->fetchAll();
        try {
            if (!empty($_SESSION['user_id'])) {
                $q = $pdo->prepare('SELECT address FROM users WHERE id = ?');
                $q->execute([$_SESSION['user_id']]);
                $studentAddress = (string) ($q->fetchColumn() ?: '');
            }
        } catch (Throwable $e) {
            $studentAddress = '';
        }

        try {
            $q = $pdo->prepare(
                "SELECT a.start_time, a.end_time
                 FROM availability a
                 WHERE a.tutor_id = :id
                   AND ((a.is_recurring = 1 AND (LOWER(a.day_of_week) = LOWER(:day_short) OR LOWER(a.day_of_week) = LOWER(:day_full)))
                        OR (a.is_recurring = 0 AND a.specific_date = :specific_date))
                   AND NOT EXISTS (
                       SELECT 1
                       FROM bookings b
                       WHERE b.tutor_id = a.tutor_id
                         AND b.booking_date = :booking_date
                         AND b.start_time = a.start_time
                         AND b.end_time = a.end_time
                         AND b.status IN ('pending', 'accepted')
                   )
                 ORDER BY a.start_time"
            );
            $q->execute([
                'id' => $tutorId,
                'day_short' => $weekdayShort,
                'day_full' => $weekdayFull,
                'specific_date' => $selectedDate,
                'booking_date' => $selectedDate,
            ]);
            $slots = $q->fetchAll();
        } catch (Throwable $e) {
            $slots = [];
        }
    }
} catch (Throwable $e) {
    $errors[] = 'Unable to load tutor details.';
}

if (!$tutor) {
    http_response_code(404);
    $pageTitle = 'Book tutor';
    require __DIR__ . '/includes/header.php';
    require __DIR__ . '/includes/navbar.php';
    echo '<main class="container py-5 text-center"><h1 class="h3">Tutor not found</h1><a class="btn btn-brand" href="find-tutor.php">Browse tutors</a></main>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'book') {
    $subjectId = filter_input(INPUT_POST, 'subject_id', FILTER_VALIDATE_INT);
    $type = (string) ($_POST['session_type'] ?? '');
    $selectedType = in_array($type, ['online', 'offline'], true) ? $type : 'online';
    $selectedAddress = trim((string) ($_POST['address'] ?? ''));

    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Your session expired.';
    }
    if (!in_array($selectedType, ['online', 'offline'], true)) {
        $errors[] = 'Select tuition type.';
    }
    if (!in_array($selectedAddress, shared_address_options(), true)) {
        $selectedAddress = '';
    }
    if ($selectedType === 'offline' && $selectedAddress === '') {
        $errors[] = 'Choose an address for offline tuition.';
    }
    if (!$subjectId) {
        $errors[] = 'Select a subject.';
    }
    if (!in_array($selectedSlot, array_map(static fn($x) => $x['start_time'] . '|' . $x['end_time'], $slots), true)) {
        $errors[] = 'That time is no longer available.';
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();
            $q = $pdo->prepare(
                "INSERT INTO bookings (student_id, tutor_id, subject_id, booking_date, start_time, end_time, session_type, address, status, price)
                 VALUES (:student, :tutor, :subject, :date, :start, :end, :type, :address, 'pending', :price)"
            );
            [$start, $end] = explode('|', $selectedSlot);
            $q->execute([
                'student' => $_SESSION['user_id'],
                'tutor' => $tutorId,
                'subject' => $subjectId,
                'date' => $selectedDate,
                'start' => $start,
                'end' => $end,
                'type' => $selectedType,
                'address' => $selectedType === 'offline' ? $selectedAddress : null,
                'price' => $tutor['hourly_rate'],
            ]);
            $booking = (int) $pdo->lastInsertId();

            $q = $pdo->prepare(
                "INSERT INTO notifications (user_id, type, message, related_id)
                 SELECT user_id, 'booking_pending', :message, :booking FROM tutors WHERE id = :tutor"
            );
            $q->execute(['message' => 'A new booking request needs your response.', 'booking' => $booking, 'tutor' => $tutorId]);

            $pdo->commit();
            header('Location: payment.php?booking_id=' . $booking);
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = 'Unable to create booking. Please try again.';
        }
    }
}

$pageTitle = 'Book tutor';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/navbar.php';
?>
<main class="section-soft py-5">
  <div class="container">
    <div class="booking-card mx-auto">
      <h1 class="h3">Book <?= e($tutor['full_name']) ?></h1>
      <p class="text-secondary">Choose a subject, date, and available time.</p>

      <?php if ($errors): ?>
        <div class="alert alert-danger"><?= e(implode(' ', $errors)) ?></div>
      <?php endif; ?>

      <form method="get" class="mb-3">
        <input type="hidden" name="tutor_id" value="<?= $tutorId ?>">
        <label class="form-label">Date</label>
        <input class="form-control" type="date" name="booking_date" min="<?= date('Y-m-d') ?>" value="<?= e($selectedDate) ?>" onchange="this.form.submit()">
      </form>

      <form method="post">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="book">
        <input type="hidden" name="tutor_id" value="<?= $tutorId ?>">
        <input type="hidden" name="booking_date" value="<?= e($selectedDate) ?>">

        <div class="mb-3">
          <label class="form-label">Subject</label>
          <select class="form-select" name="subject_id" required>
            <option value="">Select subject</option>
            <?php foreach ($subjects as $subject): ?>
              <option value="<?= $subject['id'] ?>" <?= $selectedSubjectId === (int) $subject['id'] ? 'selected' : '' ?>><?= e($subject['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="mb-3">
          <label class="form-label">Available time</label>
          <select class="form-select" name="slot" required>
            <option value="">Select a slot</option>
            <?php foreach ($slots as $slot): ?>
              <?php $value = $slot['start_time'] . '|' . $slot['end_time']; ?>
              <option value="<?= e($value) ?>" <?= $selectedSlot === $value ? 'selected' : '' ?>>
                <?= date('g:i A', strtotime($slot['start_time'])) ?> – <?= date('g:i A', strtotime($slot['end_time'])) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <?php if (!$slots): ?>
            <div class="form-text text-danger">
              No available time slots for this date. Please choose another date or ask the tutor to add availability.
            </div>
          <?php endif; ?>
        </div>

        <div class="mb-3">
          <label class="form-label d-block">Tuition type</label>
          <input class="btn-check" type="radio" name="session_type" id="online" value="online" <?= $selectedType === 'online' ? 'checked' : '' ?>>
          <label class="btn btn-outline-brand" for="online">Online</label>
          <input class="btn-check" type="radio" name="session_type" id="offline" value="offline" <?= $selectedType === 'offline' ? 'checked' : '' ?>>
          <label class="btn btn-outline-brand" for="offline">Offline</label>
        </div>

        <div class="mb-3 <?= $selectedType === 'offline' ? '' : 'd-none' ?>" id="addressGroup">
          <label class="form-label">Class address</label>
          <select class="form-select" name="address">
            <option value="">Select address</option>
            <?php foreach (shared_address_options() as $address): ?>
              <option value="<?= e($address) ?>" <?= $selectedAddress === $address ? 'selected' : '' ?>><?= e($address) ?></option>
            <?php endforeach; ?>
          </select>
          <?php if ($studentAddress !== ''): ?>
            <div class="form-text">Your saved address is <?= e($studentAddress) ?>.</div>
          <?php endif; ?>
        </div>

        <div class="border rounded p-3 mb-4">
          <strong>Payment summary</strong>
          <div class="d-flex justify-content-between mt-2">
            <span>Session fee</span>
            <span>৳<?= number_format((float) $tutor['hourly_rate']) ?></span>
          </div>
        </div>

        <button class="btn btn-brand w-100" <?= !$slots ? 'disabled' : '' ?>>Book session</button>
      </form>
    </div>
  </div>
</main>

<script>
const addressGroup = document.querySelector('#addressGroup');
const offline = document.querySelector('#offline');

function toggleAddress() {
  const show = offline.checked;
  addressGroup.classList.toggle('d-none', !show);
  const select = addressGroup.querySelector('select');
  if (select) {
    select.required = show;
  }
}

document.querySelectorAll('[name=session_type]').forEach(input => input.addEventListener('change', toggleAddress));
toggleAddress();
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
