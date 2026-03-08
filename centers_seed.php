<?php
declare(strict_types=1);

$credentialsPath = __DIR__ . '/govease-99021-firebase-adminsdk-fbsvc-fe9d642385.json';
if (!is_readable($credentialsPath)) {
    fwrite(STDERR, "Service account JSON not readable.\n");
    exit(1);
}

$serviceAccount = json_decode((string) file_get_contents($credentialsPath), true, 512, JSON_THROW_ON_ERROR);
$projectId = (string) ($serviceAccount['project_id'] ?? '');
$clientEmail = (string) ($serviceAccount['client_email'] ?? '');
$privateKey = (string) ($serviceAccount['private_key'] ?? '');

if ($projectId === '' || $clientEmail === '' || $privateKey === '') {
    fwrite(STDERR, "Invalid service account JSON.\n");
    exit(1);
}

$token = getAccessToken($clientEmail, $privateKey);
$city = 'Jaipur';
$total = isset($argv[1]) ? max(1, (int) $argv[1]) : 100;
$start = isset($argv[2]) ? max(1, (int) $argv[2]) : 1;

$names = [
    'City Health Hub',
    'Pink City Clinic',
    'Jaipur Wellness Center',
    'Rajasthan Care Point',
    'Amber Health Desk',
    'Nahargarh Medical',
    'Hawa Mahal Health',
    'Metro Care Jaipur',
    'Sunrise Hospital',
    'Heritage Health Center',
];

$types = ['OPD', 'Primary Care', 'Diagnostics', 'Emergency', 'Speciality'];
$areas = [
    'Malviya Nagar',
    'Vaishali Nagar',
    'Mansarovar',
    'C Scheme',
    'Bapu Nagar',
    'Jagatpura',
    'Jhotwara',
    'Sodala',
    'Tonk Road',
    'Amer Road',
];

$created = 0;
$end = $start + $total - 1;
for ($i = $start; $i <= $end; $i++) {
    $name = $names[$i % count($names)] . ' ' . $i;
    $type = $types[$i % count($types)];
    $area = $areas[$i % count($areas)];
    $address = $i . ' ' . $area . ', Jaipur';
    $phone = '+91 98' . str_pad((string) (100000 + $i), 6, '0', STR_PAD_LEFT);

    $docId = 'jaipur-' . $i;
    $payload = [
        'fields' => [
            'name' => ['stringValue' => $name],
            'city' => ['stringValue' => $city],
            'address' => ['stringValue' => $address],
            'type' => ['stringValue' => $type],
            'phone' => ['stringValue' => $phone],
        ],
    ];

    $url = 'https://firestore.googleapis.com/v1/projects/' . rawurlencode($projectId)
        . '/databases/(default)/documents/centers/' . rawurlencode($docId);
    $response = curlJson('PATCH', $url, $token, $payload);
    if (empty($response['name'])) {
        fwrite(STDERR, "Failed to create center {$docId}\n");
        continue;
    }
    $created++;
    if ($created % 10 === 0) {
        echo "Created {$created} centers so far...\n";
    }
}

echo "Created {$created} centers (range {$start}-{$end}).\n";

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
    $options = [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $token,
        ],
        CURLOPT_TIMEOUT => 20,
    ];
    if (!empty($payload)) {
        $options[CURLOPT_POSTFIELDS] = json_encode($payload);
    }
    curl_setopt_array($ch, $options);

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
