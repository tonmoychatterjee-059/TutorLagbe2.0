<aside class="admin-sidebar p-3" id="adminSidebar">
  <a class="mb-4 d-block" href="<?= base_url('admin/dashboard.php') ?>"><img class="site-logo" src="<?= base_url('assets/images/tutorlagbe-logo.svg') ?>" alt="TutorLagbe"></a>
  <p class="text-uppercase small text-secondary fw-bold px-2">Administration</p>
  <nav class="nav flex-column gap-1">
    <?php foreach (['dashboard.php'=>'Dashboard','pending_tutors.php'=>'Pending Tutors','all_tutors.php'=>'All Tutors','featured_tutors.php'=>'Featured Tutors','all_students.php'=>'All Students','subjects.php'=>'Subjects','bookings.php'=>'Bookings','payments.php'=>'Payments','reports.php'=>'Reports'] as $file => $label): ?>
      <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === $file ? 'active' : '' ?>" href="<?= base_url('admin/' . $file) ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
    <hr><a class="nav-link text-danger" href="<?= base_url('admin/logout.php') ?>">Logout</a>
  </nav>
</aside>
