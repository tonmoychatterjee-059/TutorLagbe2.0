<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/config/db.php';

if (empty($_SESSION['user_id'])) {
    header('Location: auth/login.php');
    exit;
}

$month = max(1, min(12, (int) ($_GET['month'] ?? date('n'))));
$year = (int) ($_GET['year'] ?? date('Y'));
$first = mktime(0, 0, 0, $month, 1, $year);
$days = (int) date('t', $first);
$start = (int) date('w', $first);
$prevMonth = (int) date('n', strtotime('-1 month', $first));
$prevYear = (int) date('Y', strtotime('-1 month', $first));
$nextMonth = (int) date('n', strtotime('+1 month', $first));
$nextYear = (int) date('Y', strtotime('+1 month', $first));
$rows = [];

try {
    $p = db();
    $isTutor = (($_SESSION['user_role'] ?? '') === 'tutor');
    $sql = $isTutor
        ? 'SELECT b.id, b.booking_date, b.start_time, b.end_time, b.status, b.session_type, b.tutor_id, b.student_id, s.name subject
           FROM bookings b JOIN subjects s ON s.id = b.subject_id
           WHERE b.tutor_id = (SELECT id FROM tutors WHERE user_id = ?)
             AND b.booking_date BETWEEN ? AND ?
           ORDER BY b.booking_date, b.start_time'
        : 'SELECT b.id, b.booking_date, b.start_time, b.end_time, b.status, b.session_type, b.tutor_id, b.student_id, s.name subject
           FROM bookings b JOIN subjects s ON s.id = b.subject_id
           WHERE b.student_id = ?
             AND b.booking_date BETWEEN ? AND ?
           ORDER BY b.booking_date, b.start_time';
    $q = $p->prepare($sql);
    $q->execute([$_SESSION['user_id'], date('Y-m-01', $first), date('Y-m-t', $first)]);
    foreach ($q as $r) {
        $rows[$r['booking_date']][] = $r;
    }
} catch (Throwable $e) {
}

$pageTitle = 'Monthly calendar';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/navbar.php';
?>
<main class="container py-5">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h1 class="h2 mb-1"><?= date('F Y', $first) ?></h1>
      <div class="text-secondary">Track upcoming sessions by date and status.</div>
    </div>
    <div class="btn-group">
      <a class="btn btn-outline-brand" href="?month=<?= $prevMonth ?>&year=<?= $prevYear ?>">Prev</a>
      <a class="btn btn-outline-brand" href="schedule.php">Week</a>
      <a class="btn btn-outline-brand" href="?month=<?= $nextMonth ?>&year=<?= $nextYear ?>">Next</a>
    </div>
  </div>

  <div class="d-flex gap-2 flex-wrap mb-3">
    <span class="badge text-bg-warning-subtle text-dark">Pending</span>
    <span class="badge text-bg-success-subtle text-dark">Accepted</span>
    <span class="badge text-bg-danger-subtle text-dark">Rejected</span>
    <span class="badge text-bg-info-subtle text-dark">Completed</span>
  </div>

  <div class="table-responsive">
    <table class="table table-bordered calendar-table align-middle">
      <thead>
        <tr>
          <?php foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $d): ?>
            <th><?= e($d) ?></th>
          <?php endforeach; ?>
        </tr>
      </thead>
      <tbody>
        <tr>
          <?php for ($i = 0; $i < $start; $i++): ?><td class="bg-body-tertiary"></td><?php endfor; ?>
          <?php for ($day = 1; $day <= $days; $day++): ?>
            <?php
              $date = sprintf('%04d-%02d-%02d', $year, $month, $day);
              $items = $rows[$date] ?? [];
              $today = $date === date('Y-m-d');
            ?>
            <td class="<?= $today ? 'border-success border-2' : '' ?>" style="vertical-align: top; min-width: 150px; height: 140px">
              <div class="d-flex justify-content-between align-items-center mb-2">
                <strong><?= $day ?></strong>
                <?php if ($today): ?><span class="badge text-bg-success">Today</span><?php endif; ?>
              </div>
              <?php foreach ($items as $item): ?>
                <?php
                  $statusClass = match ($item['status']) {
                      'accepted' => 'success',
                      'completed' => 'info',
                      'rejected' => 'danger',
                      default => 'warning',
                  };
                ?>
                <div class="small p-2 mb-2 rounded bg-<?= $statusClass ?>-subtle">
                  <div class="fw-semibold"><?= e($item['subject']) ?></div>
                  <div><?= e(date('g:i A', strtotime($item['start_time']))) ?> - <?= e(date('g:i A', strtotime($item['end_time']))) ?></div>
                  <div class="text-secondary"><?= e($item['status']) ?> · <?= e($item['session_type']) ?></div>
                  <?php if ($item['status'] === 'accepted' && $item['session_type'] === 'online'): ?>
                    <a class="small text-decoration-none" href="<?= e(base_url('video-call.php?booking_id=' . (int) $item['id'])) ?>">Join call</a>
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>
            </td>
            <?php if (($start + $day) % 7 === 0 && $day < $days): ?></tr><tr><?php endif; ?>
          <?php endfor; ?>
          <?php for ($i = ($start + $days) % 7; $i > 0 && $i < 7; $i++): ?><td class="bg-body-tertiary"></td><?php endfor; ?>
        </tr>
      </tbody>
    </table>
  </div>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
