<?php

function app_settings(): array
{
    $cache = $GLOBALS['app_settings_cache'] ?? null;
    if (is_array($cache)) {
        return $cache;
    }

    $cache = [];
    if (!function_exists('db')) {
        return $cache;
    }

    try {
        $rows = db()->query('SELECT setting_key, setting_value FROM app_settings')->fetchAll();
        foreach ($rows as $row) {
            $cache[(string) $row['setting_key']] = (string) ($row['setting_value'] ?? '');
        }
    } catch (Throwable $e) {
        $cache = [];
    }

    $GLOBALS['app_settings_cache'] = $cache;
    return $cache;
}

function setting(string $key, mixed $default = null): mixed
{
    $settings = app_settings();
    if (array_key_exists($key, $settings) && $settings[$key] !== '') {
        return $settings[$key];
    }

    $envKey = 'TUTORLAGBE_' . strtoupper(str_replace(['.', '-'], '_', $key));
    $env = getenv($envKey);
    return ($env !== false && $env !== '') ? $env : $default;
}

function set_setting(string $key, ?string $value): bool
{
    if (!function_exists('db')) {
        return false;
    }

    try {
        $q = db()->prepare(
            'INSERT INTO app_settings (setting_key, setting_value) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = CURRENT_TIMESTAMP'
        );
        $q->execute([$key, $value]);
        $GLOBALS['app_settings_cache'] = null;
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

function ensure_schema(): void
{
    if (!function_exists('db')) {
        return;
    }

    if (!empty($GLOBALS['tutorlagbe_schema_ready'])) {
        return;
    }

    try {
        $pdo = db();

        $checks = [
            ["SHOW COLUMNS FROM users LIKE 'address'", "ALTER TABLE users ADD COLUMN address VARCHAR(150) NULL AFTER phone"],
            ["SHOW COLUMNS FROM tutors LIKE 'demo_video'", "ALTER TABLE tutors ADD COLUMN demo_video VARCHAR(255) NULL AFTER cover_photo"],
        ];

        foreach ($checks as [$checkSql, $alterSql]) {
            $exists = $pdo->query($checkSql)->fetchAll();
            if (!$exists) {
                $pdo->exec($alterSql);
            }
        }

        $GLOBALS['tutorlagbe_schema_ready'] = true;
    } catch (Throwable $e) {
        // Ignore schema bootstrap issues; the app can still run if migrations are applied manually.
    }
}

function app_secret(): string
{
    return (string) setting('app_secret', getenv('TUTORLAGBE_APP_SECRET') ?: hash('sha256', __DIR__ . '|TutorLagbe'));
}

function notification_href(array $notification): string
{
    $type = (string) ($notification['type'] ?? '');
    $relatedId = (int) ($notification['related_id'] ?? 0);

    return match ($type) {
        'booking_pending' => base_url('tutor/requests.php'),
        'booking_accepted', 'booking_rejected', 'session_reminder' => base_url('student/my-bookings.php' . ($relatedId ? '?booking_id=' . $relatedId : '')),
        'payment_success' => base_url('student/payments.php' . ($relatedId ? '?booking_id=' . $relatedId : '')),
        'new_message' => base_url('chat.php' . ($relatedId ? '?user_id=' . $relatedId : '')),
        default => base_url('notifications.php'),
    };
}

function booking_meeting_url(array $booking): string
{
    $domain = trim((string) setting('video_domain', 'meet.jit.si'));
    $domain = preg_replace('~^https?://~i', '', $domain);
    $domain = trim($domain, '/');
    $seed = implode('|', [
        (string) ($booking['id'] ?? ''),
        (string) ($booking['booking_date'] ?? ''),
        (string) ($booking['start_time'] ?? ''),
        app_secret(),
    ]);
    $room = 'tutorlagbe-' . substr(hash('sha256', $seed), 0, 20);
    return 'https://' . $domain . '/' . $room;
}

function smtp_read_response($socket): array
{
    $response = '';
    while (!feof($socket)) {
        $line = fgets($socket, 515);
        if ($line === false) {
            break;
        }
        $response .= $line;
        if (strlen($line) < 4 || $line[3] !== '-') {
            break;
        }
    }
    return [(int) substr($response, 0, 3), $response];
}

function smtp_command($socket, string $command, array $expectedCodes = [250]): void
{
    fwrite($socket, $command . "\r\n");
    [$code, $response] = smtp_read_response($socket);
    if (!in_array($code, $expectedCodes, true)) {
        throw new RuntimeException('SMTP error: ' . trim($response));
    }
}

function send_app_mail(string $to, string $subject, string $html, string $text = ''): bool
{
    $fromEmail = (string) setting('smtp_from_email', setting('support_email', 'noreply@tutorlagbe.com'));
    $fromName = (string) setting('smtp_from_name', 'TutorLagbe');
    $host = trim((string) setting('smtp_host', ''));
    $port = (int) setting('smtp_port', 587);
    $username = (string) setting('smtp_username', '');
    $password = (string) setting('smtp_password', '');
    $encryption = strtolower((string) setting('smtp_encryption', 'tls'));

    $plainText = trim($text) !== '' ? $text : trim(html_entity_decode(strip_tags(preg_replace('~<br\s*/?>~i', "\n", $html)), ENT_QUOTES, 'UTF-8'));
    $boundary = 'b1_' . bin2hex(random_bytes(12));
    $headers = [
        'From: ' . $fromName . ' <' . $fromEmail . '>',
        'Reply-To: ' . $fromEmail,
        'MIME-Version: 1.0',
        'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
    ];
    $mime = implode("\r\n", [
        '--' . $boundary,
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
        '',
        $plainText,
        '--' . $boundary,
        'Content-Type: text/html; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
        '',
        $html,
        '--' . $boundary . '--',
        '',
    ]);

    if ($host === '') {
        return @mail($to, $subject, $plainText, implode("\r\n", $headers));
    }

    $remote = ($encryption === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;
    $socket = @stream_socket_client($remote, $errno, $errstr, 20);
    if (!$socket) {
        return false;
    }
    stream_set_timeout($socket, 20);
    [$code] = smtp_read_response($socket);
    if ($code >= 400) {
        fclose($socket);
        return false;
    }

    $hostName = parse_url(base_url(''), PHP_URL_HOST) ?: 'localhost';
    smtp_command($socket, 'EHLO ' . $hostName, [250]);
    if ($encryption === 'tls') {
        smtp_command($socket, 'STARTTLS', [220]);
        if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            fclose($socket);
            return false;
        }
        smtp_command($socket, 'EHLO ' . $hostName, [250]);
    }
    if ($username !== '') {
        smtp_command($socket, 'AUTH LOGIN', [334]);
        smtp_command($socket, base64_encode($username), [334]);
        smtp_command($socket, base64_encode($password), [235]);
    }

    smtp_command($socket, 'MAIL FROM:<' . $fromEmail . '>', [250]);
    smtp_command($socket, 'RCPT TO:<' . $to . '>', [250, 251]);
    smtp_command($socket, 'DATA', [354]);
    fwrite(
        $socket,
        implode("\r\n", array_merge(
            $headers,
            [
                'To: <' . $to . '>',
                'Subject: ' . $subject,
                '',
                $mime,
            ]
        )) . "\r\n.\r\n"
    );
    [$dataCode] = smtp_read_response($socket);
    smtp_command($socket, 'QUIT', [221]);
    fclose($socket);
    return $dataCode >= 200 && $dataCode < 300;
}

function create_payment_reference(array $booking, string $method): string
{
    return strtoupper($method) . '-' . substr(hash('sha256', implode('|', [
        (string) ($booking['id'] ?? ''),
        (string) ($booking['student_id'] ?? ''),
        (string) ($booking['price'] ?? ''),
        app_secret(),
        microtime(true),
        random_int(1000, 9999),
    ])), 0, 16);
}

function start_payment_flow(array $booking, string $method): array
{
    $gateway = strtolower((string) setting('payment_gateway', ''));
    $bkashPersonalNumber = trim((string) setting('bkash_personal_number', ''));
    $bkashAccountName = trim((string) setting('bkash_account_name', ''));
    $stripeSecret = (string) setting('stripe_secret_key', '');
    $stripePublic = (string) setting('stripe_public_key', '');
    $paymentEndpoint = trim((string) setting('payment_gateway_endpoint', ''));
    $paymentToken = trim((string) setting('payment_gateway_token', ''));
    $callback = base_url('payment-success.php?booking_id=' . (int) ($booking['id'] ?? 0));

    if ($method === 'bkash' && $bkashPersonalNumber !== '') {
        return [
            'mode' => 'manual',
            'message' => 'Send money to the bKash number shown on the payment page, then submit your transaction ID.',
            'personal_number' => $bkashPersonalNumber,
            'account_name' => $bkashAccountName,
        ];
    }

    if (in_array($method, ['visa', 'mastercard'], true) && $gateway === 'stripe' && $stripeSecret !== '') {
        $session = stripe_checkout_session($booking, $method, $stripeSecret, $callback);
        if ($session) {
            return ['mode' => 'redirect', 'url' => $session['url'], 'transaction_id' => $session['id'], 'status' => 'pending'];
        }
    }

    if (in_array($method, ['bkash', 'nagad', 'rocket'], true) && $paymentEndpoint !== '') {
        $payload = [
            'booking_id' => (int) $booking['id'],
            'amount' => (float) $booking['price'],
            'currency' => 'BDT',
            'method' => $method,
            'reference' => 'booking-' . (int) $booking['id'],
            'return_url' => $callback,
        ];
        $ch = curl_init($paymentEndpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => array_filter([
                'Content-Type: application/json',
                $paymentToken !== '' ? 'Authorization: Bearer ' . $paymentToken : null,
            ]),
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT => 30,
        ]);
        $raw = curl_exec($ch);
        $statusCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($raw !== false && $statusCode >= 200 && $statusCode < 300) {
            $data = json_decode($raw, true);
            if (is_array($data) && !empty($data['redirect_url'])) {
                return [
                    'mode' => 'redirect',
                    'url' => (string) $data['redirect_url'],
                    'transaction_id' => (string) ($data['transaction_id'] ?? create_payment_reference($booking, $method)),
                    'status' => (string) ($data['status'] ?? 'pending'),
                ];
            }
        }
    }

    if ((string) setting('payment_demo_mode', '0') === '1') {
        return [
            'mode' => 'record',
            'transaction_id' => create_payment_reference($booking, $method),
            'status' => 'success',
        ];
    }

    return [
        'mode' => 'unavailable',
        'message' => 'Payment gateway settings are not configured yet. Please add your live provider credentials in Settings.',
        'stripe_public_key' => $stripePublic,
    ];
}

function stripe_checkout_session(array $booking, string $method, string $secretKey, string $successUrl): ?array
{
    $payload = http_build_query([
        'mode' => 'payment',
        'success_url' => $successUrl,
        'cancel_url' => base_url('payment.php?booking_id=' . (int) $booking['id']),
        'client_reference_id' => 'booking-' . (int) $booking['id'],
        'customer_email' => (string) ($booking['student_email'] ?? ''),
        'line_items[0][price_data][currency]' => 'bdt',
        'line_items[0][price_data][product_data][name]' => 'TutorLagbe session #' . (int) $booking['id'],
        'line_items[0][price_data][unit_amount]' => (int) round(((float) $booking['price']) * 100),
        'line_items[0][quantity]' => 1,
        'metadata[booking_id]' => (string) (int) $booking['id'],
        'metadata[method]' => $method,
    ]);

    $ch = curl_init('https://api.stripe.com/v1/checkout/sessions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
        CURLOPT_USERPWD => $secretKey . ':',
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_TIMEOUT => 30,
    ]);
    $raw = curl_exec($ch);
    $statusCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    if ($raw === false || $statusCode < 200 || $statusCode >= 300) {
        return null;
    }

    $data = json_decode($raw, true);
    if (!is_array($data) || empty($data['url'])) {
        return null;
    }

    return [
        'id' => (string) ($data['id'] ?? ''),
        'url' => (string) $data['url'],
    ];
}
