<?php
require_once __DIR__ . '/bootstrap.php';
if (($_SESSION['user_role'] ?? '') !== 'tutor') { header('Location: ' . base_url('auth/login.php?role=tutor')); exit; }
