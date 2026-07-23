<?php
/**
 * Central PDO connection. Change these environment-style constants to match
 * your local MySQL installation, or define them before including this file.
 */
defined('DB_HOST') || define('DB_HOST', getenv('TUTORLAGBE_DB_HOST') ?: '127.0.0.1');
defined('DB_NAME') || define('DB_NAME', getenv('TUTORLAGBE_DB_NAME') ?: 'tutorlagbe');
defined('DB_USER') || define('DB_USER', getenv('TUTORLAGBE_DB_USER') ?: 'root');
defined('DB_PASS') || define('DB_PASS', getenv('TUTORLAGBE_DB_PASS') ?: '');

function db(): PDO
{
    static $connection = null;
    if ($connection instanceof PDO) {
        return $connection;
    }

    try {
        $connection = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
    } catch (PDOException $exception) {
        throw new RuntimeException('Unable to connect to the database. Check config/db.php.', 0, $exception);
    }

    return $connection;
}
