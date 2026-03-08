<?php
declare(strict_types=1);

session_start();

if (!empty($_SESSION['admin_user'])) {
    header('Location: admin_portal.php');
    exit;
}

$credentialsPath = resolveCredentialsPath();
$projectId = '';
$token = '';
$apiKey = '';
$authMode = 'service';
$errorMessage = '';
$usernameInput = '';

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
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usernameInput = trim((string) ($_POST['username'] ?? ''));
    $passwordInput = trim((string) ($_POST['password'] ?? ''));

    if ($usernameInput === '' || $passwordInput === '') {
        $errorMessage = 'Username and password are required.';
    } elseif ($authMode === 'api' && ($projectId === '' || $apiKey === '')) {
        $errorMessage = 'Unable to sign in. Please try again.';
    } else {
        try {
            $admin = $authMode === 'api'
                ? fetchAdminByUsernameWithApiKey($projectId, $apiKey, $usernameInput)
                : fetchAdminByUsername($projectId, $token, $usernameInput);
            if (!$admin || ($admin['password'] ?? '') !== $passwordInput) {
                $errorMessage = 'Invalid username or password.';
            } else {
                $_SESSION['admin_user'] = $admin['username'] ?? $usernameInput;
                $_SESSION['admin_center_id'] = $admin['centerId'] ?? '';
                $_SESSION['admin_center_name'] = $admin['centerName'] ?? '';
                $_SESSION['admin_center_code'] = $admin['centerCode'] ?? '';
                $_SESSION['admin_center_type'] = $admin['centerType'] ?? '';
                header('Location: admin_portal.php');
                exit;
            }
        } catch (Throwable $e) {
            error_log('Admin login failed: ' . $e->getMessage());
            $errorMessage = 'Unable to sign in. Please try again.';
        }
    }
}

function fetchAdminByUsername(string $projectId, string $token, string $username): ?array
{
    $payload = [
        'structuredQuery' => [
            'from' => [
                ['collectionId' => 'admins'],
            ],
            'where' => [
                'fieldFilter' => [
                    'field' => ['fieldPath' => 'username'],
                    'op' => 'EQUAL',
                    'value' => ['stringValue' => $username],
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
        return [
            'id' => getFieldString($fields, 'id'),
            'username' => getFieldString($fields, 'username'),
            'password' => getFieldString($fields, 'password'),
            'centerId' => getFieldString($fields, 'centerId'),
            'centerCode' => getFieldString($fields, 'centerCode'),
            'centerName' => getFieldString($fields, 'centerName'),
            'centerType' => getFieldString($fields, 'centerType'),
        ];
    }
    return null;
}

function fetchAdminByUsernameWithApiKey(string $projectId, string $apiKey, string $username): ?array
{
    $admins = fetchAdminsWithApiKey($projectId, $apiKey);
    foreach ($admins as $admin) {
        if (strcasecmp($admin['username'] ?? '', $username) === 0) {
            return $admin;
        }
    }
    return null;
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

function resolveFirebaseConfig(): array
{
    $candidates = [
        dirname(__DIR__) . '/firebase.json',
        __DIR__ . '/firebase.json',
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

function fetchAdminsWithApiKey(string $projectId, string $apiKey): array
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
        $url = appendApiKey($url, $apiKey);
        $response = curlJsonNoAuth('GET', $url, []);
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
  <title>GovEase Admin - Login</title>
  <link rel="stylesheet" href="../style.css">
  <style>
    body {
      background-color: var(--bg-color);
    }

    .page-wrapper {
      padding-top: 2rem;
      padding-bottom: 2rem;
    }

    .admin-header {
      margin-bottom: 1.5rem;
      padding: 0 1.25rem;
    }

    .admin-header h1 {
      font-size: 1.6rem;
      margin-bottom: 0.35rem;
    }

    .admin-header p {
      color: var(--text-muted);
      font-size: 0.9rem;
    }

    .admin-form {
      display: grid;
      gap: 0.9rem;
    }

    .admin-form label {
      font-size: 0.85rem;
      color: var(--text-muted);
    }

    .admin-form input {
      width: 100%;
      padding: 0.7rem 0.85rem;
      border-radius: var(--radius-md);
      border: 1px solid var(--border-color);
      background: #f8fafc;
    }

    .admin-btn {
      width: 100%;
      padding: 0.75rem 1rem;
      border-radius: var(--radius-md);
      background: var(--primary);
      color: var(--white);
      font-weight: 600;
      border: none;
      cursor: pointer;
    }

    .admin-error {
      padding: 0.75rem;
      border-radius: var(--radius-md);
      border: 1px solid #fecaca;
      color: #b91c1c;
      background: #fef2f2;
      font-size: 0.85rem;
      margin: 0 1.25rem 1rem;
    }

    .admin-card {
      border: 1px solid var(--border-color);
      border-radius: var(--radius-lg);
      padding: 1.5rem 1.25rem;
      margin: 0 1.25rem 1rem;
      background-color: var(--white);
    }
  </style>
</head>
<body>
  <div class="container page-wrapper">
    <div class="admin-header">
      <h1>Company Admin</h1>
      <p>Sign in to manage center approvals.</p>
    </div>

    <?php if ($errorMessage !== ''): ?>
      <div class="admin-error"><?php echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <div class="admin-card">
      <form class="admin-form" method="post">
        <div>
          <label for="username">Username</label>
          <input id="username" name="username" type="text" value="<?php echo htmlspecialchars($usernameInput, ENT_QUOTES, 'UTF-8'); ?>" autocomplete="username">
        </div>
        <div>
          <label for="password">Password</label>
          <input id="password" name="password" type="password" autocomplete="current-password">
        </div>
        <button class="admin-btn" type="submit">Sign in</button>
      </form>
      <div style="margin-top: 1rem; display: flex; gap: 0.5rem; flex-wrap: wrap;">
        <a class="admin-btn" style="text-align:center; background:#0f172a;" href="register.php">Register Admin</a>
        <a class="admin-btn" style="text-align:center; background:#64748b;" href="view_admin.php">View Admins</a>
      </div>
    </div>
  </div>
</body>
</html>
