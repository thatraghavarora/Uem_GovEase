<?php
// api/webhook.php
declare(strict_types=1);

require_once __DIR__ . '/../db/config.php';

/**
 * WhatsApp Cloud API config (EDIT THESE)
 */
const WA_VERIFY_TOKEN    = '1';
const WA_PHONE_NUMBER_ID = '893634317158651'; // example
const WA_ACCESS_TOKEN    = 'EAALFU0REQCYBQGobCNhbPtqVZBlubQHWtLY8TmARnAvdjINeQS4d2mZArtinK9KbMyYsSeRZCQjZBxCaENQMa0acwRIklCkAAjLlAmMKO5rOq5GjiZCq3j5XU0qfnbdJNEmB9fyFKereCyhQVcJscTySsxjOVd8qtLO12ZCFlkglOsZAg6euVKG0GFfgesA2QzuwQZDZD'; // long-lived token

header('Access-Control-Allow-Origin: *');

/**
 * Send WhatsApp text message via Cloud API.
 *
 * @param string $to   Recipient phone in E.164 with +
 * @param string $body Text message
 *
 * @return array{status:int,response:?string,error:?string}
 */
function wa_send_text(string $to, string $body): array
{
    $url = 'https://graph.facebook.com/v18.0/' . WA_PHONE_NUMBER_ID . '/messages';

    $payload = [
        'messaging_product' => 'whatsapp',
        'to'                => $to,
        'type'              => 'text',
        'text'              => ['body' => $body],
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . WA_ACCESS_TOKEN,
            'Content-Type: application/json',
        ],
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
    ]);
    $response = curl_exec($ch);
    $error    = curl_error($ch);
    $status   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Debug log file
    file_put_contents(
        __DIR__ . '/../wa_debug_send.log',
        date('c') . " | TO={$to} | STATUS={$status} | ERROR={$error} | RESP={$response}" . PHP_EOL,
        FILE_APPEND
    );

    if ($response === false) {
        error_log('WA send error: ' . $error);
    }

    return [
        'status'   => (int) $status,
        'response' => $response === false ? null : $response,
        'error'    => $error ?: null,
    ];
}

/**
 * Extract security code from WhatsApp message text.
 * Matches "Code: 123456" or "code : ABC123"
 */
function extract_code(string $text): ?string
{
    if (preg_match('/code\s*:\s*([A-Z0-9]{4,10})/i', $text, $m)) {
        return strtoupper($m[1]);
    }
    return null;
}

/**
 * Optional: Log event in wa_logs
 */
function log_event(?int $tenantId, string $logType, string $event, array $data = []): void
{
    global $appConfig;

    try {
        $pdo   = db();
        $table = table('logs');

        $stmt = $pdo->prepare("
            INSERT INTO {$table} (
                tenant_id, log_type, event, user_phone, session_token, ip, user_agent, raw_payload
            ) VALUES (
                :tenant_id, :log_type, :event, :user_phone, :session_token, :ip, :user_agent, :raw_payload
            )
        ");

        $stmt->execute([
            ':tenant_id'    => $tenantId,
            ':log_type'     => $logType,
            ':event'        => $event,
            ':user_phone'   => $data['user_phone']   ?? null,
            ':session_token'=> $data['session_token']?? null,
            ':ip'           => $_SERVER['REMOTE_ADDR'] ?? null,
            ':user_agent'   => $_SERVER['HTTP_USER_AGENT'] ?? null,
            ':raw_payload'  => isset($data['raw']) ? json_encode($data['raw']) : null,
        ]);
    } catch (Throwable $e) {
        error_log('Log insert failed: ' . $e->getMessage());
    }
}

/**
 * Simple file logger for raw webhook calls (for debugging)
 */
function log_raw_request(string $raw): void
{
    file_put_contents(
        __DIR__ . '/../wa_debug_webhook.log',
        date('c') . ' | METHOD=' . ($_SERVER['REQUEST_METHOD'] ?? 'CLI') . ' | RAW=' . $raw . PHP_EOL,
        FILE_APPEND
    );
}

// ======================= TEST ENDPOINT (GET) =======================
// Example: /api/webhook.php?action=test-send&to=91626xxxxxxx&body=Hello
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'test-send') {
    $toRaw = trim($_GET['to'] ?? '');
    $body  = trim($_GET['body'] ?? 'Hello! This is a test message from webhook.php test endpoint.');

    if ($toRaw === '') {
        header('Content-Type: text/plain; charset=utf-8');
        echo "ERROR: 'to' parameter required. Example:\n";
        echo "  /api/webhook.php?action=test-send&to=91626XXXXXXX&body=Hello\n";
        exit;
    }

    // sanitize to digits and add +
    $toDigits = preg_replace('/\D+/', '', $toRaw);
    $to       = '+' . $toDigits;

    $result = wa_send_text($to, $body);

    header('Content-Type: text/plain; charset=utf-8');
    echo "Test send to: {$to}\n";
    echo "HTTP Status : {$result['status']}\n";
    echo "cURL Error  : " . ($result['error'] ?? 'none') . "\n";
    echo "Response    : " . ($result['response'] ?? 'null') . "\n";
    exit;
}

