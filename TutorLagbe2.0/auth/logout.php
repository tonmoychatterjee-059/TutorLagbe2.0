<?php
require_once __DIR__ . '/../includes/bootstrap.php';
// Keep an administrator session alive when a student/tutor logs out in another tab.
unset($_SESSION['user_id'], $_SESSION['user_name'], $_SESSION['user_role']);
header('Location: ' . base_url('index.php')); exit;
