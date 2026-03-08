<?php
// api/send-code.php
declare(strict_types=1);

header('Content-Type: application/json');

require_once __DIR__ . '/../db/config.php';

// Allow CORS if you want cross-domain frontend integration
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ----------- WhatsApp click-to-chat config (EDIT THESE) -----------
const WA_BUSINESS_NUMBER = '918949321383'; // digits only (no +)

function json_error(string $message, int $status = 400): void
{
    http_response_code($status);
    echo json_encode([
        'success' => false,
        'error'   => $message,
    ]);
    exit;
}

// Parse input (JSON or form)
$input = [];
if (stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {
    $raw = file_get_contents('php://input');
    $input = json_decode($raw, true) ?: [];
} else {
    $input = $_POST;
}

$apiKey = trim($input['api_key'] ?? '');
$phone  = trim($input['phone'] ?? '');

// Validate
if ($apiKey === '' || $phone === '') {
    json_error('api_key and phone are required.', 422);
}

// Clean phone (keep digits only)
$phoneDigits = preg_replace('/\D+/', '', $phone);
if (strlen($phoneDigits) < 8) {
    json_error('Invalid phone number format. Use full number with country code (e.g. 9198xxxxxx).', 422);
}

// Find tenant from API key
try {
    $tenant = findTenantByApiKey($apiKey);
} catch (Throwable $e) {
    error_log('API key lookup error: ' . $e->getMessage());
    json_error('Internal server error.', 500);
}

if (!$tenant) {
    json_error('Invalid API key.', 401);
}

$tenantId = (int) $tenant['id'];

// Create verification session
global $appConfig;

$sessionToken = generateToken(32);           // 64-char hex
$waCode       = generateNumericCode(6);      // 6-digit code
$expirySecs   = (int)($appConfig['security']['session_expiry_secs'] ?? (15 * 60));
$expiresAt    = (new DateTimeImmutable("now"))->modify("+{$expirySecs} seconds");

try {
    $pdo   = db();
    $table = table('verification_sessions');

    $stmt = $pdo->prepare("
        INSERT INTO {$table} (
            tenant_id,
            session_token,
            user_phone,
            wa_code,
            status,
            expires_at
        ) VALUES (
            :tenant_id,
            :session_token,
            :user_phone,
            :wa_code,
            'pending',
            :expires_at
        )
    ");

    $stmt->execute([
        ':tenant_id'     => $tenantId,
        ':session_token' => $sessionToken,
        ':user_phone'    => $phoneDigits,
        ':wa_code'       => $waCode,
        ':expires_at'    => $expiresAt->format('Y-m-d H:i:s'),
    ]);
} catch (Throwable $e) {
    error_log('Error creating verification session: ' . $e->getMessage());
    json_error('Failed to start verification.', 500);
}

// Build WhatsApp click-to-chat URL
$message   = "Please verify my number. Code: {$waCode}";
$waLink    = "https://wa.me/" . WA_BUSINESS_NUMBER . '?text=' . urlencode($message);

http_response_code(201);
echo json_encode([
    'success'       => true,
    'session_token' => $sessionToken,
    'wa_link'       => $waLink,
    'expires_at'    => $expiresAt->format(DateTimeInterface::ATOM),
    'phone'         => '+' . $phoneDigits,
]);