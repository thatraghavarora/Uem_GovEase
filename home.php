<?php
declare(strict_types=1);

if (empty($_COOKIE['user_phone']) || empty($_COOKIE['session_token'])) {
    header('Location: preloader.php');
    exit;
}

$userPhone = trim((string) ($_COOKIE['user_phone'] ?? ''));
$cookieName = trim((string) ($_COOKIE['user_name'] ?? ''));
$cookieEmail = trim((string) ($_COOKIE['user_email'] ?? ''));
$kycDetails = [
    'fullName' => '',
    'email' => '',
    'phone' => $userPhone,
    'createdAt' => '',
    'source' => '',
];
$kycFound = false;
$centers = [];
$activeTokenCount = 0;
$centersError = '';
$token = '';

$credentialsPath = __DIR__ . '/govease-99021-firebase-adminsdk-fbsvc-fe9d642385.json';
if (is_readable($credentialsPath)) {
    try {
        $serviceAccount = json_decode((string) file_get_contents($credentialsPath), true, 512, JSON_THROW_ON_ERROR);
        $projectId = (string) ($serviceAccount['project_id'] ?? '');
        $clientEmail = (string) ($serviceAccount['client_email'] ?? '');
        $privateKey = (string) ($serviceAccount['private_key'] ?? '');

        if ($projectId !== '' && $clientEmail !== '' && $privateKey !== '') {
            $token = getAccessToken($clientEmail, $privateKey);

            try {
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
                        $kycDetails['fullName'] = (string) ($fields['fullName']['stringValue'] ?? ($fields['name']['stringValue'] ?? ''));
                        $kycDetails['email'] = (string) ($fields['email']['stringValue'] ?? '');
                        $kycDetails['phone'] = (string) ($fields['phone']['stringValue'] ?? $userPhone);
                        $kycDetails['createdAt'] = (string) ($fields['createdAt']['timestampValue'] ?? '');
                        $kycDetails['source'] = (string) ($fields['source']['stringValue'] ?? '');
                        $kycFound = true;
                        break 2;
                    }
                }

                if (!$kycFound && $userPhone !== '') {
                    $docId = $userPhone;
                    $docRef = 'projects/' . $projectId . '/databases/(default)/documents/kyc_submissions/' . $docId;
                    $payload = [
                        'structuredQuery' => [
                            'from' => [
                                ['collectionId' => 'kyc_submissions'],
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
                        if (empty($row['document']['fields'])) {
                            continue;
                        }
                        $fields = $row['document']['fields'];
                        $kycDetails['fullName'] = (string) ($fields['fullName']['stringValue'] ?? ($fields['name']['stringValue'] ?? ''));
                        $kycDetails['email'] = (string) ($fields['email']['stringValue'] ?? '');
                        $kycDetails['phone'] = (string) ($fields['phone']['stringValue'] ?? $userPhone);
                        $kycDetails['createdAt'] = (string) ($fields['createdAt']['timestampValue'] ?? '');
                        $kycDetails['source'] = (string) ($fields['source']['stringValue'] ?? '');
                        $kycFound = true;
                        break;
                    }
                }
            } catch (Throwable $e) {
                error_log('KYC lookup failed: ' . $e->getMessage());
            }

            try {
                $centers = fetchCenters($projectId, $token);
                if (empty($centers)) {
                    $centersError = 'No centers found in the database.';
                }
            } catch (Throwable $e) {
                error_log('Centers fetch failed: ' . $e->getMessage());
                $centersError = 'Unable to fetch centers. Please try again later.';
            }

            try {
                if ($userPhone !== '') {
                    $activeTokenCount = countTokensForUser($projectId, $token, $userPhone);
                }
            } catch (Throwable $e) {
                error_log('Token count failed: ' . $e->getMessage());
            }
        } else {
            $centersError = 'Service account JSON is missing required fields.';
        }
    } catch (Throwable $e) {
        error_log('Home bootstrap failed: ' . $e->getMessage());
        $centersError = 'Unable to fetch centers. Please try again later.';
    }
} else {
    $centersError = 'Service account JSON not readable.';
}

