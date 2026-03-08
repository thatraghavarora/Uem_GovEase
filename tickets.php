<?php
declare(strict_types=1);

if (empty($_COOKIE['user_phone']) || empty($_COOKIE['session_token'])) {
    header('Location: preloader.php');
    exit;
}

$userPhone = normalizePhone((string) ($_COOKIE['user_phone'] ?? ''));
$credentialsPath = resolveCredentialsPath();
$userTickets = [];
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
        $errorMessage = 'Unable to load tickets.';
    }
}

if ($errorMessage === '') {
    try {
        $userProfile = $authMode === 'api'
            ? fetchUserProfileWithApiKey($projectId, $apiKey, $userPhone)
            : fetchUserProfile($projectId, $token, $userPhone);
        if ($userProfile && !empty($userProfile['tickets'])) {
            $userTickets = $userProfile['tickets'];
        }
    } catch (Throwable $e) {
        $errorMessage = 'Unable to load tickets.';
        error_log('Tickets load failed: ' . $e->getMessage());
    }
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>GovEase - Tickets</title>
  <link rel="stylesheet" href="style.css">
  <style>
    .page-wrapper {
        padding-top: 2rem;
        padding-bottom: 80px;
    }

    .page-header {
      padding: 0 1.25rem 1.5rem;
    }

    .page-header h1 {
      font-size: 1.75rem;
      color: #0b2239;
      margin-bottom: 0.25rem;
    }

    .page-header p {
      color: #5c728a;
      font-size: 0.95rem;
    }

    .tickets-card {
      border: 1px solid var(--border-color);
      border-radius: var(--radius-lg);
      padding: 1.5rem 1.25rem;
      margin: 0 1.25rem 1rem;
      background-color: var(--white);
    }

    .tickets-list {
      display: grid;
      gap: 0.75rem;
      margin-top: 1rem;
    }

    .ticket-item {
      border: 1px solid var(--border-color);
      border-radius: var(--radius-md);
      padding: 0.85rem 1rem;
      background-color: #f8fafc;
    }

    .ticket-title {
      font-weight: 600;
      color: #0b2239;
      margin-bottom: 0.25rem;
    }

    .ticket-meta {
      color: #64748b;
      font-size: 0.85rem;
    }
  </style>
</head>
<body>
  <div class="container page-wrapper has-bottom-nav">
    <div class="dashboard-scroll-area">
      <div class="page-header">
        <h1>Tickets</h1>
        <p>Your latest token history</p>
      </div>

      <div class="search-bar-container">
        <input type="text" id="ticketSearch" class="search-input" placeholder="Search center, token, or time">
      </div>

      <?php if ($errorMessage !== ''): ?>
        <div class="tickets-card">
          <p class="ticket-meta"><?php echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?></p>
        </div>
      <?php elseif (empty($userTickets)): ?>
        <div class="tickets-card">
          <p class="ticket-meta">No tickets yet. Book a center to generate your first token.</p>
        </div>
      <?php else: ?>
        <div class="tickets-card">
          <h2>My tickets</h2>
          <p class="ticket-meta">All tokens generated with your account.</p>
          <div class="tickets-list">
            <?php foreach ($userTickets as $ticket): ?>
              <?php
                $ticketLabel = trim(($ticket['centerName'] !== '' ? $ticket['centerName'] : $ticket['centerId']) . ' ' . ($ticket['tokenNumber'] !== '' ? $ticket['tokenNumber'] : $ticket['tokenId']) . ' ' . $ticket['appointmentTime']);
              ?>
              <div class="ticket-item js-ticket" data-search="<?php echo htmlspecialchars(strtolower($ticketLabel), ENT_QUOTES, 'UTF-8'); ?>">
                <div class="ticket-title"><?php echo htmlspecialchars($ticket['centerName'] !== '' ? $ticket['centerName'] : $ticket['centerId'], ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="ticket-meta">Token: <?php echo htmlspecialchars($ticket['tokenNumber'] !== '' ? $ticket['tokenNumber'] : $ticket['tokenId'], ENT_QUOTES, 'UTF-8'); ?></div>
                <?php if ($ticket['tokenNumber'] !== '' && ctype_digit($ticket['tokenNumber'])): ?>
                  <div class="ticket-meta">Estimated wait: <?php echo (int) $ticket['tokenNumber'] * 5; ?> minutes</div>
                <?php endif; ?>
                <?php if ($ticket['appointmentTime'] !== ''): ?>
                  <div class="ticket-meta">Time: <?php echo htmlspecialchars($ticket['appointmentTime'], ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endif; ?>
                <?php if ($ticket['createdAt'] !== ''): ?>
                  <div class="ticket-meta">Created: <?php echo htmlspecialchars($ticket['createdAt'], ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>
    </div>

    <div class="bottom-nav">
      <a href="home.php" class="nav-item">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
        Home
      </a>
      <a href="appointments.php" class="nav-item">
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
      <a href="tickets.php" class="nav-item active-bg">
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
    const ticketSearch = document.getElementById('ticketSearch');
    if (ticketSearch) {
      const items = Array.from(document.querySelectorAll('.js-ticket'));
      ticketSearch.addEventListener('input', (event) => {
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
