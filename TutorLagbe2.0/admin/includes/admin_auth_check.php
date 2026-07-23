<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
if (empty($_SESSION['admin_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    flash('admin_error', 'Please log in as an administrator.');
    header('Location: ' . base_url('admin/login.php'));
    exit;
}
