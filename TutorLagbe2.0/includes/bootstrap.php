<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/system.php';
require_once __DIR__ . '/choices.php';

if (function_exists('db')) {
    try {
        if (empty($GLOBALS['tutorlagbe_schema_ready'])) {
            $columns = db()->query("SHOW COLUMNS FROM users LIKE 'address'")->fetchAll();
            if (!$columns) {
                db()->exec("ALTER TABLE users ADD COLUMN address VARCHAR(150) NULL AFTER phone");
            }
            $videoColumns = db()->query("SHOW COLUMNS FROM tutors LIKE 'demo_video'")->fetchAll();
            if (!$videoColumns) {
                db()->exec("ALTER TABLE tutors ADD COLUMN demo_video VARCHAR(255) NULL AFTER cover_photo");
            }
            $GLOBALS['tutorlagbe_schema_ready'] = true;
        }
    } catch (Throwable $e) {
        // Ignore schema bootstrap issues; the app can still run in read-only mode.
    }
}

function base_url(string $path = ''): string
{
    // Override with TUTORLAGBE_BASE_URL when using a custom virtual host.
    $base = getenv('TUTORLAGBE_BASE_URL');
    if ($base === false || $base === '') {
        $base = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
        // Auth pages sit one directory below the application root.
        if (str_ends_with($base, '/auth') || str_ends_with($base, '/admin') || str_ends_with($base, '/student') || str_ends_with($base, '/tutor')) {
            $base = dirname($base);
        }
    }
    $base = rtrim($base, '/');
    return $base . '/' . ltrim($path, '/');
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf(?string $token): bool
{
    return is_string($token) && isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function flash(string $key, ?string $message = null): ?string
{
    if ($message !== null) {
        $_SESSION['flash'][$key] = $message;
        return null;
    }
    $message = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);
    return $message;
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
