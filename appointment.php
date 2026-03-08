<?php
declare(strict_types=1);

$debug = isset($_GET['debug']) && $_GET['debug'] === '1';
if ($debug) {
    header('Content-Type: text/plain; charset=UTF-8');
} else {
    header('Content-Type: text/html; charset=UTF-8');
}

if (empty($_COOKIE['user_phone']) || empty($_COOKIE['session_token'])) {
    header('Location: preloader.php');
    exit;
}

$userPhone = normalizePhone((string) ($_COOKIE['user_phone'] ?? ''));
$userName = trim((string) ($_COOKIE['user_name'] ?? ''));
$userEmail = trim((string) ($_COOKIE['user_email'] ?? ''));
$centerId = trim((string) ($_GET['centerId'] ?? ($_GET['id'] ?? ($_POST['centerId'] ?? ($_POST['id'] ?? '')))));
$centerPath = trim((string) ($_GET['centerPath'] ?? ($_GET['path'] ?? ($_POST['centerPath'] ?? ($_POST['path'] ?? '')))));
$requestedTime = trim((string) ($_POST['appointmentTime'] ?? ''));
if ($centerId === '' && $centerPath !== '') {
    $centerId = basename($centerPath);
}

$credentialsPath = __DIR__ . '/govease-99021-firebase-adminsdk-fbsvc-fe9d642385.json';
$center = null;
$centerList = [];
$userProfile = null;
$userTickets = [];
$tokenInfo = null;
$errorMessage = '';

if (!is_readable($credentialsPath)) {
    $errorMessage = 'Service account JSON not readable.';
} else {
    try {
        $serviceAccount = json_decode((string) file_get_contents($credentialsPath), true, 512, JSON_THROW_ON_ERROR);
        $projectId = (string) ($serviceAccount['project_id'] ?? '');
        $clientEmail = (string) ($serviceAccount['client_email'] ?? '');
        $privateKey = (string) ($serviceAccount['private_key'] ?? '');

        if ($projectId === '' || $clientEmail === '' || $privateKey === '') {
            throw new RuntimeException('Invalid service account JSON.');
        }

        $token = getAccessToken($clientEmail, $privateKey);
        if ($centerId === '' && $centerPath === '') {
            $centerList = fetchCentersList($projectId, $token, 50);
        } else {
            $center = $centerPath !== '' ? fetchCenterByPath($projectId, $token, $centerPath) : fetchCenterById($projectId, $token, $centerId);

            if ($_SERVER['REQUEST_METHOD'] === 'POST' && $center) {
                $centerDocId = $center['id'] !== '' ? $center['id'] : $centerId;
                $tokenInfo = createToken(
                    $projectId,
                    $token,
                    $centerDocId,
                    $userPhone,
                    $userName,
                    $userEmail,
                    $requestedTime,
                    $centerPath
                );
                try {
                    appendUserTicket($projectId, $token, $userPhone, $center, $tokenInfo, $requestedTime);
                } catch (Throwable $e) {
                    error_log('Ticket append failed: ' . $e->getMessage());
                }
            }
        }

        $userProfile = fetchUserProfile($projectId, $token, $userPhone);
        if ($userProfile && !empty($userProfile['tickets'])) {
            $userTickets = $userProfile['tickets'];
        }
    } catch (Throwable $e) {
        if ($debug) {
            http_response_code(500);
            echo 'Appointment error: ' . $e->getMessage() . "\n";
            echo 'centerId=' . $centerId . "\n";
            echo 'centerPath=' . $centerPath . "\n";
            exit;
        }
        $errorMessage = 'Unable to load appointment details: ' . $e->getMessage();
        error_log('Appointment load failed: ' . $e->getMessage());
    }
}

