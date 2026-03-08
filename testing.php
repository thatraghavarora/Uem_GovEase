<?php
declare(strict_types=1);

$credentialsPath = __DIR__ . '/govease-99021-firebase-adminsdk-fbsvc-fe9d642385.json';
if (!is_readable($credentialsPath)) {
    http_response_code(500);
    echo 'Service account JSON not readable at: ' . $credentialsPath;
    exit;
}

try {
    $serviceAccount = json_decode((string) file_get_contents($credentialsPath), true, 512, JSON_THROW_ON_ERROR);

    $projectId = (string) ($serviceAccount['project_id'] ?? '');
    $clientEmail = (string) ($serviceAccount['client_email'] ?? '');
    $privateKey = (string) ($serviceAccount['private_key'] ?? '');

    if ($projectId === '' || $clientEmail === '' || $privateKey === '') {
        throw new RuntimeException('Invalid service account JSON.');
    }

    $token = getAccessToken($clientEmail, $privateKey);
    $now = gmdate('c');

    $payload = [
        'fields' => [
            'fullName' => ['stringValue' => 'Test User'],
            'phone' => ['stringValue' => '9999999999'],
            'email' => ['stringValue' => 'test@example.com'],
            'source' => ['stringValue' => 'kyc_test_insert.php'],
            'createdAt' => ['timestampValue' => $now],
        ],
    ];

    $url = 'https://firestore.googleapis.com/v1/projects/' . rawurlencode($projectId)
        . '/databases/(default)/documents/kyc_submissions';
    $response = curlJson('POST', $url, $token, $payload);
    if (empty($response['name'])) {
        throw new RuntimeException('Firestore insert failed.');
    }

    echo 'Inserted test KYC record.';
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Insert failed: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
}

function getAccessToken(string $clientEmail, string $privateKey): string
{
    $now = time();
    $jwtHeader = ['alg' => 'RS256', 'typ' => 'JWT'];
    $jwtClaim = [
        'iss' => $clientEmail,
        'scope' => 'https://www.googleapis.com/auth/datastore',
        'aud' => 'https://oauth2.googleapis.com/token',
        'iat' => $now,
        'exp' => $now + 3600,
    ];

    $jwt = base64UrlEncode(json_encode($jwtHeader)) . '.' . base64UrlEncode(json_encode($jwtClaim));
    openssl_sign($jwt, $signature, $privateKey, 'sha256');
    $jwt .= '.' . base64UrlEncode($signature);

    $tokenResponse = curlForm('POST', 'https://oauth2.googleapis.com/token', [
        'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
        'assertion' => $jwt,
    ]);

    if (empty($tokenResponse['access_token'])) {
        throw new RuntimeException('Unable to fetch access token.');
    }

    return (string) $tokenResponse['access_token'];
}

function curlJson(string $method, string $url, string $token, array $payload): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $token,
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

function curlForm(string $method, string $url, array $fields): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_POSTFIELDS => http_build_query($fields),
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
        throw new RuntimeException('Token API error: ' . $raw);
    }

    return $data;
}

function base64UrlEncode(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}