// ================== GET: webhook verification handshake ==================
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $mode      = $_GET['hub_mode'] ?? $_GET['hub.mode'] ?? null;
    $token     = $_GET['hub_verify_token'] ?? $_GET['hub.verify_token'] ?? null;
    $challenge = $_GET['hub_challenge'] ?? $_GET['hub.challenge'] ?? '';

    if ($mode === 'subscribe' && $token === WA_VERIFY_TOKEN) {
        header('Content-Type: text/plain');
        echo $challenge;
        exit;
    }

    http_response_code(403);
    echo 'Forbidden';
    exit;
}

// ================== POST: incoming WhatsApp message ==================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo 'Method Not Allowed';
    exit;
}

$raw = file_get_contents('php://input');
log_raw_request($raw);

$data = json_decode($raw, true) ?: [];

log_event(null, 'webhook', 'incoming_raw', ['raw' => $data]);

if (empty($data['entry'][0]['changes'][0]['value']['messages'][0])) {
    http_response_code(200);
    echo 'OK';
    exit;
}

$value   = $data['entry'][0]['changes'][0]['value'];
$message = $value['messages'][0];
$from    = $message['from'] ?? '';                 // phone without +
$text    = $message['text']['body'] ?? '';
$profile = $value['contacts'][0]['profile']['name'] ?? '';

if ($from === '' || $text === '') {
    http_response_code(200);
    echo 'OK';
    exit;
}

// Extract code
$code = extract_code($text);
if ($code === null) {
    // non-code message: ignore or auto reply
    http_response_code(200);
    echo 'OK';
    exit;
}

$phoneDigits = preg_replace('/\D+/', '', $from);

// Find matching pending session
try {
    $pdo        = db();
    $table      = table('verification_sessions');
    $now        = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');

    $sql = "
        SELECT s.*, t.webhook_url
        FROM {$table} s
        INNER JOIN wa_tenants t ON t.id = s.tenant_id
        WHERE s.user_phone = :phone
          AND s.wa_code = :code
          AND s.status = 'pending'
          AND s.expires_at >= :now
        ORDER BY s.created_at DESC
        LIMIT 1
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':phone' => $phoneDigits,
        ':code'  => $code,
        ':now'   => $now,
    ]);

    $session = $stmt->fetch();
} catch (Throwable $e) {
    error_log('Session lookup failed: ' . $e->getMessage());
    $session = null;
}

if (!$session) {
    wa_send_text('+' . $phoneDigits, 'Invalid or expired security code. Please restart verification on the website.');
    http_response_code(200);
    echo 'OK';
    exit;
}

$tenantId   = (int) $session['tenant_id'];
$sessionId  = (int) $session['id'];
$sessionTok = $session['session_token'];
$webhookUrl = $session['webhook_url'] ?? null;

// Mark as verified & generate verify token
$verifyToken = generateToken(16);
$appUrl      = $appConfig['app_url'] ?? 'https://auth.webpeaker.com';
$verifyLink  = rtrim($appUrl, '/') . '/api/verify1.php?token=' . urlencode($verifyToken);

try {
    $table = table('verification_sessions');

    $stmt = $pdo->prepare("
        UPDATE {$table}
        SET status = 'verified',
            wa_verify_token = :verify_token,
            verified_at = NOW()
        WHERE id = :id
    ");

    $stmt->execute([
        ':verify_token' => $verifyToken,
        ':id'           => $sessionId,
    ]);

    log_event($tenantId, 'auth', 'verified', [
        'user_phone'    => $phoneDigits,
        'session_token' => $sessionTok,
        'raw'           => $data,
    ]);
} catch (Throwable $e) {
    error_log('Failed to update session as verified: ' . $e->getMessage());
}

// Reply on WhatsApp with verify link
$reply = "Hi {$profile}, your number has been verified ✅\n\n"
    . "If you are on your browser, tap this link to continue: {$verifyLink}";
wa_send_text('+' . $phoneDigits, $reply);

// Optional: notify tenant via webhook (server-to-server)
if ($webhookUrl) {
    try {
        $payload = [
            'event'         => 'whatsapp_verified',
            'tenant_id'     => $tenantId,
            'session_token' => $sessionTok,
            'phone'         => '+' . $phoneDigits,
            'verified_at'   => (new DateTimeImmutable('now'))->format(DateTimeInterface::ATOM),
        ];
        $ch = curl_init($webhookUrl);
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_TIMEOUT        => 5,
        ]);
        $resp = curl_exec($ch);
        if ($resp === false) {
            error_log('Tenant webhook error: ' . curl_error($ch));
        }
        curl_close($ch);
    } catch (Throwable $e) {
        error_log('Tenant webhook exception: ' . $e->getMessage());
    }
}

http_response_code(200);
echo 'OK';