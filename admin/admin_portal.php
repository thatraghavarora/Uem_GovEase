<?php
declare(strict_types=1);

session_start();

if (empty($_SESSION['admin_user'])) {
  header('Location: admin_login.php');
  exit;
}

$credentialsPath = resolveCredentialsPath();
$debug = isset($_GET['debug']) && $_GET['debug'] === '1';
$errorMessage = '';
$successMessage = '';
$tokens = [];

$adminUser = (string) ($_SESSION['admin_user'] ?? '');
$centerId = (string) ($_SESSION['admin_center_id'] ?? '');
$centerName = (string) ($_SESSION['admin_center_name'] ?? '');
$centerCode = (string) ($_SESSION['admin_center_code'] ?? '');
$centerType = (string) ($_SESSION['admin_center_type'] ?? '');
if ($centerId === '' && $centerCode !== '') {
  $centerId = $centerCode;
}

if ($centerId === '') {
  $errorMessage = 'Center is not assigned to this admin.';
} elseif ($credentialsPath === '') {
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

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      $tokenId = trim((string) ($_POST['token_id'] ?? ''));
      $action = trim((string) ($_POST['action'] ?? ''));
      if ($tokenId !== '' && in_array($action, ['approve', 'decline'], true)) {
        updateTokenStatus($projectId, $token, $tokenId, $adminUser, $action);
        $successMessage = $action === 'approve' ? 'Token approved.' : 'Token declined.';
      }
    }

    $tokens = fetchTokensByListing($projectId, $token, $centerId, true);
    if (empty($tokens)) {
      $tokens = fetchTokensByListing($projectId, $token, $centerId, false);
    }
  } catch (Throwable $e) {
    error_log('Admin portal failed: ' . $e->getMessage());
    $errorMessage = $debug ? ('Unable to load tokens: ' . $e->getMessage()) : 'Unable to load tokens.';
  }
}

function resolveCredentialsPath(): string
{
  $candidates = [
    dirname(__DIR__) . '/govease-99021-firebase-adminsdk-fbsvc-fe9d642385.json',
    __DIR__ . '/govease-99021-firebase-adminsdk-fbsvc-fe9d642385.json',
    getcwd() . '/govease-99021-firebase-adminsdk-fbsvc-fe9d642385.json',
  ];
  foreach ($candidates as $path) {
    if ($path !== false && is_readable($path)) {
      return $path;
    }
  }
  return '';
}

function fetchTokensByListing(string $projectId, string $token, string $centerId, bool $activeOnly): array
{
  $pageToken = '';
  $tokens = [];
  $pageSize = 200;
  for ($page = 0; $page < 5; $page++) {
    $query = http_build_query(array_filter([
      'pageSize' => (string) $pageSize,
      'pageToken' => $pageToken !== '' ? $pageToken : null,
    ]));
    $url = 'https://firestore.googleapis.com/v1/projects/' . rawurlencode($projectId)
      . '/databases/(default)/documents/token' . ($query !== '' ? '?' . $query : '');
    $response = curlJson('GET', $url, $token, []);
    $documents = $response['documents'] ?? [];
    foreach ($documents as $doc) {
      if (empty($doc['fields']) || empty($doc['name'])) {
        continue;
      }
      $fields = $doc['fields'];
      $docCenterId = getFieldString($fields, 'centerId');
      if ($docCenterId !== $centerId) {
        continue;
      }
      $status = getFieldString($fields, 'status');
      if ($activeOnly && $status !== 'active') {
        continue;
      }
      $tokens[] = [
        'id' => basename((string) $doc['name']),
        'tokenNumber' => getFieldString($fields, 'tokenNumber'),
        'userPhone' => getFieldString($fields, 'userPhone'),
        'userName' => getFieldString($fields, 'userName'),
        'appointmentTime' => getFieldString($fields, 'appointmentTime'),
        'createdAt' => getFieldString($fields, 'createdAt'),
        'status' => $status,
      ];
    }
    $pageToken = (string) ($response['nextPageToken'] ?? '');
    if ($pageToken === '') {
      break;
    }
  }

  usort($tokens, static function (array $a, array $b): int {
    return strcmp($a['createdAt'], $b['createdAt']);
  });

  return $tokens;
}

