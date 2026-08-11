<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/config/db.php';

function query_int(string $key, int $minimum = 0, int $maximum = PHP_INT_MAX): ?int
{
    $value = filter_input(INPUT_GET, $key, FILTER_VALIDATE_INT);
    return $value !== false && $value !== null && $value >= $minimum && $value <= $maximum ? $value : null;
}

$filters = [
    'subject_id' => query_int('subject_id', 1),
    'class_level' => trim((string) ($_GET['class_level'] ?? '')),
    'medium' => (string) ($_GET['medium'] ?? ''),
    'gender' => (string) ($_GET['gender'] ?? ''),
    'location' => trim((string) ($_GET['location'] ?? '')),
    'min_rate' => query_int('min_rate', 0, 100000),
    'max_rate' => query_int('max_rate', 0, 100000),
    'experience' => query_int('experience', 0, 80),
];

$filters['class_level'] = mb_substr($filters['class_level'], 0, 100);
$filters['location'] = mb_substr($filters['location'], 0, 150);

if (!in_array($filters['medium'], tutor_medium_options(), true)) {
    $filters['medium'] = '';
}
if (!in_array($filters['gender'], tutor_gender_options(), true)) {
    $filters['gender'] = '';
}
if (!in_array($filters['class_level'], tutor_class_levels(), true)) {
    $filters['class_level'] = '';
}
if (!in_array($filters['location'], shared_address_options(), true)) {
    $filters['location'] = '';
}
if ($filters['min_rate'] !== null && $filters['max_rate'] !== null && $filters['min_rate'] > $filters['max_rate']) {
    [$filters['min_rate'], $filters['max_rate']] = [$filters['max_rate'], $filters['min_rate']];
}

$page = query_int('page', 1, 100000) ?? 1;
$perPage = 12;
$subjects = [];
$tutors = [];
$total = 0;
$pages = 1;
$databaseError = false;

try {
    $pdo = db();
    $subjects = $pdo->query('SELECT id, name FROM subjects ORDER BY name')->fetchAll();

    $where = ['u.role = :tutor_role', 'u.is_verified = 1', 'u.is_suspended = 0'];
    $params = ['tutor_role' => 'tutor'];

    if ($filters['subject_id'] !== null) {
        $where[] = 'EXISTS (SELECT 1 FROM tutor_subjects tsf WHERE tsf.tutor_id = t.id AND tsf.subject_id = :subject_id)';
        $params['subject_id'] = $filters['subject_id'];
    }
    if ($filters['class_level'] !== '') {
        $where[] = 'EXISTS (SELECT 1 FROM tutor_subjects tsc WHERE tsc.tutor_id = t.id AND tsc.class_level = :class_level)';
        $params['class_level'] = $filters['class_level'];
    }
    if ($filters['medium'] !== '') {
        $where[] = 't.medium = :medium';
        $params['medium'] = $filters['medium'];
    }
    if ($filters['gender'] !== '') {
        $where[] = 't.gender = :gender';
        $params['gender'] = $filters['gender'];
    }
    if ($filters['location'] !== '') {
        $where[] = 't.location = :location';
        $params['location'] = $filters['location'];
    }
    if ($filters['min_rate'] !== null) {
        $where[] = 't.hourly_rate >= :min_rate';
        $params['min_rate'] = $filters['min_rate'];
    }
    if ($filters['max_rate'] !== null) {
        $where[] = 't.hourly_rate <= :max_rate';
        $params['max_rate'] = $filters['max_rate'];
    }
    if ($filters['experience'] !== null) {
        $where[] = 't.experience_years >= :experience';
        $params['experience'] = $filters['experience'];
    }

    $whereSql = implode(' AND ', $where);

    $count = $pdo->prepare("SELECT COUNT(*) FROM tutors t JOIN users u ON u.id = t.user_id WHERE $whereSql");
    $count->execute($params);
    $total = (int) $count->fetchColumn();
    $pages = max(1, (int) ceil($total / $perPage));
    $page = min($page, $pages);
    $offset = ($page - 1) * $perPage;

    $sql = "
        SELECT
            t.id,
            t.rating,
            t.hourly_rate,
            t.experience_years,
            t.location,
            u.full_name,
            u.profile_picture,
            GROUP_CONCAT(DISTINCT s.name ORDER BY s.name SEPARATOR '|') AS subjects
        FROM tutors t
        JOIN users u ON u.id = t.user_id
        LEFT JOIN tutor_subjects ts ON ts.tutor_id = t.id
        LEFT JOIN subjects s ON s.id = ts.subject_id
        WHERE $whereSql
        GROUP BY t.id
        ORDER BY t.rating DESC, t.id DESC
        LIMIT :limit OFFSET :offset
    ";
    $statement = $pdo->prepare($sql);
    foreach ($params as $name => $value) {
        $statement->bindValue(':' . $name, $value);
    }
    $statement->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
    $statement->execute();
    $tutors = $statement->fetchAll();
} catch (Throwable $exception) {
    $databaseError = true;
    $pages = 1;
}

