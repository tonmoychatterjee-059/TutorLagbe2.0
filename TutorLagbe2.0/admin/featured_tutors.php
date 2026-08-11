<?php
require_once __DIR__ . '/includes/admin_auth_check.php';
require_once __DIR__ . '/../config/db.php';

$rows = [];
try {
    $rows = db()->query(
        "SELECT u.id AS user_id, u.full_name, u.email, u.phone, u.is_verified, u.is_suspended,
                COALESCE(t.rating, 0) AS rating, COALESCE(t.hourly_rate, 0) AS hourly_rate, COALESCE(t.is_featured, 0) AS is_featured
         FROM users u
         LEFT JOIN tutors t ON t.user_id = u.id
         WHERE u.role = 'tutor' AND u.is_verified = 1 AND u.is_suspended = 0
         ORDER BY t.is_featured DESC, t.rating DESC, u.created_at DESC"
    )->fetchAll();
} catch (Throwable $e) {
}

$pageTitle = 'Featured Tutors';
require __DIR__ . '/includes/admin_layout_start.php';
?>
<h1 class="h3 mb-1">Featured tutors</h1>
<p class="text-secondary mb-4">These tutors appear in the homepage “Featured educators” section.</p>

<?php if ($m = flash('admin_success')): ?><div class="alert alert-success"><?= e($m) ?></div><?php endif; ?>
<?php if ($m = flash('admin_error')): ?><div class="alert alert-danger"><?= e($m) ?></div><?php endif; ?>

<div class="admin-table table-responsive">
  <table class="table table-hover mb-0">
    <thead>
      <tr>
        <th>Name</th>
        <th>Email</th>
        <th>Rating</th>
        <th>Rate</th>
        <th>Status</th>
        <th>Featured</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td class="fw-semibold"><?= e($r['full_name']) ?></td>
          <td><?= e($r['email']) ?></td>
          <td><?= number_format((float) $r['rating'], 1) ?></td>
          <td>৳<?= number_format((float) $r['hourly_rate'], 0) ?></td>
          <td><span class="badge text-bg-success status-badge">Approved</span></td>
          <td>
            <form method="post" action="actions.php" class="d-inline">
              <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="action" value="<?= $r['is_featured'] ? 'unfeature_tutor' : 'feature_tutor' ?>">
              <input type="hidden" name="id" value="<?= e((string) $r['user_id']) ?>">
              <input type="hidden" name="redirect" value="featured_tutors.php">
              <button class="btn btn-sm <?= $r['is_featured'] ? 'btn-outline-danger' : 'btn-brand' ?>">
                <?= $r['is_featured'] ? 'Remove from featured' : 'Add to featured' ?>
              </button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?>
        <tr><td colspan="6" class="text-center text-secondary py-5">No approved tutors found.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<div class="alert alert-info mt-4 mb-0">
  Tip: featured tutors are still approved tutors. When you add them here, they automatically appear in the homepage section.
</div>

<?php require __DIR__ . '/includes/admin_layout_end.php'; ?>
