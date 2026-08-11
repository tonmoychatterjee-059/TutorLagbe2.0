<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/config/db.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$tutor = null;
$subjects = [];
$slots = [];
$reviews = [];
$count = 0;
$reviewPage = max(1, filter_input(INPUT_GET, 'reviews_page', FILTER_VALIDATE_INT) ?: 1);

if ($id) {
    try {
        $pdo = db();
        $q = $pdo->prepare(
            "SELECT t.*, u.full_name, u.profile_picture
             FROM tutors t
             JOIN users u ON u.id = t.user_id
             WHERE t.id = :id
               AND u.role = 'tutor'
               AND u.is_verified = 1
               AND u.is_suspended = 0"
        );
        $q->execute(['id' => $id]);
        $tutor = $q->fetch();

        if ($tutor) {
            $q = $pdo->prepare(
                'SELECT s.name, ts.class_level
                 FROM tutor_subjects ts
                 JOIN subjects s ON s.id = ts.subject_id
                 WHERE ts.tutor_id = :id
                 ORDER BY s.name'
            );
            $q->execute(['id' => $id]);
            $subjects = $q->fetchAll();

            $q = $pdo->prepare(
                'SELECT day_of_week, start_time, end_time
                 FROM availability
                 WHERE tutor_id = :id
                 ORDER BY FIELD(day_of_week, "Sat", "Sun", "Mon", "Tue", "Wed", "Thu", "Fri"), start_time'
            );
            $q->execute(['id' => $id]);
            $slots = $q->fetchAll();

            $q = $pdo->prepare('SELECT COUNT(*) FROM reviews WHERE tutor_id = :id');
            $q->execute(['id' => $id]);
            $count = (int) $q->fetchColumn();

            $q = $pdo->prepare(
                'SELECT r.rating, r.comment, r.created_at, u.full_name
                 FROM reviews r
                 JOIN users u ON u.id = r.student_id
                 WHERE r.tutor_id = :id
                 ORDER BY r.created_at DESC
                 LIMIT 5 OFFSET :offset'
            );
            $q->bindValue(':id', $id, PDO::PARAM_INT);
            $q->bindValue(':offset', ($reviewPage - 1) * 5, PDO::PARAM_INT);
            $q->execute();
            $reviews = $q->fetchAll();
        }
    } catch (Throwable $e) {
        $tutor = null;
    }
}

