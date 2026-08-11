<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/config/db.php';

if (empty($_SESSION['user_id']) || !in_array($_SESSION['user_role'] ?? '', ['student', 'tutor'], true)) {
    header('Location: auth/login.php');
    exit;
}

$role = $_SESSION['user_role'];
$userId = (int) $_SESSION['user_id'];
$availability = [];
$bookings = [];
$notice = '';
$tutorId = 0;

try {
    $pdo = db();

    if ($role === 'tutor') {
        $q = $pdo->prepare('SELECT id FROM tutors WHERE user_id = ? LIMIT 1');
        $q->execute([$userId]);
        $tutorId = (int) $q->fetchColumn();

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST['csrf_token'] ?? null) && $tutorId) {
            if (!empty($_POST['delete'])) {
                $q = $pdo->prepare('DELETE FROM availability WHERE id = ? AND tutor_id = ?');
                $q->execute([(int) $_POST['delete'], $tutorId]);
                $notice = 'Availability removed.';
            } elseif (!empty($_POST['day'])) {
                $q = $pdo->prepare('INSERT INTO availability (tutor_id, day_of_week, start_time, end_time, is_recurring) VALUES (?, ?, ?, ?, 1)');
                $q->execute([$tutorId, $_POST['day'], $_POST['start'], $_POST['end']]);
                $notice = 'Availability updated.';
            }
        }

        if ($tutorId) {
            $q = $pdo->prepare('SELECT * FROM availability WHERE tutor_id = ? ORDER BY FIELD(day_of_week,"Sat","Sun","Mon","Tue","Wed","Thu","Fri"), start_time');
            $q->execute([$tutorId]);
            $availability = $q->fetchAll();
        }
    }

    if ($role === 'tutor') {
        $bookingSql = "
            SELECT b.*, s.name subject, u.full_name other_name
            FROM bookings b
            JOIN subjects s ON s.id = b.subject_id
            JOIN users u ON u.id = b.student_id
            WHERE b.tutor_id = :id AND b.booking_date >= CURDATE()
            ORDER BY b.booking_date, b.start_time
        ";
        $q = $pdo->prepare($bookingSql);
        $q->execute(['id' => $tutorId]);
        $bookings = $q->fetchAll();
    } else {
        $bookingSql = "
            SELECT b.*, s.name subject, u.full_name other_name
            FROM bookings b
            JOIN subjects s ON s.id = b.subject_id
            JOIN tutors t ON t.id = b.tutor_id
            JOIN users u ON u.id = t.user_id
            WHERE b.student_id = :id AND b.booking_date >= CURDATE()
            ORDER BY b.booking_date, b.start_time
        ";
        $q = $pdo->prepare($bookingSql);
        $q->execute(['id' => $userId]);
        $bookings = $q->fetchAll();
    }
} catch (Throwable $e) {
}

$pageTitle = 'Schedule';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/navbar.php';
?>
<main class="section-soft py-5">
  <div class="container">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
      <div>
        <h1 class="h2 mb-1">My schedule</h1>
        <p class="text-secondary mb-0">Availability is what students see when they book you. Teacher details live in the tutor setup page.</p>
      </div>
      <?php if ($role === 'tutor'): ?>
        <a class="btn btn-brand" href="tutor/setup.php">Teacher details</a>
      <?php endif; ?>
    </div>

    <?php if ($notice): ?><div class="alert alert-success mt-4"><?= e($notice) ?></div><?php endif; ?>

    <?php if ($role === 'tutor' && !$tutorId): ?>
      <div class="alert alert-warning mt-4">
        Your tutor profile has not been created yet. Open <a href="tutor/setup.php">Teacher details</a> first, then add your subjects and availability.
      </div>
    <?php endif; ?>

    <div class="profile-section mt-4">
      <h2 class="h5">Weekly availability</h2>
      <?php if ($role === 'tutor'): ?>
        <p class="text-secondary small mb-3">Use the form below to add your weekly time slots.</p>
        <div class="availability-grid">
          <?php foreach (['Sat','Sun','Mon','Tue','Wed','Thu','Fri'] as $day): ?>
            <div class="availability-day">
              <strong><?= e($day) ?></strong>
              <?php foreach ($availability as $slot): ?>
                <?php if ($slot['day_of_week'] === $day): ?>
                  <div class="slot-link">
                    <?= e(substr((string) $slot['start_time'], 0, 5)) ?> - <?= e(substr((string) $slot['end_time'], 0, 5)) ?>
                  </div>
                <?php endif; ?>
              <?php endforeach; ?>
              <?php if (empty(array_filter($availability, fn($s) => $s['day_of_week'] === $day))): ?>
                <small class="text-secondary d-block mt-3">No slots</small>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
        <form method="post" class="row g-2 mt-4">
          <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
          <div class="col-md-3">
            <select class="form-select" name="day">
              <?php foreach (['Sat','Sun','Mon','Tue','Wed','Thu','Fri'] as $d): ?>
                <option value="<?= e($d) ?>"><?= e($d) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-3">
            <input class="form-control" type="time" name="start" required>
          </div>
          <div class="col-md-3">
            <input class="form-control" type="time" name="end" required>
          </div>
          <div class="col-md-3 d-grid">
            <button class="btn btn-brand">Add availability</button>
          </div>
        </form>

        <?php foreach ($availability as $slot): ?>
          <div class="border-top py-2 d-flex justify-content-between align-items-center">
            <div><?= e($slot['day_of_week']) ?> <?= e(substr((string) $slot['start_time'], 0, 5)) ?> - <?= e(substr((string) $slot['end_time'], 0, 5)) ?></div>
            <form method="post">
              <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
              <button class="btn btn-sm text-danger" name="delete" value="<?= (int) $slot['id'] ?>">Delete</button>
            </form>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <div class="availability-grid">
          <?php foreach (['Sat','Sun','Mon','Tue','Wed','Thu','Fri'] as $day): ?>
            <div class="availability-day">
              <strong><?= e($day) ?></strong>
              <?php foreach ($bookings as $b): ?>
                <?php if (date('D', strtotime($b['booking_date'])) === $day): ?>
                  <div class="slot-link <?= $b['status'] === 'pending' ? 'bg-warning-subtle text-dark' : '' ?>">
                    <?= e(date('g:i A', strtotime($b['start_time']))) ?><br>
                    <small><?= e($b['subject']) ?></small>
                  </div>
                <?php endif; ?>
              <?php endforeach; ?>
              <?php if (empty(array_filter($bookings, fn($b) => date('D', strtotime($b['booking_date'])) === $day))): ?>
                <small class="text-secondary d-block mt-3">No bookings</small>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <div class="profile-section mt-4">
      <h2 class="h5">Upcoming classes</h2>
      <?php foreach ($bookings as $b): ?>
        <div class="border-top py-2">
          <strong><?= e($b['subject']) ?></strong> with <?= e($b['other_name']) ?>
          <span class="float-end badge text-bg-<?= $b['status'] === 'accepted' ? 'success' : 'warning' ?>"><?= e($b['status']) ?></span><br>
          <small class="text-secondary"><?= e($b['booking_date']) ?> · <?= date('g:i A', strtotime($b['start_time'])) ?></small>
        </div>
      <?php endforeach; ?>
      <?php if (!$bookings): ?><p class="text-secondary mb-0">No upcoming classes.</p><?php endif; ?>
    </div>
  </div>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
