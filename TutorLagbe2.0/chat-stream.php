<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/config/db.php';

if (empty($_SESSION['user_id'])) {
    http_response_code(403);
    exit;
}

$me = (int) $_SESSION['user_id'];
$other = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$other) {
    http_response_code(400);
    exit;
}

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');

$lastId = 0;
if (!empty($_SERVER['HTTP_LAST_EVENT_ID'])) {
    $lastId = (int) $_SERVER['HTTP_LAST_EVENT_ID'];
}

while (!connection_aborted()) {
    try {
        $q = db()->prepare(
            'SELECT id, sender_id, receiver_id, message, file_url, created_at
             FROM messages
             WHERE ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?))
               AND id > ?
             ORDER BY id ASC'
        );
        $q->execute([$me, $other, $other, $me, $lastId]);
        $rows = $q->fetchAll();

        foreach ($rows as $row) {
            $lastId = (int) $row['id'];
            echo 'id: ' . $lastId . "\n";
            echo 'data: ' . json_encode($row, JSON_UNESCAPED_UNICODE) . "\n\n";
            @ob_flush();
            @flush();
        }
    } catch (Throwable $e) {
    }

    echo ": ping\n\n";
    @ob_flush();
    @flush();
    sleep(3);
}