function fetchCenterById(string $projectId, string $token, string $centerId): ?array
{
    $docRef = 'projects/' . $projectId . '/databases/(default)/documents/centers/' . $centerId;
    $payload = [
        'structuredQuery' => [
            'from' => [
                ['collectionId' => 'centers'],
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

    $url = 'https://firestore.googleapis.com/v1/projects/' . rawurlencode($projectId)
        . '/databases/(default)/documents:runQuery';
    $response = curlJson('POST', $url, $token, $payload);

    foreach ($response as $row) {
        $doc = $row['document'] ?? null;
        if (empty($doc['fields'])) {
            continue;
        }
        $fields = $doc['fields'];
        return [
            'id' => $centerId,
            'name' => getFieldString($fields, 'name'),
            'address' => getFieldString($fields, 'address'),
            'city' => getFieldString($fields, 'city'),
            'type' => getFieldString($fields, 'type'),
            'phone' => getFieldString($fields, 'phone'),
            'fields' => normalizeFields($fields),
        ];
    }

    return null;
}

function fetchCentersList(string $projectId, string $token, int $limit): array
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
            $fields = $doc['fields'];
            $docPath = (string) $doc['name'];
            $centers[] = [
                'id' => basename($docPath),
                'path' => $docPath,
                'name' => getFieldString($fields, 'name'),
                'city' => getFieldString($fields, 'city'),
                'type' => getFieldString($fields, 'type'),
            ];
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

function fetchCenterByPath(string $projectId, string $token, string $centerPath): ?array
{
    $path = preg_replace('#^projects/[^/]+/databases/\\(default\\)/documents/#', '', $centerPath);
    $url = 'https://firestore.googleapis.com/v1/projects/' . rawurlencode($projectId)
        . '/databases/(default)/documents/' . ltrim($path, '/');
    $response = curlJson('GET', $url, $token, []);

    if (empty($response['fields'])) {
        return null;
    }

    $fields = $response['fields'];
    $docPath = (string) ($response['name'] ?? $centerPath);
    return [
        'id' => basename($docPath),
        'name' => getFieldString($fields, 'name'),
        'address' => getFieldString($fields, 'address'),
        'city' => getFieldString($fields, 'city'),
        'type' => getFieldString($fields, 'type'),
        'phone' => getFieldString($fields, 'phone'),
        'fields' => normalizeFields($fields),
    ];
}

function createToken(
    string $projectId,
    string $token,
    string $centerId,
    string $userPhone,
    string $userName,
    string $userEmail,
    string $appointmentTime,
    string $centerPath
): array {
    $centerDocName = buildCenterDocName($projectId, $centerId, $centerPath);
    $centerDocUrl = 'https://firestore.googleapis.com/v1/' . $centerDocName;

    $attempts = 0;
    $maxAttempts = 5;
    while ($attempts < $maxAttempts) {
        $attempts++;
        $transaction = beginTransaction($projectId, $token);
        $doc = fetchDocument($centerDocUrl, $token, $transaction);
        $current = 0;
        if ($doc && !empty($doc['fields']['tokenCounter']['integerValue'])) {
            $currentValue = (string) $doc['fields']['tokenCounter']['integerValue'];
            if ($currentValue !== '' && ctype_digit($currentValue)) {
                $current = (int) $currentValue;
            }
        }

        $tokenNumber = $current + 1;
        $createdAt = gmdate('c');
        $tokenDocId = $centerId . '-' . $tokenNumber;
        $tokenDocName = 'projects/' . $projectId . '/databases/(default)/documents/token/' . $tokenDocId;

        $tokenFields = [
            'centerId' => ['stringValue' => $centerId],
            'userPhone' => ['stringValue' => $userPhone],
            'userName' => ['stringValue' => $userName],
            'userEmail' => ['stringValue' => $userEmail],
            'tokenNumber' => ['integerValue' => (string) $tokenNumber],
            'status' => ['stringValue' => 'active'],
            'createdAt' => ['timestampValue' => $createdAt],
            'appointmentTime' => ['stringValue' => $appointmentTime],
        ];

        $writes = [
            [
                'update' => [
                    'name' => $centerDocName,
                    'fields' => [
                        'tokenCounter' => ['integerValue' => (string) $tokenNumber],
                    ],
                ],
                'updateMask' => [
                    'fieldPaths' => ['tokenCounter'],
                ],
            ],
            [
                'update' => [
                    'name' => $tokenDocName,
                    'fields' => $tokenFields,
                ],
                'currentDocument' => [
                    'exists' => false,
                ],
            ],
        ];

        try {
            commitTransaction($projectId, $token, $transaction, $writes);
            return [
                'id' => $tokenDocId,
                'number' => $tokenNumber,
                'createdAt' => $createdAt,
                'appointmentTime' => $appointmentTime,
            ];
        } catch (Throwable $e) {
            error_log('Token transaction failed: ' . $e->getMessage());
            if ($attempts >= $maxAttempts) {
                throw $e;
            }
        }
    }

    throw new RuntimeException('Token creation failed.');
}

function appendUserTicket(
    string $projectId,
    string $token,
    string $userPhone,
    array $center,
    array $tokenInfo,
    string $appointmentTime
): void {
    $docId = $userPhone;
    $docUrl = 'https://firestore.googleapis.com/v1/projects/' . rawurlencode($projectId)
        . '/databases/(default)/documents/kyc_submissions/' . rawurlencode($docId);
    $existing = fetchDocument($docUrl, $token);
    $existingValues = [];

    if ($existing && !empty($existing['fields']['tickets']['arrayValue']['values'])) {
        $values = $existing['fields']['tickets']['arrayValue']['values'];
        if (is_array($values)) {
            $existingValues = $values;
        }
    }

    $newTicket = [
        'mapValue' => [
            'fields' => [
                'tokenId' => ['stringValue' => (string) $tokenInfo['id']],
                'tokenNumber' => ['integerValue' => (string) $tokenInfo['number']],
                'centerId' => ['stringValue' => (string) ($center['id'] ?? '')],
                'centerName' => ['stringValue' => (string) ($center['name'] ?? '')],
                'appointmentTime' => ['stringValue' => $appointmentTime],
                'createdAt' => ['timestampValue' => (string) $tokenInfo['createdAt']],
            ],
        ],
    ];

    $payload = [
        'fields' => [
            'phone' => ['stringValue' => $userPhone],
            'tickets' => [
                'arrayValue' => [
                    'values' => array_values(array_merge($existingValues, [$newTicket])),
                ],
            ],
        ],
    ];

    if ($existing === null) {
        curlJson('PATCH', $docUrl, $token, $payload);
    } else {
        $updateUrl = $docUrl . '?updateMask.fieldPaths=tickets&updateMask.fieldPaths=phone';
        curlJson('PATCH', $updateUrl, $token, $payload);
    }
}

function fetchDocument(string $url, string $token, string $transaction = ''): ?array
{
    if ($transaction !== '') {
        $separator = strpos($url, '?') === false ? '?' : '&';
        $url .= $separator . 'transaction=' . rawurlencode($transaction);
    }
    $ch = curl_init($url);
    $options = [
        CURLOPT_CUSTOMREQUEST => 'GET',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $token,
        ],
        CURLOPT_TIMEOUT => 15,
    ];
    curl_setopt_array($ch, $options);

    $raw = curl_exec($ch);
    if ($raw === false) {
        throw new RuntimeException('Request failed: ' . curl_error($ch));
    }
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($status === 404) {
        return null;
    }

    $data = json_decode($raw, true);
    if (!is_array($data) || $status >= 400) {
        throw new RuntimeException('Firestore API error: ' . $raw);
    }

    return $data;
}

function fetchUserProfile(string $projectId, string $token, string $userPhone): ?array
{
    $docUrl = 'https://firestore.googleapis.com/v1/projects/' . rawurlencode($projectId)
        . '/databases/(default)/documents/kyc_submissions/' . rawurlencode($userPhone);
    $doc = fetchDocument($docUrl, $token);
    if (!$doc || empty($doc['fields'])) {
        return null;
    }
    $fields = $doc['fields'];
    $tickets = [];
    if (!empty($fields['tickets']['arrayValue']['values']) && is_array($fields['tickets']['arrayValue']['values'])) {
        foreach ($fields['tickets']['arrayValue']['values'] as $value) {
            if (!is_array($value) || empty($value['mapValue']['fields'])) {
                continue;
            }
            $ticketFields = $value['mapValue']['fields'];
            $tickets[] = [
                'tokenId' => getFieldString($ticketFields, 'tokenId'),
                'tokenNumber' => getFieldString($ticketFields, 'tokenNumber'),
                'centerId' => getFieldString($ticketFields, 'centerId'),
                'centerName' => getFieldString($ticketFields, 'centerName'),
                'appointmentTime' => getFieldString($ticketFields, 'appointmentTime'),
                'createdAt' => getFieldString($ticketFields, 'createdAt'),
            ];
        }
    }

    return [
        'fullName' => getFieldString($fields, 'fullName'),
        'name' => getFieldString($fields, 'name'),
        'email' => getFieldString($fields, 'email'),
        'phone' => getFieldString($fields, 'phone'),
        'tickets' => $tickets,
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

function normalizePhone(string $phone): string
{
    $value = trim($phone);
    if ($value === '') {
        return '';
    }
    return $value[0] === '+' ? $value : '+' . $value;
}

function normalizeFields(array $fields): array
{
    $normalized = [];
    foreach ($fields as $key => $value) {
        if (!is_array($value)) {
            continue;
        }
        $normalized[$key] = getFieldScalarFromValue($value);
    }
    return $normalized;
}

function getFieldScalarFromValue(array $value): string
{
    if (isset($value['stringValue'])) {
        return (string) $value['stringValue'];
    }
    if (isset($value['integerValue'])) {
        return (string) $value['integerValue'];
    }
    if (isset($value['doubleValue'])) {
        return (string) $value['doubleValue'];
    }
    if (isset($value['booleanValue'])) {
        return $value['booleanValue'] ? 'true' : 'false';
    }
    if (isset($value['timestampValue'])) {
        return (string) $value['timestampValue'];
    }
    if (isset($value['arrayValue']['values']) && is_array($value['arrayValue']['values'])) {
        $items = [];
        foreach ($value['arrayValue']['values'] as $item) {
            $items[] = getFieldScalarFromValue($item);
        }
        return implode(', ', array_filter($items, static fn ($v) => $v !== ''));
    }
    if (isset($value['mapValue'])) {
        return json_encode($value['mapValue']);
    }
    return '';
}

function buildCenterDocName(string $projectId, string $centerId, string $centerPath): string
{
    if ($centerPath !== '') {
        if (str_starts_with($centerPath, 'projects/')) {
            return $centerPath;
        }
        return 'projects/' . $projectId . '/databases/(default)/documents/' . ltrim($centerPath, '/');
    }
    return 'projects/' . $projectId . '/databases/(default)/documents/centers/' . $centerId;
}

function beginTransaction(string $projectId, string $token): string
{
    $url = 'https://firestore.googleapis.com/v1/projects/' . rawurlencode($projectId)
        . '/databases/(default)/documents:beginTransaction';
    $response = curlJson('POST', $url, $token, []);
    $transaction = (string) ($response['transaction'] ?? '');
    if ($transaction === '') {
        throw new RuntimeException('Unable to begin transaction.');
    }
    return $transaction;
}

function commitTransaction(string $projectId, string $token, string $transaction, array $writes): void
{
    $url = 'https://firestore.googleapis.com/v1/projects/' . rawurlencode($projectId)
        . '/databases/(default)/documents:commit';
    $payload = [
        'transaction' => $transaction,
        'writes' => $writes,
    ];
    $response = curlJson('POST', $url, $token, $payload);
    if (empty($response['writeResults'])) {
        throw new RuntimeException('Commit failed.');
    }
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>GovEase - Appointment</title>
  <link rel="stylesheet" href="style.css">
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');

    :root {
        --primary: #0a6476;
        --primary-light: #e6f3f5;
        --text-dark: #1f2937;
        --text-muted: #6b7280;
        --bg-color: #f3f6f8;
        --white: #ffffff;
        --border-color: #e5e7eb;
        --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        --radius-md: 0.5rem;
        --radius-lg: 0.75rem;
        --radius-xl: 1rem;
    }

    * {
        box-sizing: border-box;
        margin: 0;
        padding: 0;
        font-family: 'Inter', sans-serif;
    }

    body {
        background-color: var(--bg-color);
        color: var(--text-dark);
        font-size: 14px;
        line-height: 1.5;
        -webkit-font-smoothing: antialiased;
    }

    .container {
        width: 100%;
        max-width: 480px;
        margin: 0 auto;
        background: var(--white);
        min-height: 100vh;
        position: relative;
        box-shadow: var(--shadow-md);
        padding: 1.5rem 1.25rem 2.5rem;
    }

    @media (min-width: 481px) {
        body {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background-color: #0f172a;
        }

        .container {
            min-height: 850px;
            max-height: 90vh;
            border-radius: var(--radius-xl);
            overflow: hidden;
        }
    }

    .header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1.5rem;
    }

    .header a {
        text-decoration: none;
        color: var(--primary);
        font-weight: 600;
    }

    .card {
        border: 1px solid var(--border-color);
        border-radius: var(--radius-lg);
        padding: 1.25rem;
        margin-bottom: 1rem;
        background: var(--white);
    }

    .card h2 {
        font-size: 1.2rem;
        margin-bottom: 0.4rem;
    }

    .muted {
        color: var(--text-muted);
        font-size: 0.85rem;
    }

    .btn {
        width: 100%;
        border: none;
        border-radius: var(--radius-md);
        padding: 0.8rem 1rem;
        background: var(--primary);
        color: var(--white);
        font-weight: 600;
        cursor: pointer;
    }

    .token-box {
        background: var(--primary-light);
        border-radius: var(--radius-md);
        padding: 0.9rem;
        margin-top: 0.75rem;
    }

    .token-number {
        font-size: 1.6rem;
        font-weight: 700;
        color: var(--primary);
    }

    .center-fields {
        margin-top: 0.6rem;
        display: grid;
        gap: 0.2rem;
    }

    .center-field {
        font-size: 0.78rem;
        color: var(--text-muted);
    }

    .center-list {
        display: grid;
        gap: 0.75rem;
        margin-top: 1rem;
    }

    .center-link {
        display: flex;
        justify-content: space-between;
        align-items: center;
        border: 1px solid var(--border-color);
        border-radius: var(--radius-md);
        padding: 0.85rem 1rem;
        text-decoration: none;
        color: var(--text-dark);
    }

    .center-link:hover {
        border-color: var(--primary);
    }

    .center-action {
        font-size: 0.75rem;
        font-weight: 600;
        color: var(--primary);
        background-color: var(--primary-light);
        padding: 0.3rem 0.6rem;
        border-radius: 9999px;
    }
  </style>
</head>
<body>
  <div class="container has-bottom-nav">
    <div class="header">
      <h1>Appointment</h1>
      <a href="home.php">Back</a>
    </div>

    <?php if ($errorMessage !== ''): ?>
      <div class="card">
        <p class="muted"><?php echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?></p>
      </div>
    <?php endif; ?>

    <?php if ($userProfile): ?>
      <div class="card">
        <h2>User Details</h2>
        <p class="muted">
          <?php
            $userDisplayName = $userProfile['fullName'] !== '' ? $userProfile['fullName'] : $userProfile['name'];
            echo htmlspecialchars($userDisplayName !== '' ? $userDisplayName : 'User', ENT_QUOTES, 'UTF-8');
          ?>
        </p>
        <?php if ($userProfile['phone'] !== ''): ?>
          <p class="muted">Phone: <?php echo htmlspecialchars($userProfile['phone'], ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>
        <?php if ($userProfile['email'] !== ''): ?>
          <p class="muted">Email: <?php echo htmlspecialchars($userProfile['email'], ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <?php if ($center): ?>
      <div class="card">
        <h2><?php echo htmlspecialchars($center['name'] !== '' ? $center['name'] : 'Center', ENT_QUOTES, 'UTF-8'); ?></h2>
        <p class="muted"><?php echo htmlspecialchars(trim($center['type'] . ($center['city'] !== '' ? ' • ' . $center['city'] : '')), ENT_QUOTES, 'UTF-8'); ?></p>
        <?php if ($center['address'] !== ''): ?>
          <p class="muted"><?php echo htmlspecialchars($center['address'], ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>
        <?php if ($center['phone'] !== ''): ?>
          <p class="muted">Contact: <?php echo htmlspecialchars($center['phone'], ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>
        <?php if (!empty($center['fields'])): ?>
          <div class="center-fields">
            <?php foreach ($center['fields'] as $fieldKey => $fieldValue): ?>
              <?php if ($fieldValue === ''): ?>
                <?php continue; ?>
              <?php endif; ?>
              <div class="center-field"><?php echo htmlspecialchars($fieldKey . ': ' . $fieldValue, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <div class="card">
        <h3>Get your token</h3>
        <p class="muted">Select a time and generate a token for this center.</p>
        <form method="post">
          <input type="hidden" name="centerId" value="<?php echo htmlspecialchars($centerId, ENT_QUOTES, 'UTF-8'); ?>">
          <input type="hidden" name="centerPath" value="<?php echo htmlspecialchars($centerPath, ENT_QUOTES, 'UTF-8'); ?>">
          <label class="muted" for="appointmentTime" style="display:block; margin: 0.75rem 0 0.35rem;">Preferred time</label>
          <input id="appointmentTime" name="appointmentTime" type="time" value="<?php echo htmlspecialchars($requestedTime, ENT_QUOTES, 'UTF-8'); ?>" style="width:100%; padding:0.65rem; border:1px solid var(--border-color); border-radius: var(--radius-md); margin-bottom: 0.75rem;">
          <button class="btn" type="submit">Generate Token</button>
        </form>

        <?php if ($tokenInfo): ?>
          <div class="token-box">
            <div class="token-number"><?php echo (int) $tokenInfo['number']; ?></div>
            <div class="muted">Token ID: <?php echo htmlspecialchars($tokenInfo['id'], ENT_QUOTES, 'UTF-8'); ?></div>
            <div class="muted">Time: <?php echo htmlspecialchars($tokenInfo['createdAt'], ENT_QUOTES, 'UTF-8'); ?></div>
            <div class="muted">Estimated wait: <?php echo (int) $tokenInfo['number'] * 5; ?> minutes</div>
            <?php if ($tokenInfo['appointmentTime'] !== ''): ?>
              <div class="muted">Preferred: <?php echo htmlspecialchars($tokenInfo['appointmentTime'], ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>
            <div style="margin-top: 0.75rem;">
              <a class="center-link" href="appointments.php">
                <div>
                  <strong>Book another center</strong>
                  <div class="muted">Choose a new center for another booking.</div>
                </div>
                <span class="center-action">Browse</span>
              </a>
            </div>
          </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <?php if (!empty($userTickets)): ?>
      <div class="card">
        <h2>Bookings</h2>
        <div class="center-list">
          <?php foreach ($userTickets as $ticket): ?>
            <div class="center-link">
              <div>
                <strong><?php echo htmlspecialchars($ticket['centerName'] !== '' ? $ticket['centerName'] : $ticket['centerId'], ENT_QUOTES, 'UTF-8'); ?></strong>
                <div class="muted">Token: <?php echo htmlspecialchars($ticket['tokenNumber'] !== '' ? $ticket['tokenNumber'] : $ticket['tokenId'], ENT_QUOTES, 'UTF-8'); ?></div>
                <?php if ($ticket['appointmentTime'] !== ''): ?>
                  <div class="muted">Time: <?php echo htmlspecialchars($ticket['appointmentTime'], ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endif; ?>
                <?php if ($ticket['createdAt'] !== ''): ?>
                  <div class="muted">Created: <?php echo htmlspecialchars($ticket['createdAt'], ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
        <div style="margin-top: 1rem;">
          <a class="center-link" href="appointments.php">
            <div>
              <strong>Book another center</strong>
              <div class="muted">Choose a new center for another booking.</div>
            </div>
            <span class="center-action">Browse</span>
          </a>
        </div>
      </div>
    <?php elseif ($userProfile): ?>
      <div class="card">
        <h2>Bookings</h2>
        <p class="muted">No bookings yet. Create your first appointment below.</p>
      </div>
    <?php endif; ?>

    <?php if (!$center && !empty($centerList)): ?>
      <div class="card">
        <h2>Select a center</h2>
        <p class="muted">Choose a center to book a token.</p>
        <div class="center-list">
          <?php foreach ($centerList as $item): ?>
            <a class="center-link" href="appointment.php?centerId=<?php echo urlencode($item['id']); ?>&centerPath=<?php echo urlencode($item['path']); ?>">
              <div>
                <strong><?php echo htmlspecialchars($item['name'] !== '' ? $item['name'] : 'Center', ENT_QUOTES, 'UTF-8'); ?></strong>
                <div class="muted"><?php echo htmlspecialchars(trim($item['type'] . ($item['city'] !== '' ? ' • ' . $item['city'] : '')), ENT_QUOTES, 'UTF-8'); ?></div>
              </div>
              <span class="center-action">Open</span>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
    <?php elseif (!$center): ?>
      <div class="card">
        <p class="muted">Center not found. Please select a center from the dashboard.</p>
      </div>
    <?php endif; ?>

    <div class="bottom-nav">
      <a href="home.php" class="nav-item">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
        Home
      </a>
      <a href="appointments.php" class="nav-item active-bg">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="4" rx="2" ry="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/><path d="M8 14h.01"/><path d="M12 14h.01"/><path d="M16 14h.01"/><path d="M8 18h.01"/><path d="M12 18h.01"/><path d="M16 18h.01"/></svg>
        Appointments
      </a>
      <a href="scan.php" class="nav-item nav-primary">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7V5a2 2 0 0 1 2-2h2"/><path d="M17 3h2a2 2 0 0 1 2 2v2"/><path d="M21 17v2a2 2 0 0 1-2 2h-2"/><path d="M7 21H5a2 2 0 0 1-2-2v-2"/><rect x="7" y="7" width="10" height="10" rx="1"/></svg>
        Scan
      </a>
      <a href="chat.php" class="nav-item">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a4 4 0 0 1-4 4H7l-4 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4z"/></svg>
        Assistant
      </a>
      <a href="tickets.php" class="nav-item">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2Z"/><path d="M13 5v2"/><path d="M13 17v2"/><path d="M13 11v2"/></svg>
        Tickets
      </a>
      <a href="profile.php" class="nav-item">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
        Profile
      </a>
    </div>
  </div>
</body>
</html>