function filter_url(array $changes = []): string
{
    global $filters, $page;
    $query = array_filter(
        array_merge($filters, ['page' => $page], $changes),
        static fn($value) => $value !== '' && $value !== null && $value !== 0
    );

    return 'find-tutor.php?' . http_build_query($query);
}

$pageTitle = 'Find a Tutor';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/navbar.php';
?>
<main class="section-soft py-5 min-vh-100">
  <div class="container">
    <div class="mb-4">
      <p class="eyebrow mb-1">Tutor directory</p>
      <h1 class="section-title mb-1">Find your ideal tutor</h1>
      <p class="text-secondary mb-0">Compare verified tutors by subject, location, and budget.</p>
    </div>

    <?php if ($databaseError): ?>
      <div class="alert alert-warning">Tutor listings are temporarily unavailable. Please import the latest database schema.</div>
    <?php endif; ?>

    <div class="row g-4">
      <aside class="col-lg-3">
        <button class="btn btn-outline-brand w-100 d-lg-none mb-3" data-bs-toggle="collapse" data-bs-target="#tutorFilters">Show filters</button>
        <div class="collapse d-lg-block" id="tutorFilters">
          <form class="filter-panel" method="get">
            <div class="mb-3">
              <label class="form-label fw-semibold" for="subject_id">Subject</label>
              <select class="form-select" id="subject_id" name="subject_id">
                <option value="">All subjects</option>
                <?php foreach ($subjects as $subject): ?>
                  <option value="<?= (int) $subject['id'] ?>" <?= $filters['subject_id'] === (int) $subject['id'] ? 'selected' : '' ?>><?= e($subject['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="mb-3">
              <label class="form-label fw-semibold" for="class_level">Class / level</label>
              <select class="form-select" id="class_level" name="class_level">
                <option value="">Any level</option>
                <?php foreach (tutor_class_levels() as $level): ?>
                  <option value="<?= e($level) ?>" <?= $filters['class_level'] === $level ? 'selected' : '' ?>><?= e($level) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="mb-3">
              <label class="form-label fw-semibold">Medium</label>
              <select class="form-select" name="medium">
                <option value="">Any medium</option>
                <?php foreach (tutor_medium_options() as $item): ?>
                  <option value="<?= e($item) ?>" <?= $filters['medium'] === $item ? 'selected' : '' ?>><?= e($item) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="mb-3">
              <label class="form-label fw-semibold">Gender</label>
              <select class="form-select" name="gender">
                <option value="">Any gender</option>
                <?php foreach (tutor_gender_options() as $item): ?>
                  <option value="<?= e($item) ?>" <?= $filters['gender'] === $item ? 'selected' : '' ?>><?= e($item) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="mb-3">
              <label class="form-label fw-semibold">Address</label>
              <select class="form-select" name="location">
                <option value="">Any address</option>
                <?php foreach (shared_address_options() as $address): ?>
                  <option value="<?= e($address) ?>" <?= $filters['location'] === $address ? 'selected' : '' ?>><?= e($address) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="mb-3">
              <label class="form-label fw-semibold d-flex justify-content-between">
                <span>Budget (৳/hour)</span>
                <span id="budgetValue"></span>
              </label>
              <input class="form-range" type="range" name="min_rate" min="0" max="10000" step="100" value="<?= $filters['min_rate'] ?? 0 ?>" data-budget="min">
              <input class="form-range" type="range" name="max_rate" min="0" max="10000" step="100" value="<?= $filters['max_rate'] ?? 10000 ?>" data-budget="max">
            </div>

            <div class="mb-4">
              <label class="form-label fw-semibold">Minimum experience</label>
              <select class="form-select" name="experience">
                <option value="">Any experience</option>
                <?php foreach ([1, 2, 3, 5, 8, 10] as $years): ?>
                  <option value="<?= $years ?>" <?= $filters['experience'] === $years ? 'selected' : '' ?>><?= $years ?>+ years</option>
                <?php endforeach; ?>
              </select>
            </div>

            <button class="btn btn-brand w-100">Apply filters</button>
            <a class="btn btn-link text-success w-100 mt-2" href="find-tutor.php">Clear all</a>
          </form>
        </div>
      </aside>

      <section class="col-lg-9">
        <p class="small text-secondary mb-3"><?= $total ?> verified tutor<?= $total === 1 ? '' : 's' ?> found</p>
        <div class="row g-4">
          <?php foreach ($tutors as $tutor): ?>
            <?php $tags = array_filter(explode('|', (string) $tutor['subjects'])); ?>
            <div class="col-md-6 col-xl-4">
              <article class="tutor-card directory-card">
                <img src="<?= e($tutor['profile_picture'] ?: base_url('assets/images/no-image.svg')) ?>" alt="<?= e($tutor['full_name']) ?>">
                <div class="p-4">
                  <div class="d-flex justify-content-between gap-2">
                    <h2 class="h5 mb-1"><?= e($tutor['full_name']) ?></h2>
                    <span class="rating text-nowrap">★ <?= number_format((float) $tutor['rating'], 1) ?></span>
                  </div>
                  <p class="small text-secondary mb-2">📍 <?= e($tutor['location'] ?: 'Location not listed') ?></p>
                  <div class="mb-3">
                    <?php foreach (array_slice($tags, 0, 3) as $tag): ?>
                      <span class="badge subject-badge"><?= e($tag) ?></span>
                    <?php endforeach; ?>
                  </div>
                  <div class="d-flex justify-content-between align-items-center border-top pt-3">
                    <span>
                      <strong>৳<?= number_format((float) $tutor['hourly_rate']) ?></strong>
                      <small class="text-secondary"> / hour</small>
                      <small class="d-block text-secondary"><?= (int) $tutor['experience_years'] ?> yrs experience</small>
                    </span>
                    <a class="btn btn-outline-brand btn-sm" href="tutor-profile.php?id=<?= (int) $tutor['id'] ?>">View profile</a>
                  </div>
                </div>
              </article>
            </div>
          <?php endforeach; ?>
        </div>

        <?php if (!$tutors && !$databaseError): ?>
          <div class="empty-state text-center">
            <div class="display-5">🔎</div>
            <h2 class="h4 mt-3">No tutors match those filters</h2>
            <p class="text-secondary">Try widening your budget, address, or experience criteria.</p>
            <a class="btn btn-brand" href="find-tutor.php">Browse all tutors</a>
          </div>
        <?php endif; ?>

        <?php if ($total > $perPage): ?>
          <nav class="mt-5">
            <ul class="pagination justify-content-center">
              <?php for ($number = max(1, $page - 2); $number <= min($pages, $page + 2); $number++): ?>
                <li class="page-item <?= $number === $page ? 'active' : '' ?>">
                  <a class="page-link" href="<?= e(filter_url(['page' => $number])) ?>"><?= $number ?></a>
                </li>
              <?php endfor; ?>
            </ul>
          </nav>
        <?php endif; ?>
      </section>
    </div>
  </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const min = document.querySelector('[data-budget="min"]');
  const max = document.querySelector('[data-budget="max"]');
  const label = document.querySelector('#budgetValue');

  function update() {
    if (+min.value > +max.value) {
      [min.value, max.value] = [max.value, min.value];
    }
    label.textContent = `৳${min.value} – ৳${max.value}`;
  }

  min?.addEventListener('input', update);
  max?.addEventListener('input', update);
  update();
});
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
