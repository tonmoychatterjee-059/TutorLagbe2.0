<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/config/db.php';

if (empty($_SESSION['user_id'])) {
    http_response_code(403);
    exit;
}

$to = (int) ($_POST['receiver_id'] ?? 0);
$file = $_FILES['file'] ?? null;

if ($to && $file && $file['error'] === UPLOAD_ERR_OK && $file['size'] < 5242880) {
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx'];
    if (in_array($ext, $allowed, true)) {
        $name = bin2hex(random_bytes(12)) . '.' . $ext;
        $dir = __DIR__ . '/assets/uploads/chat/';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $target = $dir . $name;
        if (move_uploaded_file($file['tmp_name'], $target)) {
            $q = db()->prepare('INSERT INTO messages (sender_id, receiver_id, file_url) VALUES (?, ?, ?)');
            $q->execute([$_SESSION['user_id'], $to, 'assets/uploads/chat/' . $name]);
            db()->prepare(
                'INSERT INTO notifications (user_id, type, message, related_id)
                 VALUES (?, "new_message", ?, ?)'
            )->execute([
                $to,
                'You received a new file.',
                $_SESSION['user_id'],
            ]);
        }
    }
}

header('Location: chat.php?user_id=' . $to);
exit;