$displayName = $kycDetails['fullName'] !== '' ? $kycDetails['fullName'] : $cookieName;
$greetingName = $displayName !== '' ? $displayName : 'there';
$displayPhone = $kycDetails['phone'];
$displayEmail = $kycDetails['email'] !== '' ? $kycDetails['email'] : $cookieEmail;
$displayCreatedAt = $kycDetails['createdAt'];
$displaySource = $kycDetails['source'];

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
    return array_values(array_unique($candidates));
}

function fetchCenters(string $projectId, string $token): array
{
    $centers = [];
    $pageToken = '';
    $pageSize = 200;
    $maxPages = 5;

    for ($page = 0; $page < $maxPages; $page++) {
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
            $docId = basename($docPath);
            $centers[] = [
                'id' => $docId,
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

        $pageToken = (string) ($response['nextPageToken'] ?? '');
        if ($pageToken === '') {
            break;
        }
    }

    return $centers;
}

function countTokensForUser(string $projectId, string $token, string $userPhone): int
{
    $payload = [
        'structuredQuery' => [
            'from' => [
                ['collectionId' => 'token'],
            ],
            'where' => [
                'fieldFilter' => [
                    'field' => ['fieldPath' => 'userPhone'],
                    'op' => 'EQUAL',
                    'value' => ['stringValue' => $userPhone],
                ],
            ],
            'limit' => 25,
        ],
    ];

    $url = 'https://firestore.googleapis.com/v1/projects/' . rawurlencode($projectId)
        . '/databases/(default)/documents:runQuery';
    $response = curlJson('POST', $url, $token, $payload);
    $count = 0;
    foreach ($response as $row) {
        if (!empty($row['document']['name'])) {
            $count++;
        }
    }
    return $count;
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
        CURLOPT_TIMEOUT => 15,
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
  <title>GovEase - Dashboard</title>
  <link rel="stylesheet" href="style.css">
  <style>
    /* Small inline styles for alignment where flex generic isn't enough */
    .dashboard-wrapper {
        padding-bottom: 80px; /* Space for bottom nav */
    }
  </style>
</head>
<body>
  
  <div class="container dashboard-wrapper has-bottom-nav">
    <!-- Header -->
    <div class="dash-header">
      <div style="display: flex; gap: 1rem; align-items: flex-start;">
        <div class="brand-sm" style="margin-top: 4px;">
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M12 2L2 7l10 5 10-5-10-5Z"/>
              <path d="M2 17l10 5 10-5"/>
              <path d="M2 12l10 5 10-5"/>
          </svg>
          <span style="font-size: 0.9rem;">GovEase</span>
        </div>
        <div class="user-greeting">
          <h2>Hi, <?php echo htmlspecialchars($greetingName, ENT_QUOTES, 'UTF-8'); ?></h2>
          <p>Find your service quickly</p>
          <?php if ($displayPhone !== '' || $displayEmail !== ''): ?>
            <p><?php echo htmlspecialchars(trim($displayPhone . ($displayEmail !== '' ? ' • ' . $displayEmail : '')), ENT_QUOTES, 'UTF-8'); ?></p>
          <?php endif; ?>
        </div>
      </div>
      <button class="btn btn-signout" onclick="window.location.href='login.php'">Sign out</button>
    </div>

    <!-- Search -->
    <div class="search-bar-container">
      <input type="text" class="search-input" placeholder="Search hospital, RTO, or location">
    </div>

    <!-- Services Header -->
    <div class="section-header">
      <h3>Services</h3>
      <a href="#" class="link-text">50 categories</a>
    </div>

    <!-- Services Scroll -->
    <div class="services-scroll">
      <div class="service-pill active">All</div>
      <div class="service-pill">OPD Consultation</div>
      <div class="service-pill">Emergency Care</div>
      <div class="service-pill">Pediatrics</div>
      <div class="service-pill">Cardiology</div>
    </div>

    <!-- Scroll Indicators -->
    <div class="scroll-indicators">
      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
      <div class="scroll-dots">
        <div class="dot active"></div>
        <div class="dot"></div>
        <div class="dot"></div>
      </div>
      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
    </div>

    <!-- Location Panel -->
    <div class="panel-card bg-light flex-between">
      <div class="location-access">
        <h4>Location access</h4>
        <p>Allow location to show Jaipur hospitals.</p>
      </div>
      <button class="btn btn-sm-outline">Allow</button>
    </div>

    <!-- Dropdowns -->
    <div class="form-group">
      <label class="form-label">City</label>
      <select class="custom-select" style="width: 100%; padding: 0.8rem 1rem; border: 1px solid var(--border-color); border-radius: var(--radius-md); outline: none;">
        <option>All</option>
        <option>Jaipur</option>
        <option>Delhi</option>
      </select>
    </div>

    <div class="form-group mb-4">
      <label class="form-label">Location</label>
      <select class="custom-select" style="width: 100%; padding: 0.8rem 1rem; border: 1px solid var(--border-color); border-radius: var(--radius-md); outline: none;">
        <option>All</option>
        <option>North District</option>
        <option>South District</option>
      </select>
    </div>

    <!-- Active Tokens -->
    <div class="active-tokens-panel">
      <span>Active tokens</span>
      <span class="token-count"><?php echo (int) $activeTokenCount; ?></span>
    </div>

    <!-- Centers -->
    <div class="section-header" style="margin-top: 0.5rem;">
      <h3>Centers near you</h3>
      <span class="link-text"><?php echo count($centers); ?> available</span>
    </div>
    <div class="centers-list">
      <?php if (!empty($centers)): ?>
        <?php foreach ($centers as $center): ?>
          <a class="center-card" href="appointment.php?centerId=<?php echo urlencode($center['id']); ?>&centerPath=<?php echo urlencode($center['path']); ?>">
            <div>
              <h4><?php echo htmlspecialchars($center['name'] !== '' ? $center['name'] : 'Center', ENT_QUOTES, 'UTF-8'); ?></h4>
              <?php if ($center['type'] !== ''): ?>
                <p class="center-meta"><?php echo htmlspecialchars($center['type'], ENT_QUOTES, 'UTF-8'); ?></p>
              <?php endif; ?>
              <p class="center-meta">
                <?php echo htmlspecialchars(trim($center['city'] . ($center['address'] !== '' ? ' • ' . $center['address'] : '')), ENT_QUOTES, 'UTF-8'); ?>
              </p>
              <?php if ($center['phone'] !== ''): ?>
                <p class="center-meta">Phone: <?php echo htmlspecialchars($center['phone'], ENT_QUOTES, 'UTF-8'); ?></p>
              <?php endif; ?>
              <?php if ($center['code'] !== ''): ?>
                <p class="center-meta">Code: <?php echo htmlspecialchars($center['code'], ENT_QUOTES, 'UTF-8'); ?></p>
              <?php endif; ?>
              <?php if ($center['location'] !== ''): ?>
                <p class="center-meta">Location: <?php echo htmlspecialchars($center['location'], ENT_QUOTES, 'UTF-8'); ?></p>
              <?php endif; ?>
            </div>
            <span class="center-action">Book</span>
          </a>
        <?php endforeach; ?>
      <?php else: ?>
        <div class="panel-card" style="margin-bottom: 1.5rem;">
          <p class="text-muted"><?php echo htmlspecialchars($centersError !== '' ? $centersError : 'No centers found in the database.', ENT_QUOTES, 'UTF-8'); ?></p>
        </div>
      <?php endif; ?>
    </div>

    <!-- Action Card -->
    <div class="action-card">
      <h3 style="font-size: 1.25rem;">Scan QR to get token</h3>
      <p style="margin-top: 0.25rem;">Open your camera and scan the queue QR</p>
      <div class="flex-between" style="margin-top: 1.5rem;">
        <button class="btn btn-outline" style="background: var(--white); color: var(--primary); border: none; padding: 0.5rem 1.25rem; font-size: 0.85rem; border-radius: var(--radius-full); width: auto;">Scan Now</button>
        <div class="qrcode-box">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="3" y="3" width="7" height="7" rx="1"/>
            <rect x="14" y="3" width="7" height="7" rx="1"/>
            <rect x="14" y="14" width="7" height="7" rx="1"/>
            <rect x="3" y="14" width="7" height="7" rx="1"/>
          </svg>
        </div>
      </div>
    </div>

    <div class="panel-card">
      <h4 style="font-size: 0.95rem; color: #334155; margin-bottom: 0.5rem;">KYC Details</h4>
      <?php if ($kycFound): ?>
        <p class="mb-1"><strong>Name:</strong> <?php echo htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8'); ?></p>
        <p class="mb-1"><strong>Phone:</strong> <?php echo htmlspecialchars($displayPhone, ENT_QUOTES, 'UTF-8'); ?></p>
        <p class="mb-1"><strong>Email:</strong> <?php echo htmlspecialchars($displayEmail, ENT_QUOTES, 'UTF-8'); ?></p>
        <p class="mb-1"><strong>Created At:</strong> <?php echo htmlspecialchars($displayCreatedAt, ENT_QUOTES, 'UTF-8'); ?></p>
        <p class="mb-0"><strong>Source:</strong> <?php echo htmlspecialchars($displaySource, ENT_QUOTES, 'UTF-8'); ?></p>
      <?php else: ?>
        <p class="text-muted">No KYC record found for this phone number.</p>
      <?php endif; ?>
    </div>

    <!-- Bottom Navigation -->
    <div class="bottom-nav">
      <a href="#" class="nav-item active-bg">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
        Home
      </a>
      <a href="appointments.php" class="nav-item">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="4" rx="2" ry="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/><path d="M8 14h.01"/><path d="M12 14h.01"/><path d="M16 14h.01"/><path d="M8 18h.01"/><path d="M12 18h.01"/><path d="M16 18h.01"/></svg>
        Appointments
      </a>
      <a href="scan.php" class="nav-item">
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
      <a href="#" class="nav-item">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
        Profile
      </a>
    </div>

  </div>
  <style>
      @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');

:root {
    --primary: #0a6476;
    /* Dark teal from screenshots */
    --primary-light: #e6f3f5;
    --secondary: #2C3E50;
    --text-dark: #1f2937;
    --text-muted: #6b7280;
    --bg-color: #f3f6f8;
    --white: #ffffff;
    --border-color: #e5e7eb;
    --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
    --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
    --radius-sm: 0.375rem;
    --radius-md: 0.5rem;
    --radius-lg: 0.75rem;
    --radius-xl: 1rem;
    --radius-full: 9999px;
    --transition: all 0.2s ease-in-out;
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

a {
    text-decoration: none;
    color: inherit;
}

a:hover {
    color: var(--primary);
}

button {
    cursor: pointer;
    border: none;
    background: none;
    font-family: inherit;
    font-size: inherit;
    transition: var(--transition);
}

input,
select {
    font-family: inherit;
    font-size: 14px;
}

/* Utilities */
.container {
    width: 100%;
    max-width: 480px;
    /* Mobile app size for dashboard */
    margin: 0 auto;
    background: var(--white);
    min-height: 100vh;
    position: relative;
    box-shadow: var(--shadow-md);
    overflow-x: hidden;
}

@media (min-width: 481px) {
    body {
        display: flex;
        justify-content: center;
        align-items: center;
        min-height: 100vh;
        background-color: var(--text-dark);
        /* Darker background outside the phone frame */
    }

    .container {
        min-height: 850px;
        /* Simulate phone height */
        max-height: 90vh;
        /* Don't exceed screen */
        border-radius: var(--radius-xl);
        overflow: hidden;
        /* Keep content inside rounded corners */
        position: relative;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        /* Deep shadow for phone effect */
    }
}

/* Typography */
h1,
h2,
h3,
h4 {
    color: var(--text-dark);
    font-weight: 600;
}

.text-muted {
    color: var(--text-muted);
}

.text-primary {
    color: var(--primary);
}

.text-center {
    text-align: center;
}

.font-medium {
    font-weight: 500;
}

.font-semibold {
    font-weight: 600;
}

.mb-1 {
    margin-bottom: 0.25rem;
}

.mb-2 {
    margin-bottom: 0.5rem;
}

.mb-3 {
    margin-bottom: 0.75rem;
}

.mb-4 {
    margin-bottom: 1rem;
}

.mb-6 {
    margin-bottom: 1.5rem;
}

.mb-8 {
    margin-bottom: 2rem;
}

.mt-2 {
    margin-top: 0.5rem;
}

.mt-4 {
    margin-top: 1rem;
}

.p-4 {
    padding: 1rem;
}

/* Dashboard Specifics */
.dash-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    padding: 1.5rem 1.25rem 1rem;
}

.brand-sm {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    color: var(--primary);
    font-weight: 700;
}

.brand-sm svg {
    width: 18px;
    height: 18px;
}

.user-greeting h2 {
    font-size: 1.1rem;
    color: #0b2239;
    /* Darker navy */
}

.user-greeting p {
    color: #5c728a;
    font-size: 0.85rem;
}

.btn-signout {
    border: 1px solid var(--border-color);
    border-radius: var(--radius-full);
    padding: 0.4rem 0.8rem;
    font-size: 0.8rem;
    color: #5c728a;
}

.search-bar-container {
    padding: 0 1.25rem 1rem;
}

.search-input {
    width: 100%;
    padding: 0.8rem 1rem;
    background-color: var(--bg-color);
    border: 1px solid transparent;
    border-radius: var(--radius-md);
    outline: none;
    font-size: 0.9rem;
    transition: var(--transition);
}

.search-input:focus {
    border-color: var(--border-color);
    background-color: var(--white);
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    padding: 0 1.25rem;
    margin-bottom: 0.75rem;
}

.section-header h3 {
    font-size: 1.25rem;
    color: #0b2239;
}

.section-header .link-text {
    font-size: 0.8rem;
    color: #5c728a;
}

.services-scroll {
    display: flex;
    gap: 0.75rem;
    overflow-x: auto;
    padding: 0 1.25rem 0.5rem;
    scrollbar-width: none;
    /* Firefox */
}

.services-scroll::-webkit-scrollbar {
    display: none;
    /* Chrome */
}

.service-pill {
    padding: 0.6rem 1rem;
    border-radius: var(--radius-full);
    border: 1px solid var(--border-color);
    white-space: nowrap;
    font-size: 0.85rem;
    color: #5c728a;
    background-color: var(--white);
    transition: var(--transition);
}

.service-pill.active {
    background-color: var(--primary);
    color: var(--white);
    border-color: var(--primary);
}

.scroll-indicators {
    display: flex;
    justify-content: space-between;
    padding: 0 1.25rem;
    color: #adb5bd;
    margin-bottom: 1rem;
    align-items: center;
}

.scroll-dots {
    display: flex;
    gap: 4px;
}

.dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background-color: #dee2e6;
}

.dot.active {
    background-color: #adb5bd;
    width: 20px;
    border-radius: 4px;
}

.panel-card {
    border: 1px solid var(--border-color);
    border-radius: var(--radius-md);
    padding: 1rem;
    margin: 0 1.25rem 1rem;
    background-color: var(--white);
}

.panel-card.bg-light {
    background-color: #f8fafc;
}

.flex-between {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.location-access h4 {
    font-size: 0.95rem;
    color: #334155;
    margin-bottom: 0.2rem;
}

.location-access p {
    font-size: 0.8rem;
    color: #64748b;
}

.btn-sm-outline {
    border: 1px solid var(--border-color);
    padding: 0.4rem 1rem;
    border-radius: var(--radius-full);
    font-size: 0.8rem;
    color: #334155;
}

.form-group {
    padding: 0 1.25rem 1rem;
}

.form-label {
    display: block;
    font-size: 0.85rem;
    color: #475569;
    margin-bottom: 0.4rem;
}

.custom-select {
    appearance: none;
    background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
    background-repeat: no-repeat;
    background-position: right 1rem center;
    background-size: 1em;
}

.active-tokens-panel {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem 1.25rem;
    border: 1px solid var(--border-color);
    border-radius: var(--radius-md);
    margin: 0 1.25rem 1rem;
    background-color: #f8fafc;
    color: #475569;
    font-weight: 500;
}

.token-count {
    font-weight: 700;
    color: #0f172a;
}

.action-card {
    background-color: var(--primary);
    color: var(--white);
    border-radius: var(--radius-lg);
    padding: 1.5rem;
    margin: 0 1.25rem 6rem;
    /* space for bottom nav */
    position: relative;
    overflow: hidden;
}

.action-card::after {
    content: '';
    position: absolute;
    right: -20%;
    bottom: -20%;
    width: 150px;
    height: 150px;
    background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, rgba(255, 255, 255, 0) 70%);
    border-radius: 50%;
}

.action-card h3 {
    color: var(--white);
    margin-bottom: 0.25rem;
    font-size: 1.1rem;
}

.action-card p {
    color: rgba(255, 255, 255, 0.8);
    font-size: 0.85rem;
    margin-bottom: 1rem;
}

.centers-list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    padding: 0 1.25rem 1.5rem;
}

