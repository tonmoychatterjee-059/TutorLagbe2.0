<?php
require_once __DIR__ . '/../includes/tutor_auth_check.php';
require_once __DIR__ . '/../config/db.php';

$stats = ['students' => 0, 'upcoming' => 0, 'pending' => 0, 'rating' => 0];
$hasProfile = false;
$profileComplete = false;
$subjectsCount = 0;
$slotsCount = 0;

try {
    $p = db();
    $q = $p->prepare('SELECT * FROM tutors WHERE user_id = ?');
    $q->execute([$_SESSION['user_id']]);
    $t = $q->fetch();

    if ($t) {
        $hasProfile = true;
        $profileComplete = trim((string) $t['bio']) !== '' && trim((string) $t['education']) !== '' && (float) $t['hourly_rate'] > 0;
        $id = (int) $t['id'];
        $stats['rating'] = (float) $t['rating'];

        $q = $p->prepare('SELECT COUNT(*) FROM tutor_subjects WHERE tutor_id = ?');
        $q->execute([$id]);
        $subjectsCount = (int) $q->fetchColumn();

        $q = $p->prepare('SELECT COUNT(*) FROM availability WHERE tutor_id = ?');
        $q->execute([$id]);
        $slotsCount = (int) $q->fetchColumn();

        foreach (['students' => "COUNT(DISTINCT student_id)", 'upcoming' => "COUNT(*)", 'pending' => "COUNT(*)"] as $key => $select) {
            $condition = $key === 'upcoming' ? "status='accepted' AND booking_date >= CURDATE()" : ($key === 'pending' ? "status='pending'" : '1');
            $q = $p->prepare("SELECT $select FROM bookings WHERE tutor_id = ? AND $condition");
            $q->execute([$id]);
            $stats[$key] = (int) $q->fetchColumn();
        }
    }
} catch (Throwable $e) {
}

$pageTitle = 'Tutor dashboard';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/navbar.php';
?>
<main class="section-soft py-5">
  <div class="container">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
      <div>
        <h1 class="h2 mb-1">Tutor dashboard</h1>
        <p class="text-secondary mb-0">Complete your teacher details so students can understand what you teach and when you’re available.</p>
      </div>
      <a class="btn btn-brand" href="setup.php">Teacher details</a>
    </div>

    <?php if (!$hasProfile): ?>
      <div class="alert alert-warning mt-4">Your tutor profile is not set up yet. Add your teacher details, subjects, and availability first.</div>
    <?php elseif (!$profileComplete || !$subjectsCount || !$slotsCount): ?>
      <div class="alert alert-info mt-4">Your tutor profile exists, but it still needs some details. Add subjects and availability so students can book you.</div>
    <?php endif; ?>

    <div class="row g-3 mt-2">
      <?php foreach (['students' => 'Total students', 'upcoming' => 'Upcoming sessions', 'pending' => 'Pending requests', 'rating' => 'Average rating'] as $key => $label): ?>
        <div class="col-6 col-lg-3">
          <div class="profile-section text-center">
            <strong class="display-6"><?= e((string) $stats[$key]) ?></strong>
            <div class="text-secondary small"><?= e($label) ?></div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="row g-4 mt-3">
      <div class="col-lg-7">
        <div class="profile-section h-100">
          <h2 class="h5">Next steps</h2>
          <div class="border-top py-2">1. Open <a href="setup.php">Teacher details</a> and complete your profile.</div>
          <div class="border-top py-2">2. Add the subjects and class levels you teach.</div>
          <div class="border-top py-2">3. Add weekly availability so booking time slots work.</div>
          <div class="border-top py-2">4. Approve booking requests from the requests page.</div>
        </div>
      </div>
      <div class="col-lg-5">
        <div class="profile-section h-100">
          <h2 class="h5">Quick links</h2>
          <a class="btn btn-brand w-100 mb-2" href="setup.php">Teacher details</a>
          <a class="btn btn-outline-brand w-100 mb-2" href="requests.php">Manage requests</a>
          <a class="btn btn-outline-brand w-100" href="../schedule.php">Schedule</a>
        </div>
      </div>
    </div>
  </div>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
