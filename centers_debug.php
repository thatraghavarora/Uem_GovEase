<?php
declare(strict_types=1);

header('Content-Type: application/json');

$credentialsPath = __DIR__ . '/govease-99021-firebase-adminsdk-fbsvc-fe9d642385.json';
$centerId = trim((string) ($_GET['id'] ?? ''));
$centerName = trim((string) ($_GET['name'] ?? ''));
$limit = (int) ($_GET['limit'] ?? 50);
if ($limit < 1) {
    $limit = 1;
}
if ($limit > 200) {
    $limit = 200;
}

if (!is_readable($credentialsPath)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Service account JSON not readable.']);
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

    if ($centerId !== '') {
        $centers = fetchCenterById($projectId, $token, $centerId);
    } elseif ($centerName !== '') {
        $centers = fetchCentersByName($projectId, $token, $centerName, $limit);
    } else {
        $centers = fetchAllCenters($projectId, $token, $limit);
    }

    echo json_encode([
        'success' => true,
        'count' => count($centers),
        'centers' => $centers,
    ]);
    exit;
} catch (Throwable $e) {
    error_log('Centers API failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Unable to fetch centers.']);
    exit;
}

function fetchCenterById(string $projectId, string $token, string $centerId): array
{
    $docRef = 'projects/' . $projectId . '/databases/(default)/documents/centers/' . $centerId;
    $payload = [
        'structuredQuery' => [
            'from' => [
                [
                    'collectionId' => 'centers',
                    'allDescendants' => true,
                ],
            ],
            'where' => [
                'fieldFilter' => [
                    'field' => ['fieldPath' => '__name__'],
                    'op' => 'EQUAL',
                    'value' => ['referenceValue' => $docRef],
                ],
            ],
            'limit' => 1,
        ],
    ];

    return fetchCenters($projectId, $token, $payload);
}

function fetchCentersByName(string $projectId, string $token, string $name, int $limit): array
{
    $payload = [
        'structuredQuery' => [
            'from' => [
                [
                    'collectionId' => 'centers',
                    'allDescendants' => true,
                ],
            ],
            'where' => [
                'fieldFilter' => [
                    'field' => ['fieldPath' => 'name'],
                    'op' => 'EQUAL',
                    'value' => ['stringValue' => $name],
                ],
            ],
            'limit' => $limit,
        ],
    ];

    return fetchCenters($projectId, $token, $payload);
}

function fetchAllCenters(string $projectId, string $token, int $limit): array
{
    $centers = [];
    $pageToken = '';
    $pageSize = min($limit, 200);

    while (count($centers) < $limit) {
        $query = http_build_query(array_filter([
            'pageSize' => (string) $pageSize,
            'pageToken' => $pageToken !== '' ? $pageToken : null,
        ]));
        $url = 'https://firestore.googleapis.com/v1/projects/' . rawurlencode($projectId)
            . '/databases/(default)/documents/centers' . ($query !== '' ? '?' . $query : '');
        $response = curlJson('GET', $url, $token, []);

        $documents = $response['documents'] ?? [];
        foreach ($documents as $doc) {
            if (empty($doc['fields']) || empty($doc['name'])) {
                continue;
            }
            $centers[] = mapCenterDoc($doc);
            if (count($centers) >= $limit) {
                break;
            }
        }

        $pageToken = (string) ($response['nextPageToken'] ?? '');
        if ($pageToken === '') {
            break;
        }
    }

    return $centers;
}

function fetchCenters(string $projectId, string $token, array $payload): array
{
    $url = 'https://firestore.googleapis.com/v1/projects/' . rawurlencode($projectId)
        . '/databases/(default)/documents:runQuery';
    $response = curlJson('POST', $url, $token, $payload);
    $centers = [];
    foreach ($response as $row) {
        $doc = $row['document'] ?? null;
        if (empty($doc['fields']) || empty($doc['name'])) {
            continue;
        }
        $centers[] = mapCenterDoc($doc);
    }
    return $centers;
}

function mapCenterDoc(array $doc): array
{
    $fields = $doc['fields'] ?? [];
    $docPath = (string) ($doc['name'] ?? '');
    return [
        'id' => basename($docPath),
        'path' => $docPath,
        'name' => getFieldString($fields, 'name'),
        'city' => getFieldString($fields, 'city'),
        'address' => getFieldString($fields, 'address'),
        'type' => getFieldString($fields, 'type'),
        'phone' => getFieldString($fields, 'phone'),
        'code' => getFieldString($fields, 'code'),
        'location' => getFieldString($fields, 'location'),
    ];
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

function getFieldString(array $fields, string $key): string
{
    $value = $fields[$key] ?? null;
    if (!is_array($value)) {
        return '';
    }
    if (isset($value['stringValue'])) {
        return (string) $value['stringValue'];
    }
    if (isset($value['integerValue'])) {
        return (string) $value['integerValue'];
    }
    if (isset($value['doubleValue'])) {
        return (string) $value['doubleValue'];
    }
    if (isset($value['timestampValue'])) {
        return (string) $value['timestampValue'];
    }
    return '';
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
