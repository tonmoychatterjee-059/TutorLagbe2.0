<?php
require_once __DIR__ . '/includes/admin_auth_check.php';
require_once __DIR__ . '/../config/db.php';

$metrics = [
    'revenue' => 0,
    'bookings' => 0,
    'pending' => 0,
    'completed' => 0,
    'students' => 0,
    'tutors' => 0,
];
$revenueRows = [];
$bookingRows = [];

try {
    $pdo = db();
    $metrics['revenue'] = (float) $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'success'")->fetchColumn();
    $metrics['bookings'] = (int) $pdo->query('SELECT COUNT(*) FROM bookings')->fetchColumn();
    $metrics['pending'] = (int) $pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'pending'")->fetchColumn();
    $metrics['completed'] = (int) $pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'completed'")->fetchColumn();
    $metrics['students'] = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetchColumn();
    $metrics['tutors'] = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'tutor' AND is_verified = 1")->fetchColumn();
    $revenueRows = $pdo->query("SELECT DATE_FORMAT(created_at, '%Y-%m') AS month, SUM(amount) AS total FROM payments WHERE status = 'success' GROUP BY month ORDER BY month DESC LIMIT 12")->fetchAll();
    $bookingRows = $pdo->query("SELECT status, COUNT(*) AS total FROM bookings GROUP BY status")->fetchAll();
} catch (Throwable $e) {
}

$pageTitle = 'Reports';
require __DIR__ . '/../includes/header.php';
?>
<main class="container py-5">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h1 class="h2 mb-1">Platform reports</h1>
      <p class="text-secondary mb-0">Revenue, bookings, and community growth at a glance.</p>
    </div>
  </div>

  <div class="row g-4 mb-4">
    <?php foreach ([
      ['Total revenue', '৳' . number_format($metrics['revenue'], 2)],
      ['All bookings', (string) $metrics['bookings']],
      ['Pending bookings', (string) $metrics['pending']],
      ['Completed bookings', (string) $metrics['completed']],
      ['Students', (string) $metrics['students']],
      ['Verified tutors', (string) $metrics['tutors']],
    ] as $card): ?>
      <div class="col-sm-6 col-xl-4">
        <div class="card stat-card p-4">
          <p class="text-secondary small mb-2"><?= e($card[0]) ?></p>
          <strong class="display-6"><?= e($card[1]) ?></strong>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="row g-4">
    <div class="col-lg-8">
      <div class="profile-section">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h2 class="h5 mb-0">Monthly revenue</h2>
          <span class="text-secondary small">Last 12 months</span>
        </div>
        <canvas id="revenueChart" height="120"></canvas>
      </div>
    </div>
    <div class="col-lg-4">
      <div class="profile-section">
        <h2 class="h5 mb-3">Booking status</h2>
        <canvas id="statusChart" height="220"></canvas>
      </div>
    </div>
  </div>

  <div class="profile-section mt-4">
    <h2 class="h5">Revenue history</h2>
    <div class="table-responsive">
      <table class="table align-middle mb-0">
        <thead>
          <tr><th>Month</th><th class="text-end">Revenue</th></tr>
        </thead>
        <tbody>
          <?php foreach ($revenueRows as $row): ?>
            <tr>
              <td><?= e((string) $row['month']) ?></td>
              <td class="text-end">৳<?= number_format((float) $row['total'], 2) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</main>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
new Chart(document.querySelector('#revenueChart'), {
  type: 'line',
  data: {
    labels: <?= json_encode(array_reverse(array_map(fn($row) => $row['month'], $revenueRows))) ?>,
    datasets: [{
      label: 'Revenue',
      data: <?= json_encode(array_reverse(array_map(fn($row) => (float) $row['total'], $revenueRows))) ?>,
      borderColor: '#087f5b',
      backgroundColor: 'rgba(8, 127, 91, 0.15)',
      tension: 0.35,
      fill: true
    }]
  },
  options: { responsive: true, plugins: { legend: { display: false } } }
});
new Chart(document.querySelector('#statusChart'), {
  type: 'doughnut',
  data: {
    labels: <?= json_encode(array_column($bookingRows, 'status')) ?>,
    datasets: [{
      data: <?= json_encode(array_map(fn($row) => (int) $row['total'], $bookingRows)) ?>,
      backgroundColor: ['#ff922b', '#2f9e44', '#fa5252', '#339af0', '#868e96']
    }]
  },
  options: { responsive: true }
});
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
