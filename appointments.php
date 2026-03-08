<?php
declare(strict_types=1);

if (empty($_COOKIE['user_phone']) || empty($_COOKIE['session_token'])) {
    header('Location: preloader.php');
    exit;
}

$userPhone = normalizePhone((string) ($_COOKIE['user_phone'] ?? ''));
$credentialsPath = resolveCredentialsPath();
$centers = [];
$userTickets = [];
$userProfile = null;
$projectId = '';
$token = '';
$apiKey = '';
$authMode = 'service';
$errorMessage = '';

if ($credentialsPath !== '') {
    try {
        $serviceAccount = json_decode((string) file_get_contents($credentialsPath), true);
        $projectId = (string) ($serviceAccount['project_id'] ?? '');
        $clientEmail = (string) ($serviceAccount['client_email'] ?? '');
        $privateKey = (string) ($serviceAccount['private_key'] ?? '');

        if ($projectId === '' || $clientEmail === '' || $privateKey === '') {
            throw new RuntimeException('Invalid service account JSON.');
        }

        try {
            $token = getAccessToken($clientEmail, $privateKey);
        } catch (Throwable $e) {
            $authMode = 'api';
        }
    } catch (Throwable $e) {
        $authMode = 'api';
    }
} else {
    $authMode = 'api';
}

if ($authMode === 'api') {
    $config = resolveFirebaseConfig();
    $projectId = $config['projectId'];
    $apiKey = $config['apiKey'];
    if ($projectId === '' || $apiKey === '') {
        $errorMessage = 'Unable to load centers.';
    }
}

