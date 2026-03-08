<?php
declare(strict_types=1);

if (empty($_COOKIE['user_phone']) || empty($_COOKIE['session_token'])) {
    header('Location: preloader.php');
    exit;
}

$userPhoneRaw = trim((string) ($_COOKIE['user_phone'] ?? ''));
$userPhone = normalizePhone($userPhoneRaw);
$cookieName = trim((string) ($_COOKIE['user_name'] ?? ''));
$cookieEmail = trim((string) ($_COOKIE['user_email'] ?? ''));

$profile = [
    'fullName' => '',
    'name' => $cookieName,
    'email' => $cookieEmail,
    'phone' => $userPhone,
];
$profileFound = false;
$profileError = '';

$credentialsPath = __DIR__ . '/govease-99021-firebase-adminsdk-fbsvc-fe9d642385.json';
if (is_readable($credentialsPath)) {
    try {
        $serviceAccount = json_decode((string) file_get_contents($credentialsPath), true, 512, JSON_THROW_ON_ERROR);
        $projectId = (string) ($serviceAccount['project_id'] ?? '');
        $clientEmail = (string) ($serviceAccount['client_email'] ?? '');
        $privateKey = (string) ($serviceAccount['private_key'] ?? '');

        if ($projectId !== '' && $clientEmail !== '' && $privateKey !== '') {
            $token = getAccessToken($clientEmail, $privateKey);
            $phoneCandidates = buildPhoneCandidates($userPhone);
            foreach ($phoneCandidates as $candidate) {
                if ($candidate === '') {
                    continue;
                }
                $payload = [
                    'structuredQuery' => [
                        'from' => [
                            ['collectionId' => 'kyc_submissions'],
                        ],
                        'where' => [
                            'fieldFilter' => [
                                'field' => ['fieldPath' => 'phone'],
                                'op' => 'EQUAL',
                                'value' => ['stringValue' => $candidate],
                            ],
                        ],
                        'orderBy' => [
                            [
                                'field' => ['fieldPath' => 'createdAt'],
                                'direction' => 'DESCENDING',
                            ],
                        ],
                        'limit' => 1,
                    ],
                ];
                $url = 'https://firestore.googleapis.com/v1/projects/' . rawurlencode($projectId)
                    . '/databases/(default)/documents:runQuery';
                $response = curlJson('POST', $url, $token, $payload);
                foreach ($response as $row) {
                    if (empty($row['document']['fields'])) {
                        continue;
                    }
                    $fields = $row['document']['fields'];
                    $profile['fullName'] = (string) ($fields['fullName']['stringValue'] ?? '');
                    $profile['name'] = (string) ($fields['name']['stringValue'] ?? $cookieName);
                    $profile['email'] = (string) ($fields['email']['stringValue'] ?? $cookieEmail);
                    $profile['phone'] = (string) ($fields['phone']['stringValue'] ?? $userPhone);
                    $profileFound = true;
                    break 2;
                }
            }

            if (!$profileFound) {
                $docCandidates = $phoneCandidates !== [] ? $phoneCandidates : [$userPhone];
                foreach ($docCandidates as $candidate) {
                    if ($candidate === '') {
                        continue;
                    }
                    $docUrl = 'https://firestore.googleapis.com/v1/projects/' . rawurlencode($projectId)
                        . '/databases/(default)/documents/kyc_submissions/' . rawurlencode($candidate);
                    $doc = fetchDocument($docUrl, $token);
                    if (!$doc || empty($doc['fields'])) {
                        continue;
                    }
                    $fields = $doc['fields'];
                    $profile['fullName'] = (string) ($fields['fullName']['stringValue'] ?? '');
                    $profile['name'] = (string) ($fields['name']['stringValue'] ?? $cookieName);
                    $profile['email'] = (string) ($fields['email']['stringValue'] ?? $cookieEmail);
                    $profile['phone'] = (string) ($fields['phone']['stringValue'] ?? $candidate);
                    $profileFound = true;
                    break;
                }
            }
        } else {
            $profileError = 'Service account JSON is missing required fields.';
        }
    } catch (Throwable $e) {
        error_log('Profile load failed: ' . $e->getMessage());
        $profileError = 'Unable to load profile details.';
    }
} else {
    $profileError = 'Service account JSON not readable.';
}

$displayName = $profile['fullName'] !== '' ? $profile['fullName'] : $profile['name'];
$displayEmail = $profile['email'];
$displayPhone = $profile['phone'];
$initials = buildInitials($displayName !== '' ? $displayName : 'User');

function buildPhoneCandidates(string $phone): array
{
    $raw = trim($phone);
    $digits = preg_replace('/\D+/', '', $raw);
    $candidates = [];
    if ($raw !== '') {
        $candidates[] = $raw;
    }
    if ($digits !== '' && $digits !== $raw) {
        $candidates[] = $digits;
    }
    if ($digits !== '' && $raw !== '+' . $digits) {
        $candidates[] = '+' . $digits;
    }
    if ($digits !== '' && strlen($digits) === 10) {
        $candidates[] = '91' . $digits;
        $candidates[] = '+91' . $digits;
    }
    return array_values(array_unique($candidates));
}

