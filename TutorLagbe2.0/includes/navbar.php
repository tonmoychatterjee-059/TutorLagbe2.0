<nav class="navbar navbar-expand-xl navbar-light bg-white sticky-top site-nav">
  <div class="container-fluid px-3 px-xl-4">
    <a class="navbar-brand" href="<?= base_url('index.php') ?>">
      <img class="site-logo nav-logo" src="<?= base_url('assets/images/tutorlagbe-logo.svg') ?>" alt="TutorLagbe">
    </a>

    <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="mainNav">
      <ul class="navbar-nav mx-auto align-items-xl-center gap-xl-1">
        <li class="nav-item"><a class="nav-link" href="<?= base_url('index.php') ?>">Home</a></li>

        <?php if (!empty($_SESSION['user_id'])): ?>
          <?php
          $dashboardPath = ($_SESSION['user_role'] ?? '') === 'tutor'
              ? 'tutor/dashboard.php'
              : (($_SESSION['user_role'] ?? '') === 'admin' ? 'admin/dashboard.php' : 'student/dashboard.php');
          ?>
          <li class="nav-item"><a class="nav-link" href="<?= base_url($dashboardPath) ?>">Dashboard</a></li>
        <?php endif; ?>

        <li class="nav-item"><a class="nav-link" href="<?= base_url('find-tutor.php') ?>">Find Tutor</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= base_url('auth/register.php') ?>?role=tutor">Become a Tutor</a></li>
        <li class="nav-item"><a class="nav-link" href="#why-us">About</a></li>
        <li class="nav-item"><a class="nav-link" href="#contact">Contact</a></li>
      </ul>

      <div class="d-flex align-items-center gap-2 mt-3 mt-xl-0">
        <?php if (!empty($_SESSION['user_id'])): ?>
          <?php
          $unreadCount = 0;
          if (function_exists('db')) {
              try {
                  $q = db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
                  $q->execute([$_SESSION['user_id']]);
                  $unreadCount = (int) $q->fetchColumn();
              } catch (Throwable $e) {
              }
          }
          ?>

          <a class="btn btn-outline-brand btn-sm px-3 position-relative" href="<?= base_url('notifications.php') ?>">
            Alerts
            <?php if ($unreadCount): ?>
              <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"><?= (int) $unreadCount ?></span>
            <?php endif; ?>
          </a>

          <div class="dropdown">
            <button class="btn btn-brand btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
              <?= e($_SESSION['user_name'] ?? 'Account') ?>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow-sm">
              <li><a class="dropdown-item" href="<?= base_url($dashboardPath) ?>">Dashboard</a></li>
              <?php if (($_SESSION['user_role'] ?? '') === 'tutor'): ?>
                <li><a class="dropdown-item" href="<?= base_url('tutor/setup.php') ?>">Teacher details</a></li>
              <?php endif; ?>
              <li><a class="dropdown-item" href="<?= base_url('chat.php') ?>">Messages</a></li>
              <li><a class="dropdown-item" href="<?= base_url('settings.php') ?>">Settings</a></li>
              <li><a class="dropdown-item" href="<?= base_url('notifications.php') ?>">Notifications</a></li>
              <li><hr class="dropdown-divider"></li>
              <li><a class="dropdown-item text-danger" href="<?= base_url('auth/logout.php') ?>">Log out</a></li>
            </ul>
          </div>
        <?php else: ?>
          <a class="btn btn-link text-decoration-none text-dark fw-semibold" href="<?= base_url('auth/login.php') ?>">Login</a>
          <a class="btn btn-brand btn-sm px-3" href="<?= base_url('auth/register.php') ?>">Register</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</nav>
