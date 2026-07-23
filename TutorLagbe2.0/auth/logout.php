<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$_SESSION = []; session_destroy(); header('Location: ' . base_url('index.php')); exit;