function normalizePhone(string $phone): string
{
    $value = trim($phone);
    if ($value === '') {
        return '';
    }
    return $value[0] === '+' ? $value : '+' . $value;
}

function buildInitials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name));
    $letters = '';
    foreach ($parts as $part) {
        if ($part === '') {
            continue;
        }
        $letters .= strtoupper($part[0]);
        if (strlen($letters) >= 2) {
            break;
        }
    }
    return $letters !== '' ? $letters : 'U';
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>GovEase - Profile</title>
  <link rel="stylesheet" href="style.css">
  <style>
    /* Profile Specifics */
    .page-wrapper {
        padding-top: 2rem;
        padding-bottom: 80px; /* Space for bottom nav */
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

    .profile-card {
      background-color: var(--primary);
      border-radius: var(--radius-lg);
      padding: 1.5rem;
      margin: 0 1.25rem 1.5rem;
      color: var(--white);
    }

    .profile-card-subtitle {
      font-size: 0.85rem;
      color: rgba(255, 255, 255, 0.8);
      margin-bottom: 0.5rem;
    }

    .profile-card-title {
      font-size: 1.25rem;
      font-weight: 700;
      margin-bottom: 0.25rem;
    }

    .profile-card-email {
      font-size: 0.9rem;
      color: rgba(255, 255, 255, 0.9);
      margin-bottom: 1.25rem;
    }

    .profile-actions {
      display: flex;
      gap: 0.75rem;
    }

    .btn-profile-outline {
      border: 1px solid rgba(255, 255, 255, 0.5);
      background: transparent;
      color: var(--white);
      padding: 0.5rem 1rem;
      border-radius: var(--radius-full);
      font-size: 0.85rem;
    }

    .btn-profile-outline:hover {
      background: rgba(255, 255, 255, 0.1);
    }

    .details-list {
      padding: 0 1.25rem;
    }

    .detail-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 1rem 1.25rem;
      border: 1px solid var(--border-color);
      border-radius: var(--radius-md);
      margin-bottom: 0.75rem;
      background-color: var(--white);
    }

    .detail-label {
      color: #5c728a;
      font-size: 0.95rem;
    }

    .detail-value {
      color: #0b2239;
      font-weight: 600;
      font-size: 0.95rem;
    }

  </style>
</head>
<body>
  
  <div class="container page-wrapper has-bottom-nav">
    <div class="dashboard-scroll-area">
      <!-- Header -->
      <div class="page-header">
      <h1>Profile</h1>
      <p>Manage your account details</p>
    </div>
    <?php if ($profileError !== ''): ?>
      <div class="detail-row" style="margin: 0 1.25rem 1rem;">
        <span class="detail-label">Notice</span>
        <span class="detail-value"><?php echo htmlspecialchars($profileError, ENT_QUOTES, 'UTF-8'); ?></span>
      </div>
    <?php endif; ?>

    <!-- Identity Card -->
    <div class="profile-card">
      <div class="profile-card-subtitle">GovEase User</div>
      <div class="profile-card-title"><?php echo htmlspecialchars($initials, ENT_QUOTES, 'UTF-8'); ?> &middot; <?php echo htmlspecialchars($displayName !== '' ? $displayName : 'User', ENT_QUOTES, 'UTF-8'); ?></div>
      <div class="profile-card-email"><?php echo htmlspecialchars($displayEmail !== '' ? $displayEmail : 'No email', ENT_QUOTES, 'UTF-8'); ?></div>
      
      <div class="profile-actions">
        <button class="btn btn-profile-outline" onclick="window.location.href='home.php'">Back to Home</button>
        <button class="btn btn-profile-outline" onclick="window.location.href='login.php'">Sign out</button>
      </div>
    </div>

      <!-- Details -->
      <div class="details-list">
        <div class="detail-row">
          <span class="detail-label">Role</span>
          <span class="detail-value">user</span>
        </div>
        <div class="detail-row">
          <span class="detail-label">Email</span>
          <span class="detail-value"><?php echo htmlspecialchars($displayEmail !== '' ? $displayEmail : 'Not set', ENT_QUOTES, 'UTF-8'); ?></span>
        </div>
        <div class="detail-row">
          <span class="detail-label">Phone</span>
          <span class="detail-value"><?php echo htmlspecialchars($displayPhone !== '' ? $displayPhone : 'Not set', ENT_QUOTES, 'UTF-8'); ?></span>
        </div>
        <div class="detail-row">
          <span class="detail-label">Account status</span>
          <span class="detail-value">Active</span>
        </div>
      </div>
    </div>

    <!-- Bottom Navigation -->
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
      <a href="tickets.php" class="nav-item">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2Z"/><path d="M13 5v2"/><path d="M13 17v2"/><path d="M13 11v2"/></svg>
        Tickets
      </a>
      <a href="profile.php" class="nav-item active-bg">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
        Profile
      </a>
    </div>

  </div>

</body>
</html>