.center-card {
    border: 1px solid var(--border-color);
    border-radius: var(--radius-md);
    padding: 0.9rem 1rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    background-color: var(--white);
    box-shadow: var(--shadow-sm);
}

.center-card h4 {
    font-size: 0.95rem;
    margin-bottom: 0.25rem;
}

.center-meta {
    font-size: 0.8rem;
    color: #64748b;
}

.center-action {
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--primary);
    background-color: var(--primary-light);
    padding: 0.3rem 0.6rem;
    border-radius: var(--radius-full);
}

.qrcode-box {
    width: 60px;
    height: 60px;
    background-color: var(--white);
    border-radius: var(--radius-sm);
    display: flex;
    align-items: center;
    justify-content: center;
}

.qrcode-box svg {
    width: 30px;
    height: 30px;
    color: var(--text-dark);
}

.bottom-nav {
    position: fixed;
    bottom: 0;
    left: 50%;
    transform: translateX(-50%);
    width: 100%;
    max-width: 480px;
    background-color: var(--white);
    border-top: 1px solid var(--border-color);
    display: flex;
    justify-content: space-around;
    padding: 0.75rem 0.5rem;
    box-shadow: 0 -4px 10px rgba(0, 0, 0, 0.03);
    z-index: 10;
}

.nav-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.25rem;
    color: #64748b;
    font-size: 0.7rem;
    font-weight: 500;
    padding: 0.5rem;
    border-radius: var(--radius-sm);
}

.nav-item:hover {
    color: var(--primary);
    background-color: var(--bg-color);
}

.nav-item.active {
    color: var(--primary);
}

.nav-item.active-bg {
    background-color: var(--primary);
    color: var(--white);
    border-radius: var(--radius-md);
    padding: 0.5rem 1rem;
}

.nav-item svg {
    width: 20px;
    height: 20px;
}
  </style>

</body>
</html>