function updateTokenStatus(string $projectId, string $token, string $tokenId, string $adminUser, string $action): void
{
    $statusValue = $action === 'approve' ? 'approved' : 'declined';
    $byField = $action === 'approve' ? 'approvedBy' : 'declinedBy';
    $atField = $action === 'approve' ? 'approvedAt' : 'declinedAt';
    $url = 'https://firestore.googleapis.com/v1/projects/' . rawurlencode($projectId)
        . '/databases/(default)/documents/token/' . rawurlencode($tokenId)
        . '?updateMask.fieldPaths=status&updateMask.fieldPaths=' . rawurlencode($byField)
        . '&updateMask.fieldPaths=' . rawurlencode($atField);
    $payload = [
        'fields' => [
            'status' => ['stringValue' => $statusValue],
            $byField => ['stringValue' => $adminUser],
            $atField => ['timestampValue' => gmdate('c')],
        ],
    ];
    curlJson('PATCH', $url, $token, $payload);
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
  <title>GovEase Admin - Portal</title>
  <link rel="stylesheet" href="../style.css">
  <style>
    body {
      background-color: var(--bg-color);
    }

    .page-wrapper {
      padding-top: 2rem;
      padding-bottom: 2rem;
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

    .admin-meta {
      display: flex;
      flex-wrap: wrap;
      gap: 0.5rem;
      margin-top: 0.75rem;
    }

    .chip {
      background: #f1f5f9;
      padding: 0.35rem 0.75rem;
      border-radius: 999px;
      font-size: 0.8rem;
      color: #475569;
    }

    .admin-actions {
      display: flex;
      gap: 0.5rem;
      align-items: center;
      margin-top: 0.75rem;
    }

    .admin-logout,
    .print-btn {
      border: 1px solid var(--border-color);
      border-radius: 999px;
      padding: 0.5rem 0.9rem;
      background: var(--white);
      color: #334155;
      font-size: 0.85rem;
      cursor: pointer;
    }

    .admin-message {
      margin: 0 1.25rem 1rem;
      padding: 0.75rem 1rem;
      border-radius: var(--radius-md);
      font-size: 0.9rem;
    }

    .admin-message.success {
      background: #ecfdf3;
      border: 1px solid #bbf7d0;
      color: #15803d;
    }

    .admin-message.error {
      background: #fef2f2;
      border: 1px solid #fecaca;
      color: #b91c1c;
    }

    .tokens-list {
      display: grid;
      gap: 0.75rem;
      margin-top: 1rem;
    }

    .token-item {
      border: 1px solid var(--border-color);
      border-radius: var(--radius-md);
      padding: 0.9rem 1rem;
      background-color: #f8fafc;
    }

    .token-title {
      font-weight: 600;
      color: #0b2239;
      margin-bottom: 0.25rem;
    }

    .token-meta {
      color: #64748b;
      font-size: 0.85rem;
    }

    .token-action {
      margin-top: 0.75rem;
      display: flex;
      gap: 0.5rem;
    }

    .token-action button {
      border: none;
      background: var(--primary);
      color: var(--white);
      padding: 0.4rem 0.85rem;
      border-radius: 999px;
      font-size: 0.8rem;
      cursor: pointer;
    }

    .token-action .decline {
      background: #ef4444;
    }

    .token-empty {
      color: var(--text-muted);
      text-align: center;
    }

    @media print {
      body {
        background: #ffffff;
      }

      .admin-actions,
      .token-action,
      .admin-message,
      .print-hide {
        display: none !important;
      }

      .tickets-card {
        margin: 0;
        border: none;
      }
    }
  </style>
</head>

<body>
  <div class="container page-wrapper">
    <div class="page-header">
      <h1>Center Admin Portal</h1>
      <p>Approve tokens in FIFO order and track 5 minute slots.</p>
    </div>

    <div class="tickets-card">
      <div class="admin-meta">
        <span class="chip">Center: <?php echo htmlspecialchars($centerName !== '' ? $centerName : $centerId, ENT_QUOTES, 'UTF-8'); ?></span>
        <?php if ($centerCode !== ''): ?>
          <span class="chip">Code: <?php echo htmlspecialchars($centerCode, ENT_QUOTES, 'UTF-8'); ?></span>
        <?php endif; ?>
        <?php if ($centerType !== ''): ?>
          <span class="chip">Type: <?php echo htmlspecialchars($centerType, ENT_QUOTES, 'UTF-8'); ?></span>
        <?php endif; ?>
      </div>
      <div class="admin-actions">
        <span class="chip">Signed in: <?php echo htmlspecialchars($adminUser, ENT_QUOTES, 'UTF-8'); ?></span>
        <button class="print-btn print-hide" type="button" onclick="window.print()">Print</button>
        <a class="admin-logout" href="logout.php">Sign out</a>
      </div>
    </div>

    <?php if ($successMessage !== ''): ?>
      <div class="admin-message success"><?php echo htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>
    <?php if ($errorMessage !== ''): ?>
      <div class="admin-message error"><?php echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <?php if ($errorMessage === ''): ?>
      <div class="tickets-card">
        <h2>Active tokens</h2>
        <p class="token-meta">FIFO order with 5 minute slots per token.</p>
        <?php if (empty($tokens)): ?>
          <div class="token-empty">No active tokens waiting for approval.</div>
        <?php else: ?>
          <div class="tokens-list">
            <?php
            $baseTime = time();
            ?>
            <?php foreach ($tokens as $index => $tokenRow): ?>
              <?php
              $slotTime = $baseTime + ($index * 5 * 60);
              $slotLabel = date('H:i', $slotTime);
              $userLabel = trim(($tokenRow['userName'] !== '' ? $tokenRow['userName'] : '') . ' ' . ($tokenRow['userPhone'] !== '' ? '(' . $tokenRow['userPhone'] . ')' : ''));
              ?>
              <div class="token-item">
                <div class="token-title">Token <?php echo htmlspecialchars($tokenRow['tokenNumber'] !== '' ? $tokenRow['tokenNumber'] : $tokenRow['id'], ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="token-meta">User: <?php echo htmlspecialchars($userLabel !== '' ? $userLabel : 'User', ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="token-meta">Created: <?php echo htmlspecialchars($tokenRow['createdAt'], ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="token-meta">Preferred: <?php echo htmlspecialchars($tokenRow['appointmentTime'] !== '' ? $tokenRow['appointmentTime'] : '-', ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="token-meta">Slot: <?php echo htmlspecialchars($slotLabel, ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="token-action">
                  <form method="post">
                    <input type="hidden" name="token_id"
                      value="<?php echo htmlspecialchars($tokenRow['id'], ENT_QUOTES, 'UTF-8'); ?>">
                    <button type="submit" name="action" value="approve">Approve</button>
                    <button class="decline" type="submit" name="action" value="decline">Decline</button>
                  </form>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
</body>

</html>
