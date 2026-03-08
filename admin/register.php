<?php
declare(strict_types=1);

$credentialsPath = resolveCredentialsPath();
$errorMessage = '';
$successMessage = '';

$form = [
    'username' => '',
    'password' => '',
    'centerId' => '',
    'centerCode' => '',
    'centerName' => '',
    'centerType' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($form as $key => $value) {
        $form[$key] = trim((string) ($_POST[$key] ?? ''));
    }

    if ($form['username'] === '' || $form['password'] === '' || $form['centerId'] === '') {
        $errorMessage = 'Username, password, and centerId are required.';
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
            $docId = 'admin-' . preg_replace('/[^a-zA-Z0-9_-]/', '-', $form['username']);
            $createdAt = gmdate('c');
            createAdmin($projectId, $token, $docId, $form, $createdAt);
            $successMessage = 'Admin registered successfully.';
        } catch (Throwable $e) {
            error_log('Admin register failed: ' . $e->getMessage());
            $errorMessage = 'Unable to register admin.';
        }
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

function createAdmin(string $projectId, string $token, string $docId, array $form, string $createdAt): void
{
    $url = 'https://firestore.googleapis.com/v1/projects/' . rawurlencode($projectId)
        . '/databases/(default)/documents/admins/' . rawurlencode($docId);
    $payload = [
        'fields' => [
            'id' => ['stringValue' => $docId],
            'username' => ['stringValue' => $form['username']],
            'password' => ['stringValue' => $form['password']],
            'centerId' => ['stringValue' => $form['centerId']],
            'centerCode' => ['stringValue' => $form['centerCode']],
            'centerName' => ['stringValue' => $form['centerName']],
            'centerType' => ['stringValue' => $form['centerType']],
            'createdAt' => ['timestampValue' => $createdAt],
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
  <title>GovEase Admin - Register</title>
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

    .form-card {
      border: 1px solid var(--border-color);
      border-radius: var(--radius-lg);
      padding: 1.5rem 1.25rem;
      margin: 0 1.25rem 1rem;
      background-color: var(--white);
    }

    .form-grid {
      display: grid;
      gap: 0.85rem;
    }

    .form-grid label {
      font-size: 0.85rem;
      color: #5c728a;
    }

    .form-grid input {
      width: 100%;
      padding: 0.7rem 0.85rem;
      border-radius: var(--radius-md);
      border: 1px solid var(--border-color);
      background: #f8fafc;
    }

    .form-actions {
      display: flex;
      gap: 0.5rem;
      flex-wrap: wrap;
      margin-top: 1rem;
    }

    .form-actions a,
    .form-actions button {
      border: 1px solid var(--border-color);
      border-radius: 999px;
      padding: 0.6rem 1rem;
      background: var(--white);
      color: #334155;
      font-size: 0.85rem;
      cursor: pointer;
    }

    .form-actions button {
      background: var(--primary);
      color: var(--white);
      border: none;
    }

    .notice {
      margin: 0 1.25rem 1rem;
      padding: 0.75rem 1rem;
      border-radius: var(--radius-md);
      font-size: 0.9rem;
    }

    .notice.success {
      background: #ecfdf3;
      border: 1px solid #bbf7d0;
      color: #15803d;
    }

    .notice.error {
      background: #fef2f2;
      border: 1px solid #fecaca;
      color: #b91c1c;
    }
  </style>
</head>
<body>
  <div class="container page-wrapper">
    <div class="page-header">
      <h1>Register Admin</h1>
      <p>Create a new company admin account.</p>
    </div>

    <?php if ($successMessage !== ''): ?>
      <div class="notice success"><?php echo htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>
    <?php if ($errorMessage !== ''): ?>
      <div class="notice error"><?php echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <div class="form-card">
      <form class="form-grid" method="post">
        <div>
          <label for="username">Username</label>
          <input id="username" name="username" type="text" value="<?php echo htmlspecialchars($form['username'], ENT_QUOTES, 'UTF-8'); ?>">
        </div>
        <div>
          <label for="password">Password</label>
          <input id="password" name="password" type="text" value="<?php echo htmlspecialchars($form['password'], ENT_QUOTES, 'UTF-8'); ?>">
        </div>
        <div>
          <label for="centerId">Center ID</label>
          <input id="centerId" name="centerId" type="text" value="<?php echo htmlspecialchars($form['centerId'], ENT_QUOTES, 'UTF-8'); ?>">
        </div>
        <div>
          <label for="centerCode">Center Code</label>
          <input id="centerCode" name="centerCode" type="text" value="<?php echo htmlspecialchars($form['centerCode'], ENT_QUOTES, 'UTF-8'); ?>">
        </div>
        <div>
          <label for="centerName">Center Name</label>
          <input id="centerName" name="centerName" type="text" value="<?php echo htmlspecialchars($form['centerName'], ENT_QUOTES, 'UTF-8'); ?>">
        </div>
        <div>
          <label for="centerType">Center Type</label>
          <input id="centerType" name="centerType" type="text" value="<?php echo htmlspecialchars($form['centerType'], ENT_QUOTES, 'UTF-8'); ?>">
        </div>
        <div class="form-actions">
          <button type="submit">Create Admin</button>
          <a href="admin_login.php">Back to Login</a>
          <a href="view_admin.php">View Admins</a>
        </div>
      </form>
    </div>
  </div>
</body>
</html>
