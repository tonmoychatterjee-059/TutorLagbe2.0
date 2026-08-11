<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../config/db.php';

$errors = [];
$values = [
    'full_name' => '',
    'email' => '',
    'phone' => '',
    'address' => '',
    'role' => $_GET['role'] ?? 'student',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($values as $key => $value) {
        $values[$key] = trim((string) ($_POST[$key] ?? ''));
    }

    $password = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['confirm_password'] ?? '');

    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Your session expired. Please try again.';
    }
    if (mb_strlen($values['full_name']) < 2 || mb_strlen($values['full_name']) > 120) {
        $errors[] = 'Please enter your full name.';
    }
    if (!filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }

    $phone = preg_replace('/[\s-]/', '', $values['phone']);
    if (!preg_match('/^(?:\+?8801|01)\d{9}$/', $phone)) {
        $errors[] = 'Enter a valid Bangladeshi mobile number.';
    }
    if (!in_array($values['role'], ['student', 'tutor'], true)) {
        $errors[] = 'Please select a valid account type.';
    }
    if (!in_array($values['address'], shared_address_options(), true)) {
        $errors[] = 'Please choose a valid address.';
    }
    if (strlen($password) < 8) {
        $errors[] = 'Password must have at least 8 characters.';
    }
    if ($password !== $confirm) {
        $errors[] = 'Passwords do not match.';
    }

    if (!$errors) {
        try {
            $pdo = db();
            $check = $pdo->prepare('SELECT id FROM users WHERE email = :email OR phone = :phone LIMIT 1');
            $check->execute(['email' => $values['email'], 'phone' => $phone]);

            if ($check->fetch()) {
                $errors[] = 'An account already uses that email or phone number.';
            } else {
                // Students can learn immediately; tutor accounts require a manual review.
                $verified = $values['role'] === 'student' ? 1 : 0;
                $insert = $pdo->prepare(
                    'INSERT INTO users (full_name, email, phone, address, password, role, is_verified)
                     VALUES (:full_name, :email, :phone, :address, :password, :role, :is_verified)'
                );
                $insert->execute([
                    'full_name' => $values['full_name'],
                    'email' => $values['email'],
                    'phone' => $phone,
                    'address' => $values['address'],
                    'password' => password_hash($password, PASSWORD_DEFAULT),
                    'role' => $values['role'],
                    'is_verified' => $verified,
                ]);

                flash('success', $values['role'] === 'tutor'
                    ? "Your account has been created and is pending admin approval. You'll be notified once approved."
                    : 'Registration successful. Please log in.');
                header('Location: login.php');
                exit;
            }
        } catch (Throwable $e) {
            $errors[] = 'We could not create your account right now. Please ensure the database has been imported.';
        }
    }
}

$pageTitle = 'Create your account';
require __DIR__ . '/../includes/header.php';
?>
<main class="auth-page">
  <section class="auth-card">
    <a class="brand d-block text-center mb-4" href="<?= base_url('index.php') ?>">
      <span class="brand-mark">T</span>TutorLagbe
    </a>
    <h1 class="h3 text-center">Create your account</h1>
    <p class="text-secondary text-center mb-4">Start learning or teaching today.</p>

    <?php if ($errors): ?>
      <div class="alert alert-danger">
        <ul class="mb-0">
          <?php foreach ($errors as $error): ?>
            <li><?= e($error) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <form method="post" novalidate data-validate-register>
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

      <div class="role-pills d-flex mb-4">
        <input id="student" type="radio" name="role" value="student" <?= $values['role'] === 'student' ? 'checked' : '' ?>>
        <label for="student">I’m a Student</label>
        <input id="tutor" type="radio" name="role" value="tutor" <?= $values['role'] === 'tutor' ? 'checked' : '' ?>>
        <label for="tutor">I’m a Tutor</label>
      </div>

      <div class="mb-3">
        <label class="form-label">Full name</label>
        <input class="form-control" name="full_name" value="<?= e($values['full_name']) ?>" required maxlength="120" autocomplete="name">
      </div>

      <div class="mb-3">
        <label class="form-label">Email address</label>
        <input class="form-control" type="email" name="email" value="<?= e($values['email']) ?>" required autocomplete="email">
      </div>

      <div class="mb-3">
        <label class="form-label">Mobile number</label>
        <input class="form-control" type="tel" name="phone" value="<?= e($values['phone']) ?>" placeholder="01XXXXXXXXX" required autocomplete="tel">
      </div>

      <div class="mb-3">
        <label class="form-label">Address</label>
        <select class="form-select" name="address" required>
          <option value="">Select address</option>
          <?php foreach (shared_address_options() as $address): ?>
            <option value="<?= e($address) ?>" <?= $values['address'] === $address ? 'selected' : '' ?>><?= e($address) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="mb-3">
        <label class="form-label">Password</label>
        <input class="form-control" type="password" name="password" required minlength="8" autocomplete="new-password">
      </div>

      <div class="mb-4">
        <label class="form-label">Confirm password</label>
        <input class="form-control" type="password" name="confirm_password" required minlength="8" autocomplete="new-password">
      </div>

      <button class="btn btn-brand w-100 py-2" type="submit">Create account</button>
    </form>

    <p class="text-center text-secondary small mt-4 mb-0">
      Already have an account? <a class="text-success fw-semibold" href="login.php">Log in</a>
    </p>
  </section>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
