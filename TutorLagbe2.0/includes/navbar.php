<nav class="navbar navbar-expand-lg navbar-light bg-white sticky-top site-nav">
  <div class="container">
    <a class="navbar-brand" href="<?= base_url('index.php') ?>"><img class="site-logo nav-logo" src="<?= base_url('assets/images/tutorlagbe-logo.svg') ?>" alt="TutorLagbe"></a>
    <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-label="Toggle navigation"><span class="navbar-toggler-icon"></span></button>
    <div class="collapse navbar-collapse" id="mainNav">
      <ul class="navbar-nav mx-auto gap-lg-2">
        <li class="nav-item"><a class="nav-link" href="<?= base_url('index.php') ?>">Home</a></li>
        <?php if (!empty($_SESSION['user_id'])): ?>
          <?php $dashboardPath = ($_SESSION['user_role'] ?? '') === 'tutor' ? 'tutor/dashboard.php' : (($_SESSION['user_role'] ?? '') === 'admin' ? 'admin/dashboard.php' : 'student/dashboard.php'); ?>
          <li class="nav-item"><a class="nav-link" href="<?= base_url($dashboardPath) ?>"><span class="dashboard-nav-icon" aria-hidden="true">▦</span> Dashboard</a></li>
        <?php endif; ?>
        <li class="nav-item"><a class="nav-link" href="<?= base_url('find-tutor.php') ?>">Find Tutor</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= base_url('auth/register.php') ?>?role=tutor">Become a Tutor</a></li>
        <li class="nav-item"><a class="nav-link" href="#why-us">About</a></li>
        <li class="nav-item"><a class="nav-link" href="#contact">Contact</a></li>
      </ul>
      <div class="d-flex gap-2 mt-3 mt-lg-0">
        <?php if (!empty($_SESSION['user_id'])): ?>
          <a class="btn btn-outline-brand btn-sm px-3" href="<?= base_url('auth/logout.php') ?>">Log out</a>
        <?php else: ?>
          <a class="btn btn-link text-decoration-none text-dark fw-semibold" href="<?= base_url('auth/login.php') ?>">Login</a>
          <a class="btn btn-brand btn-sm px-3" href="<?= base_url('auth/register.php') ?>">Register</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</nav>