if (!$tutor) {
    http_response_code(404);
    $pageTitle = 'Tutor not found';
    require __DIR__ . '/includes/header.php';
    require __DIR__ . '/includes/navbar.php';
    echo '<main class="container py-5 text-center"><h1 class="h3">Tutor not found</h1><a class="btn btn-brand" href="find-tutor.php">Browse tutors</a></main>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$days = ['Sat', 'Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri'];
$weekly = array_fill_keys($days, []);
foreach ($slots as $slot) {
    if (isset($weekly[$slot['day_of_week']])) {
        $weekly[$slot['day_of_week']][] = $slot;
    }
}

$book = !empty($_SESSION['user_id'])
    ? 'booking.php?tutor_id=' . $id
    : 'auth/login.php?return_to=' . rawurlencode('tutor-profile.php?id=' . $id);

$pageTitle = $tutor['full_name'];
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/navbar.php';
?>
<main class="pb-5">
  <section class="profile-cover"<?= $tutor['cover_photo'] ? ' style="background-image:url(\'' . e($tutor['cover_photo']) . '\')"' : '' ?>></section>

  <div class="container profile-content">
    <div class="row g-4">
      <div class="col-lg-8">
        <section class="profile-heading">
          <img class="profile-avatar" src="<?= e($tutor['profile_picture'] ?: base_url('assets/images/no-image.svg')) ?>" alt="<?= e($tutor['full_name']) ?>">
          <div class="pt-3">
            <h1 class="mb-1"><?= e($tutor['full_name']) ?></h1>
            <p class="rating mb-2">
              <span class="fs-5">★★★★★</span>
              <?= number_format((float) $tutor['rating'], 1) ?>
              <span class="text-secondary">(<?= $count ?> reviews)</span>
            </p>
            <p class="text-secondary mb-0">📍 <?= e($tutor['location'] ?: 'Location not listed') ?> · <?= e($tutor['medium']) ?> medium</p>
          </div>
        </section>

        <section class="profile-section mt-4">
          <h2 class="h4">Subjects taught</h2>
          <?php foreach ($subjects as $subject): ?>
            <span class="badge subject-badge fs-6 me-1 mb-2">
              <?= e($subject['name']) ?>
              <small class="fw-normal">· <?= e($subject['class_level']) ?></small>
            </span>
          <?php endforeach; ?>
          <?php if (!$subjects): ?>
            <p class="text-secondary mb-0">Subjects will be added soon.</p>
          <?php endif; ?>
        </section>

        <section class="profile-section mt-4">
          <h2 class="h4">Qualification & experience</h2>
          <p><?= nl2br(e($tutor['qualification'] ?: $tutor['education'] ?: 'Qualification details are not listed yet.')) ?></p>
          <p class="text-secondary mb-0"><strong><?= (int) $tutor['experience_years'] ?> years</strong> of teaching experience</p>
        </section>

        <?php if (!empty($tutor['demo_video'])): ?>
          <section class="profile-section mt-4">
            <h2 class="h4">Demo video</h2>
            <video class="w-100 rounded-4" controls preload="metadata" style="max-height: 420px; background:#000">
              <source src="<?= e(base_url((string) $tutor['demo_video'])) ?>">
              Your browser does not support the video tag.
            </video>
          </section>
        <?php endif; ?>

        <section class="profile-section mt-4">
          <h2 class="h4">Availability</h2>
          <div class="d-grid gap-3 mt-3">
            <?php foreach ($weekly as $day => $daySlots): ?>
              <div class="border rounded-4 p-3">
                <div class="fw-semibold mb-2"><?= $day ?></div>
                <?php if ($daySlots): ?>
                  <div class="d-flex flex-wrap gap-2">
                    <?php foreach ($daySlots as $slot): ?>
                      <a class="btn btn-outline-brand btn-sm" href="<?= e($book) ?>">
                        <?= date('g:i A', strtotime($slot['start_time'])) ?> – <?= date('g:i A', strtotime($slot['end_time'])) ?>
                      </a>
                    <?php endforeach; ?>
                  </div>
                <?php else: ?>
                  <small class="text-secondary">No slots</small>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
        </section>

        <section class="profile-section mt-4">
          <div class="d-flex justify-content-between">
            <h2 class="h4">Reviews</h2>
            <span class="small text-secondary"><?= $count ?> total</span>
          </div>

          <?php foreach ($reviews as $review): ?>
            <article class="review-item">
              <div class="d-flex justify-content-between">
                <strong><?= e($review['full_name']) ?></strong>
                <small class="text-secondary"><?= date('M j, Y', strtotime($review['created_at'])) ?></small>
              </div>
              <div class="rating">
                <?= str_repeat('★', (int) $review['rating']) ?><span class="text-muted"><?= str_repeat('★', 5 - (int) $review['rating']) ?></span>
              </div>
              <?php if ($review['comment']): ?>
                <p class="mb-0 mt-1"><?= nl2br(e($review['comment'])) ?></p>
              <?php endif; ?>
            </article>
          <?php endforeach; ?>

          <?php if (!$reviews): ?>
            <p class="text-secondary">No reviews yet.</p>
          <?php endif; ?>

          <?php if ($count > 5): ?>
            <nav class="mt-3">
              <a class="btn btn-outline-brand btn-sm <?= $reviewPage <= 1 ? 'disabled' : '' ?>" href="tutor-profile.php?id=<?= $id ?>&reviews_page=<?= $reviewPage - 1 ?>">Previous</a>
              <a class="btn btn-outline-brand btn-sm <?= $reviewPage * 5 >= $count ? 'disabled' : '' ?>" href="tutor-profile.php?id=<?= $id ?>&reviews_page=<?= $reviewPage + 1 ?>">Next</a>
            </nav>
          <?php endif; ?>
        </section>
      </div>

      <aside class="col-lg-4">
        <div class="booking-summary sticky-lg-top">
          <p class="text-secondary mb-1">Starting from</p>
          <p class="h2">৳<?= number_format((float) $tutor['hourly_rate']) ?><small class="fs-6 text-secondary fw-normal"> / hour</small></p>
          <a class="btn btn-brand w-100 py-2" href="<?= e($book) ?>">Book now</a>
        </div>
      </aside>
    </div>
  </div>
</main>

<a class="mobile-book-btn btn btn-brand" href="<?= e($book) ?>">Book now · ৳<?= number_format((float) $tutor['hourly_rate']) ?>/hr</a>
<?php require __DIR__ . '/includes/footer.php'; ?>