if ($errorMessage === '') {
    try {
        $centers = $authMode === 'api'
            ? fetchCentersListWithApiKey($projectId, $apiKey, 200)
            : fetchCentersList($projectId, $token, 200);
        $userProfile = $authMode === 'api'
            ? fetchUserProfileWithApiKey($projectId, $apiKey, $userPhone)
            : fetchUserProfile($projectId, $token, $userPhone);
        if ($userProfile && !empty($userProfile['tickets'])) {
            $userTickets = $userProfile['tickets'];
        }
    } catch (Throwable $e) {
        $errorMessage = 'Unable to load centers.';
        error_log('Appointments list failed: ' . $e->getMessage());
    }
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

function resolveCredentialsPath(): string
{
    $candidates = [
        __DIR__ . '/govease-99021-firebase-adminsdk-fbsvc-fe9d642385.json',
        dirname(__DIR__) . '/govease-99021-firebase-adminsdk-fbsvc-fe9d642385.json',
        getcwd() . '/govease-99021-firebase-adminsdk-fbsvc-fe9d642385.json',
    ];
    foreach ($candidates as $path) {
        if ($path !== false && is_readable($path)) {
            return $path;
        }
    }
    return '';
}

function resolveFirebaseConfig(): array
{
    $candidates = [
        __DIR__ . '/firebase.json',
        dirname(__DIR__) . '/firebase.json',
        getcwd() . '/firebase.json',
    ];
    foreach ($candidates as $path) {
        if ($path !== false && is_readable($path)) {
            $config = json_decode((string) file_get_contents($path), true);
            if (is_array($config)) {
                return [
                    'projectId' => (string) ($config['projectId'] ?? ''),
                    'apiKey' => (string) ($config['apiKey'] ?? ''),
                ];
            }
        }
    }
    return ['projectId' => '', 'apiKey' => ''];
}

function appendApiKey(string $url, string $apiKey): string
{
    $separator = strpos($url, '?') === false ? '?' : '&';
    return $url . $separator . 'key=' . rawurlencode($apiKey);
}

function curlJsonNoAuth(string $method, string $url, array $payload): array
{
    $ch = curl_init($url);
    $options = [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
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

function fetchCentersListWithApiKey(string $projectId, string $apiKey, int $limit): array
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
        $url = appendApiKey($url, $apiKey);
        $response = curlJsonNoAuth('GET', $url, []);

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

function fetchDocumentWithApiKey(string $url, string $apiKey): ?array
{
    $url = appendApiKey($url, $apiKey);
    $ch = curl_init($url);
    $options = [
        CURLOPT_CUSTOMREQUEST => 'GET',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
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

function fetchUserProfileWithApiKey(string $projectId, string $apiKey, string $userPhone): ?array
{
    if ($userPhone === '') {
        return null;
    }

    $docUrl = 'https://firestore.googleapis.com/v1/projects/' . rawurlencode($projectId)
        . '/databases/(default)/documents/kyc_submissions/' . rawurlencode($userPhone);
    $doc = fetchDocumentWithApiKey($docUrl, $apiKey);
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

function fetchDocument(string $url, string $token): ?array
{
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
    if ($userPhone === '') {
        return null;
    }

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
  <title>GovEase - Appointments</title>
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

    .muted {
        color: var(--text-muted);
        font-size: 0.85rem;
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
      <h1>Appointments</h1>
      <a href="home.php">Back</a>
    </div>

    <div class="search-bar-container" style="padding: 0 0 1rem;">
      <input type="text" id="appointmentsSearch" class="search-input" placeholder="Search centers or your bookings">
    </div>

    <?php if ($errorMessage !== ''): ?>
      <div class="card">
        <p class="muted"><?php echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?></p>
      </div>
    <?php else: ?>
      <?php if (!empty($userTickets)): ?>
        <div class="card">
          <h2>Your bookings</h2>
          <p class="muted">Your recent tokens for booked centers.</p>
          <div class="center-list">
            <?php foreach ($userTickets as $ticket): ?>
              <?php
                $ticketLabel = trim(($ticket['centerName'] !== '' ? $ticket['centerName'] : $ticket['centerId']) . ' ' . ($ticket['tokenNumber'] !== '' ? $ticket['tokenNumber'] : $ticket['tokenId']) . ' ' . $ticket['appointmentTime']);
              ?>
              <div class="center-link js-search-item" data-search="<?php echo htmlspecialchars(strtolower($ticketLabel), ENT_QUOTES, 'UTF-8'); ?>">
                <div>
                <strong><?php echo htmlspecialchars($ticket['centerName'] !== '' ? $ticket['centerName'] : $ticket['centerId'], ENT_QUOTES, 'UTF-8'); ?></strong>
                <div class="muted">Token: <?php echo htmlspecialchars($ticket['tokenNumber'] !== '' ? $ticket['tokenNumber'] : $ticket['tokenId'], ENT_QUOTES, 'UTF-8'); ?></div>
                <?php if ($ticket['tokenNumber'] !== '' && ctype_digit($ticket['tokenNumber'])): ?>
                  <div class="muted">Estimated wait: <?php echo (int) $ticket['tokenNumber'] * 5; ?> minutes</div>
                <?php endif; ?>
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
        </div>
      <?php elseif ($userProfile): ?>
        <div class="card">
          <h2>Your bookings</h2>
          <p class="muted">No bookings yet. Book your first center below.</p>
        </div>
      <?php endif; ?>

      <div class="card">
        <h2>Choose a center</h2>
        <p class="muted">Book an appointment for any center below.</p>
        <div class="center-list">
          <?php foreach ($centers as $center): ?>
            <?php
              $centerLabel = trim($center['name'] . ' ' . $center['type'] . ' ' . $center['city']);
            ?>
            <a class="center-link js-search-item" data-search="<?php echo htmlspecialchars(strtolower($centerLabel), ENT_QUOTES, 'UTF-8'); ?>" href="appointment.php?centerId=<?php echo urlencode($center['id']); ?>&centerPath=<?php echo urlencode($center['path']); ?>">
              <div>
                <strong><?php echo htmlspecialchars($center['name'] !== '' ? $center['name'] : 'Center', ENT_QUOTES, 'UTF-8'); ?></strong>
                <div class="muted"><?php echo htmlspecialchars(trim($center['type'] . ($center['city'] !== '' ? ' • ' . $center['city'] : '')), ENT_QUOTES, 'UTF-8'); ?></div>
              </div>
              <span class="center-action">Book</span>
            </a>
          <?php endforeach; ?>
        </div>
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
  <script>
    const appointmentsSearch = document.getElementById('appointmentsSearch');
    if (appointmentsSearch) {
      const items = Array.from(document.querySelectorAll('.js-search-item'));
      appointmentsSearch.addEventListener('input', (event) => {
        const query = event.target.value.trim().toLowerCase();
        items.forEach((item) => {
          const haystack = item.dataset.search || '';
          item.style.display = haystack.includes(query) ? '' : 'none';
        });
      });
    }
  </script>
</body>
</html>
