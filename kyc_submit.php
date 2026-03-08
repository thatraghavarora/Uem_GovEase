<?php
declare(strict_types=1);

$debug = true;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Location: kyc.php');
    exit;
}

$fullName = trim((string) ($_POST['fullName'] ?? ($_COOKIE['user_name'] ?? '')));
$phone = normalizePhone(trim((string) ($_POST['phone'] ?? ($_POST['phone_display'] ?? ($_COOKIE['user_phone'] ?? '')))));
$email = trim((string) ($_POST['email'] ?? ($_COOKIE['user_email'] ?? '')));

if ($fullName === '' || $phone === '' || $email === '') {
    http_response_code(400);
    echo 'Missing required fields.<br>';
    echo 'POST: ' . htmlspecialchars(json_encode($_POST), ENT_QUOTES, 'UTF-8');
    exit;
}

$configPath = resolveFirebaseConfigPath();
if ($configPath === '') {
    http_response_code(500);
    echo 'firebase.json not readable.';
    exit;
}

$config = json_decode((string) file_get_contents($configPath), true);
$projectId = (string) ($config['projectId'] ?? '');
$apiKey = (string) ($config['apiKey'] ?? '');

if ($projectId === '' || $apiKey === '') {
    http_response_code(500);
    echo 'firebase.json missing projectId or apiKey.';
    exit;
}

try {
    $payload = [
        'fields' => [
            'fullName' => ['stringValue' => $fullName],
            'phone' => ['stringValue' => $phone],
            'email' => ['stringValue' => $email],
            'source' => ['stringValue' => 'kyc.php'],
            'createdAt' => ['timestampValue' => gmdate('c')],
        ],
    ];

    $docId = $phone;
    $url = 'https://firestore.googleapis.com/v1/projects/' . rawurlencode($projectId)
        . '/databases/(default)/documents/kyc_submissions/' . rawurlencode($docId)
        . '?key=' . rawurlencode($apiKey);

    $response = curlJsonNoAuth('PATCH', $url, $payload);
    if (empty($response['name'])) {
        throw new RuntimeException('Firestore insert failed.');
    }

    header('Location: home.php');
    exit;
} catch (Throwable $e) {
    http_response_code(500);
    echo htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
    exit;
}

function resolveFirebaseConfigPath(): string
{
    $candidates = [
        __DIR__ . '/firebase.json',
        dirname(__DIR__) . '/firebase.json',
        getcwd() . '/firebase.json',
    ];
    foreach ($candidates as $path) {
        if ($path !== false && is_readable($path)) {
            return $path;
        }
    }
    return '';
}

function normalizePhone(string $phone): string
{
    $value = trim($phone);
    if ($value === '') {
        return '';
    }
    if ($value[0] === '+') {
        return $value;
    }
    $digits = preg_replace('/\D+/', '', $value);
    if (strlen($digits) === 10) {
        return '+91' . $digits;
    }
    return '+' . $digits;
}

function curlJsonNoAuth(string $method, string $url, array $payload): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => 15,
    ]);

    $raw = curl_exec($ch);
    if ($raw === false) {
        throw new RuntimeException('Request failed: ' . curl_error($ch));
    }
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data = json_decode($raw, true);
    if (!is_array($data) || $status >= 400) {
        throw new RuntimeException('Firestore API error: ' . $raw);
    }

    return $data;
}
