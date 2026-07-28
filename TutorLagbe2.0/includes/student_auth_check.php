<?php
require_once __DIR__ . '/bootstrap.php';
if (($_SESSION['user_role'] ?? '') !== 'student') { header('Location: ' . base_url('auth/login.php?role=student')); exit; }
