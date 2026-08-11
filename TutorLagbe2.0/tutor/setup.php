<?php
require_once __DIR__ . '/../includes/tutor_auth_check.php';
require_once __DIR__ . '/../config/db.php';

$pdo = db();
$message = null;
$error = null;
$tutor = [
    'id' => 0,
    'bio' => '',
    'education' => '',
    'qualification' => '',
    'experience_years' => 0,
    'hourly_rate' => '',
    'medium' => 'Both',
    'gender' => '',
    'location' => '',
    'cover_photo' => '',
    'demo_video' => '',
    'is_featured' => 0,
];
$profileImage = '';
$subjects = [];
$availability = [];
$allSubjects = [];

try {
    $q = $pdo->prepare('SELECT * FROM tutors WHERE user_id = ? LIMIT 1');
    $q->execute([$_SESSION['user_id']]);
    if ($row = $q->fetch()) {
        $tutor = array_merge($tutor, $row);
    }

    $q = $pdo->prepare('SELECT profile_picture FROM users WHERE id = ? LIMIT 1');
    $q->execute([$_SESSION['user_id']]);
    $profileImage = (string) ($q->fetchColumn() ?: '');

    $q = $pdo->query('SELECT id, name FROM subjects ORDER BY name');
    $allSubjects = $q->fetchAll();

    if ($tutor['id']) {
        $q = $pdo->prepare(
            'SELECT ts.id, ts.subject_id, ts.class_level, s.name
             FROM tutor_subjects ts
             JOIN subjects s ON s.id = ts.subject_id
             WHERE ts.tutor_id = ?
             ORDER BY s.name, ts.class_level'
        );
        $q->execute([$tutor['id']]);
        $subjects = $q->fetchAll();

        $q = $pdo->prepare('SELECT * FROM availability WHERE tutor_id = ? ORDER BY FIELD(day_of_week,"Sat","Sun","Mon","Tue","Wed","Thu","Fri"), start_time');
        $q->execute([$tutor['id']]);
        $availability = $q->fetchAll();
    }
} catch (Throwable $e) {
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST['csrf_token'] ?? null)) {
    $action = (string) ($_POST['action'] ?? '');
    try {
        $pdo->beginTransaction();

        if ($action === 'save_profile') {
            $profilePicturePath = $profileImage;
            if (!empty($_FILES['profile_picture']['name'] ?? '')) {
                $file = $_FILES['profile_picture'];
                if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK && (int) ($file['size'] ?? 0) > 0) {
                    if ((int) $file['size'] > 5 * 1024 * 1024) {
                        throw new InvalidArgumentException('Profile image must be 5 MB or smaller.');
                    }
                    $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
                    $allowedImageExt = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
                    if (!in_array($ext, $allowedImageExt, true)) {
                        throw new InvalidArgumentException('Upload a profile image in JPG, PNG, WebP, or GIF format.');
                    }
                    $dir = __DIR__ . '/../assets/uploads/tutor-images/';
                    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
                        throw new RuntimeException('Could not create the upload folder for profile images.');
                    }
                    $name = 'profile-' . $_SESSION['user_id'] . '-' . bin2hex(random_bytes(8)) . '.' . $ext;
                    $target = $dir . $name;
                    if (!move_uploaded_file((string) $file['tmp_name'], $target)) {
                        throw new RuntimeException('We could not save the profile image. Please try again.');
                    }
                    $profilePicturePath = 'assets/uploads/tutor-images/' . $name;
                }
            }

            $coverPhotoPath = (string) $tutor['cover_photo'];
            if (!empty($_FILES['cover_photo_file']['name'] ?? '')) {
                $file = $_FILES['cover_photo_file'];
                if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK && (int) ($file['size'] ?? 0) > 0) {
                    if ((int) $file['size'] > 8 * 1024 * 1024) {
                        throw new InvalidArgumentException('Cover image must be 8 MB or smaller.');
                    }
                    $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
                    $allowedImageExt = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
                    if (!in_array($ext, $allowedImageExt, true)) {
                        throw new InvalidArgumentException('Upload a cover image in JPG, PNG, WebP, or GIF format.');
                    }
                    $dir = __DIR__ . '/../assets/uploads/tutor-covers/';
                    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
                        throw new RuntimeException('Could not create the upload folder for cover images.');
                    }
                    $name = 'cover-' . $_SESSION['user_id'] . '-' . bin2hex(random_bytes(8)) . '.' . $ext;
                    $target = $dir . $name;
                    if (!move_uploaded_file((string) $file['tmp_name'], $target)) {
                        throw new RuntimeException('We could not save the cover image. Please try again.');
                    }
                    $coverPhotoPath = 'assets/uploads/tutor-covers/' . $name;
                }
            }

            $videoPath = (string) $tutor['demo_video'];
            if (!empty($_FILES['demo_video']['name'] ?? '')) {
                $file = $_FILES['demo_video'];
                if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK && (int) ($file['size'] ?? 0) > 0) {
                    if ((int) $file['size'] > 50 * 1024 * 1024) {
                        throw new InvalidArgumentException('Demo video must be 50 MB or smaller.');
                    }
                    $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
                    $allowedVideoExt = ['mp4', 'webm', 'ogg', 'mov'];
                    if (!in_array($ext, $allowedVideoExt, true)) {
                        throw new InvalidArgumentException('Upload a demo video in MP4, WebM, OGG, or MOV format.');
                    }
                    $dir = __DIR__ . '/../assets/uploads/tutor-videos/';
                    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
                        throw new RuntimeException('Could not create the upload folder for demo videos.');
                    }
                    $name = 'demo-' . $_SESSION['user_id'] . '-' . bin2hex(random_bytes(8)) . '.' . $ext;
                    $target = $dir . $name;
                    if (!move_uploaded_file((string) $file['tmp_name'], $target)) {
                        throw new RuntimeException('We could not save the demo video. Please try again.');
                    }
                    $videoPath = 'assets/uploads/tutor-videos/' . $name;
                }
            }

            $data = [
                'bio' => trim((string) ($_POST['bio'] ?? '')),
                'education' => trim((string) ($_POST['education'] ?? '')),
                'qualification' => trim((string) ($_POST['qualification'] ?? '')),
                'experience_years' => max(0, (int) ($_POST['experience_years'] ?? 0)),
                'hourly_rate' => max(0, (float) ($_POST['hourly_rate'] ?? 0)),
                'medium' => in_array($_POST['medium'] ?? 'Both', tutor_medium_options(), true) ? $_POST['medium'] : 'Both',
                'gender' => in_array($_POST['gender'] ?? '', tutor_gender_options(), true) ? $_POST['gender'] : null,
                'location' => in_array(trim((string) ($_POST['location'] ?? '')), shared_address_options(), true) ? trim((string) ($_POST['location'] ?? '')) : '',
                'cover_photo' => $coverPhotoPath,
                'demo_video' => $videoPath,
            ];

            if (!$tutor['id']) {
                $q = $pdo->prepare(
                    'INSERT INTO tutors (user_id, bio, education, qualification, experience_years, hourly_rate, medium, gender, location, cover_photo, demo_video)
                     VALUES (:user_id, :bio, :education, :qualification, :experience_years, :hourly_rate, :medium, :gender, :location, :cover_photo, :demo_video)'
                );
                $q->execute([
                    'user_id' => $_SESSION['user_id'],
                    'bio' => $data['bio'],
                    'education' => $data['education'],
                    'qualification' => $data['qualification'],
                    'experience_years' => $data['experience_years'],
                    'hourly_rate' => $data['hourly_rate'],
                    'medium' => $data['medium'],
                    'gender' => $data['gender'],
                    'location' => $data['location'],
                    'cover_photo' => $data['cover_photo'],
                    'demo_video' => $data['demo_video'],
                ]);
                $q = $pdo->prepare('UPDATE users SET profile_picture = ? WHERE id = ?');
                $q->execute([$profilePicturePath, $_SESSION['user_id']]);
                $tutor['id'] = (int) $pdo->lastInsertId();
            } else {
                $q = $pdo->prepare(
                    'UPDATE tutors
                     SET bio = :bio, education = :education, qualification = :qualification,
                         experience_years = :experience_years, hourly_rate = :hourly_rate,
                         medium = :medium, gender = :gender, location = :location, cover_photo = :cover_photo, demo_video = :demo_video
                     WHERE id = :id AND user_id = :user_id'
                );
                $q->execute($data + ['id' => $tutor['id'], 'user_id' => $_SESSION['user_id']]);
                $q = $pdo->prepare('UPDATE users SET profile_picture = ? WHERE id = ?');
                $q->execute([$profilePicturePath, $_SESSION['user_id']]);
            }
            $message = 'Tutor profile saved.';
        } elseif ($action === 'add_subject' && $tutor['id']) {
            $subjectId = (int) ($_POST['subject_id'] ?? 0);
            $classLevel = trim((string) ($_POST['class_level'] ?? ''));
            if ($subjectId < 1 || $classLevel === '') {
                throw new InvalidArgumentException('Choose a subject and class level.');
            }
            $q = $pdo->prepare(
                'INSERT INTO tutor_subjects (tutor_id, subject_id, class_level)
                 VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE subject_id = VALUES(subject_id)'
            );
            $q->execute([$tutor['id'], $subjectId, $classLevel]);
            $message = 'Subject added.';
        } elseif ($action === 'delete_subject' && $tutor['id']) {
            $q = $pdo->prepare('DELETE FROM tutor_subjects WHERE id = ? AND tutor_id = ?');
            $q->execute([(int) ($_POST['subject_row_id'] ?? 0), $tutor['id']]);
            $message = 'Subject removed.';
        } elseif ($action === 'add_slot' && $tutor['id']) {
            $day = (string) ($_POST['day_of_week'] ?? '');
            $start = (string) ($_POST['start_time'] ?? '');
            $end = (string) ($_POST['end_time'] ?? '');
            if (!in_array($day, ['Sat','Sun','Mon','Tue','Wed','Thu','Fri'], true) || $start === '' || $end === '') {
                throw new InvalidArgumentException('Choose a valid day and time range.');
            }
            $q = $pdo->prepare(
                'INSERT INTO availability (tutor_id, day_of_week, start_time, end_time, is_recurring)
                 VALUES (?, ?, ?, ?, 1)'
            );
            $q->execute([$tutor['id'], $day, $start, $end]);
            $message = 'Availability slot added.';
        } elseif ($action === 'delete_slot' && $tutor['id']) {
            $q = $pdo->prepare('DELETE FROM availability WHERE id = ? AND tutor_id = ?');
            $q->execute([(int) ($_POST['slot_id'] ?? 0), $tutor['id']]);
            $message = 'Availability slot removed.';
        } else {
            throw new InvalidArgumentException('Unknown action.');
        }

        $pdo->commit();
        header('Location: ' . base_url('tutor/setup.php'));
        exit;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = $e->getMessage() ?: 'Unable to save tutor details.';
    }
}

