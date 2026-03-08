<?php
declare(strict_types=1);

$credentialsPath = resolveCredentialsPath();
$admins = [];
$errorMessage = '';

if ($credentialsPath === '') {
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
        $admins = fetchAdmins($projectId, $token);
    } catch (Throwable $e) {
        error_log('View admins failed: ' . $e->getMessage());
        $errorMessage = 'Unable to load admin list.';
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

function fetchAdmins(string $projectId, string $token): array
{
    $admins = [];
    $pageToken = '';
    $pageSize = 200;
    for ($page = 0; $page < 5; $page++) {
        $query = http_build_query(array_filter([
            'pageSize' => (string) $pageSize,
            'pageToken' => $pageToken !== '' ? $pageToken : null,
        ]));
        $url = 'https://firestore.googleapis.com/v1/projects/' . rawurlencode($projectId)
            . '/databases/(default)/documents/admins' . ($query !== '' ? '?' . $query : '');
        $response = curlJson('GET', $url, $token, []);
        $documents = $response['documents'] ?? [];
        foreach ($documents as $doc) {
            if (empty($doc['fields']) || empty($doc['name'])) {
                continue;
            }
            $fields = $doc['fields'];
            $admins[] = [
                'id' => getFieldString($fields, 'id'),
                'username' => getFieldString($fields, 'username'),
                'password' => getFieldString($fields, 'password'),
                'centerId' => getFieldString($fields, 'centerId'),
                'centerCode' => getFieldString($fields, 'centerCode'),
                'centerName' => getFieldString($fields, 'centerName'),
                'centerType' => getFieldString($fields, 'centerType'),
                'createdAt' => getFieldString($fields, 'createdAt'),
            ];
        }
        $pageToken = (string) ($response['nextPageToken'] ?? '');
        if ($pageToken === '') {
            break;
        }
    }

    return $admins;
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
  <title>GovEase Admin - View</title>
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

    .admin-card {
      border: 1px solid var(--border-color);
      border-radius: var(--radius-lg);
      padding: 1.25rem;
      margin: 0 1.25rem 1rem;
      background-color: var(--white);
    }

    .admin-list {
      display: grid;
      gap: 0.75rem;
      margin-top: 1rem;
    }

    .admin-item {
      border: 1px solid var(--border-color);
      border-radius: var(--radius-md);
      padding: 0.9rem 1rem;
      background-color: #f8fafc;
    }

    .admin-title {
      font-weight: 600;
      color: #0b2239;
      margin-bottom: 0.25rem;
    }

    .admin-meta {
      color: #64748b;
      font-size: 0.85rem;
    }

    .admin-actions {
      display: flex;
      gap: 0.5rem;
      flex-wrap: wrap;
      margin: 0 1.25rem 1.25rem;
    }

    .admin-actions a {
      border: 1px solid var(--border-color);
      border-radius: 999px;
      padding: 0.5rem 0.9rem;
      background: var(--white);
      color: #334155;
      font-size: 0.85rem;
      cursor: pointer;
      text-decoration: none;
    }
  </style>
</head>
<body>
  <div class="container page-wrapper">
    <div class="page-header">
      <h1>Admin Credentials</h1>
      <p>Demo view of all admin accounts.</p>
    </div>

    <div class="admin-actions">
      <a href="admin_login.php">Admin Login</a>
      <a href="register.php">Register Admin</a>
    </div>

    <div class="admin-card">
      <?php if ($errorMessage !== ''): ?>
        <p class="admin-meta"><?php echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?></p>
      <?php elseif (empty($admins)): ?>
        <p class="admin-meta">No admins found.</p>
      <?php else: ?>
        <div class="admin-list">
          <?php foreach ($admins as $admin): ?>
            <div class="admin-item">
              <div class="admin-title"><?php echo htmlspecialchars($admin['username'] !== '' ? $admin['username'] : 'Admin', ENT_QUOTES, 'UTF-8'); ?></div>
              <div class="admin-meta">Username: <?php echo htmlspecialchars($admin['username'], ENT_QUOTES, 'UTF-8'); ?></div>
              <div class="admin-meta">Password: <?php echo htmlspecialchars($admin['password'], ENT_QUOTES, 'UTF-8'); ?></div>
              <div class="admin-meta">Center: <?php echo htmlspecialchars($admin['centerName'] !== '' ? $admin['centerName'] : $admin['centerId'], ENT_QUOTES, 'UTF-8'); ?></div>
              <?php if ($admin['centerCode'] !== ''): ?>
                <div class="admin-meta">Code: <?php echo htmlspecialchars($admin['centerCode'], ENT_QUOTES, 'UTF-8'); ?></div>
              <?php endif; ?>
              <?php if ($admin['centerType'] !== ''): ?>
                <div class="admin-meta">Type: <?php echo htmlspecialchars($admin['centerType'], ENT_QUOTES, 'UTF-8'); ?></div>
              <?php endif; ?>
              <?php if ($admin['createdAt'] !== ''): ?>
                <div class="admin-meta">Created: <?php echo htmlspecialchars($admin['createdAt'], ENT_QUOTES, 'UTF-8'); ?></div>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>