$pageTitle = 'Tutor details';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/navbar.php';
?>
<main class="container py-5" style="max-width: 1080px">
  <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
    <div>
      <h1 class="h2 mb-1">Teacher details</h1>
      <p class="text-secondary mb-0">Set what you teach, how much you charge, and when you’re available.</p>
    </div>
    <a class="btn btn-outline-brand" href="../tutor/dashboard.php">Back to dashboard</a>
  </div>

  <?php if ($message): ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

  <div class="row g-4">
    <div class="col-lg-6">
      <form method="post" class="profile-section" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="save_profile">
        <h2 class="h5">Profile info</h2>
        <div class="mb-3">
          <label class="form-label">Bio</label>
          <textarea class="form-control" name="bio" rows="4" placeholder="Tell students about your teaching style"><?= e((string) $tutor['bio']) ?></textarea>
        </div>
        <div class="mb-3">
          <label class="form-label">Education</label>
          <input class="form-control" name="education" value="<?= e((string) $tutor['education']) ?>" placeholder="Your degree or institution">
        </div>
        <div class="mb-3">
          <label class="form-label">Qualification</label>
          <textarea class="form-control" name="qualification" rows="3" placeholder="Certificates, training, experience highlights"><?= e((string) $tutor['qualification']) ?></textarea>
        </div>
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label">Experience years</label>
            <input class="form-control" type="number" min="0" name="experience_years" value="<?= e((string) $tutor['experience_years']) ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label">Hourly rate</label>
            <input class="form-control" type="number" min="0" step="50" name="hourly_rate" value="<?= e((string) $tutor['hourly_rate']) ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label">Medium</label>
            <select class="form-select" name="medium">
              <?php foreach (tutor_medium_options() as $item): ?>
                <option value="<?= e($item) ?>" <?= $tutor['medium'] === $item ? 'selected' : '' ?>><?= e($item) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Gender</label>
            <select class="form-select" name="gender">
              <option value="">Not listed</option>
              <?php foreach (tutor_gender_options() as $item): ?>
                <option value="<?= e($item) ?>" <?= $tutor['gender'] === $item ? 'selected' : '' ?>><?= e($item) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-8">
            <label class="form-label">Location</label>
            <select class="form-select" name="location">
              <option value="">Select address</option>
              <?php foreach (shared_address_options() as $address): ?>
                <option value="<?= e($address) ?>" <?= (string) $tutor['location'] === $address ? 'selected' : '' ?>><?= e($address) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-12">
            <label class="form-label">Profile image</label>
            <input class="form-control" type="file" name="profile_picture" accept="image/jpeg,image/png,image/webp,image/gif">
            <div class="form-text">Saved locally and shown on your tutor profile.</div>
            <?php if (!empty($profileImage)): ?>
              <div class="mt-2 small text-secondary">Current image: <?= e($profileImage) ?></div>
            <?php endif; ?>
          </div>
          <div class="col-12">
            <label class="form-label">Cover image</label>
            <input class="form-control" type="file" name="cover_photo_file" accept="image/jpeg,image/png,image/webp,image/gif">
            <div class="form-text">Upload a local cover image for the top banner.</div>
            <?php if (!empty($tutor['cover_photo'])): ?>
              <div class="mt-2 small text-secondary">Current cover: <?= e((string) $tutor['cover_photo']) ?></div>
            <?php endif; ?>
          </div>
          <div class="col-12">
            <label class="form-label">Demo video</label>
            <input class="form-control" type="file" name="demo_video" accept="video/mp4,video/webm,video/ogg,video/quicktime">
            <div class="form-text">Upload a short local demo video for your profile.</div>
            <?php if (!empty($tutor['demo_video'])): ?>
              <div class="mt-2 small text-secondary">Current video: <?= e((string) $tutor['demo_video']) ?></div>
            <?php endif; ?>
          </div>
        </div>
        <button class="btn btn-brand mt-4">Save teacher details</button>
      </form>
    </div>

    <div class="col-lg-6">
      <div class="profile-section mb-4">
        <h2 class="h5">What you teach</h2>
        <form method="post" class="row g-2 mb-3">
          <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="action" value="add_subject">
          <div class="col-md-6">
            <select class="form-select" name="subject_id" required>
              <option value="">Select subject</option>
              <?php foreach ($allSubjects as $subject): ?>
                <option value="<?= (int) $subject['id'] ?>"><?= e($subject['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4">
            <select class="form-select" name="class_level" required>
              <option value="">Class / level</option>
              <?php foreach (tutor_class_levels() as $level): ?>
                <option value="<?= e($level) ?>"><?= e($level) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-2 d-grid">
            <button class="btn btn-outline-brand">Add</button>
          </div>
        </form>

        <?php foreach ($subjects as $subject): ?>
          <div class="border-top py-2 d-flex justify-content-between align-items-center">
            <div>
              <strong><?= e($subject['name']) ?></strong>
              <div class="text-secondary small"><?= e($subject['class_level']) ?></div>
            </div>
            <form method="post">
              <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="action" value="delete_subject">
              <input type="hidden" name="subject_row_id" value="<?= (int) $subject['id'] ?>">
              <button class="btn btn-sm btn-link text-danger">Remove</button>
            </form>
          </div>
        <?php endforeach; ?>
        <?php if (!$subjects): ?>
          <p class="text-secondary mb-0">Add the subjects and class levels you teach.</p>
        <?php endif; ?>
      </div>

      <div class="profile-section">
        <h2 class="h5">Availability</h2>
        <form method="post" class="row g-2 mb-3">
          <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="action" value="add_slot">
          <div class="col-md-4">
            <select class="form-select" name="day_of_week">
              <?php foreach (['Sat','Sun','Mon','Tue','Wed','Thu','Fri'] as $d): ?>
                <option value="<?= e($d) ?>"><?= e($d) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-3">
            <input class="form-control" type="time" name="start_time" required>
          </div>
          <div class="col-md-3">
            <input class="form-control" type="time" name="end_time" required>
          </div>
          <div class="col-md-2 d-grid">
            <button class="btn btn-outline-brand">Add</button>
          </div>
        </form>

        <?php foreach ($availability as $slot): ?>
          <div class="border-top py-2 d-flex justify-content-between align-items-center">
            <div><?= e($slot['day_of_week']) ?> <?= e(substr((string) $slot['start_time'], 0, 5)) ?> - <?= e(substr((string) $slot['end_time'], 0, 5)) ?></div>
            <form method="post">
              <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="action" value="delete_slot">
              <input type="hidden" name="slot_id" value="<?= (int) $slot['id'] ?>">
              <button class="btn btn-sm btn-link text-danger">Remove</button>
            </form>
          </div>
        <?php endforeach; ?>
        <?php if (!$availability): ?>
          <p class="text-secondary mb-0">Add your weekly available slots here.</p>
        <?php endif; ?>
      </div>
    </div>
  </div>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
