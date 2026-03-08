<?php
// api/verify.php
declare(strict_types=1);

require_once __DIR__ . '/../db/config.php';

$token  = trim($_GET['token'] ?? $_POST['token'] ?? '');
$format = strtolower(trim($_GET['format'] ?? $_POST['format'] ?? ''));

if ($token === '') {
    if ($format === 'json') {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error'   => 'Missing token.',
        ]);
        exit;
    }

    http_response_code(400);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Verification failed</title>
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <style>
            body {
                margin: 0;
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                background: radial-gradient(circle at top, #020617 0, #020617 40%, #020617 80%);
                font-family: system-ui, -apple-system, BlinkMacSystemFont, "SF Pro Text", "Segoe UI", sans-serif;
                color: #e5e7eb;
            }
            .card {
                max-width: 380px;
                width: 100%;
                padding: 1.8rem 1.6rem;
                border-radius: 1rem;
                background: rgba(15,23,42,0.96);
                border: 1px solid rgba(30,64,175,0.7);
                box-shadow: 0 20px 45px rgba(15,23,42,0.85);
                text-align: center;
            }
            h1 {
                font-size: 1.3rem;
                margin-bottom: 0.4rem;
            }
            p {
                font-size: 0.9rem;
                color: #9ca3af;
            }
        </style>
    </head>
    <body>
        <div class="card">
            <h1>Verification link invalid</h1>
            <p>This verification link is missing or invalid. Please restart the login from the website.</p>
        </div>
    </body>
    </html>
    <?php
    exit;
}

try {
    $pdo   = db();
    $table = table('verification_sessions');

    $sql = "
        SELECT s.*, t.name AS tenant_name
        FROM {$table} s
        INNER JOIN wa_tenants t ON t.id = s.tenant_id
        WHERE s.wa_verify_token = :token
          AND s.status = 'verified'
          AND s.expires_at >= NOW()
        LIMIT 1
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':token' => $token]);
    $session = $stmt->fetch();
} catch (Throwable $e) {
    error_log('Verify lookup failed: ' . $e->getMessage());
    $session = null;
}

if (!$session) {
    if ($format === 'json') {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error'   => 'Invalid or expired verification token.',
        ]);
        exit;
    }

    http_response_code(410);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Verification expired</title>
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <style>
            body {
                margin: 0;
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                background: radial-gradient(circle at top, #020617 0, #020617 40%, #020617 80%);
                font-family: system-ui, -apple-system, BlinkMacSystemFont, "SF Pro Text", "Segoe UI", sans-serif;
                color: #e5e7eb;
            }
            .card {
                max-width: 380px;
                width: 100%;
                padding: 1.8rem 1.6rem;
                border-radius: 1rem;
                background: rgba(15,23,42,0.96);
                border: 1px solid rgba(30,64,175,0.7);
                box-shadow: 0 20px 45px rgba(15,23,42,0.85);
                text-align: center;
            }
            h1 {
                font-size: 1.3rem;
                margin-bottom: 0.4rem;
            }
            p {
                font-size: 0.9rem;
                color: #9ca3af;
            }
        </style>
    </head>
    <body>
        <div class="card">
            <h1>Verification expired</h1>
            <p>This verification link is no longer valid. Please restart the login on the website.</p>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Success
$userPhone   = '+' . $session['user_phone'];
$sessionTok  = $session['session_token'];
$tenantId    = (int) $session['tenant_id'];
$tenantName  = $session['tenant_name'];

if ($format === 'json') {
    header('Content-Type: application/json');
    echo json_encode([
        'success'       => true,
        'tenant_id'     => $tenantId,
        'tenant_name'   => $tenantName,
        'session_token' => $sessionTok,
        'phone'         => $userPhone,
        'verified_at'   => $session['verified_at'],
    ]);
    exit;
}

// Simple HTML page for users coming from WhatsApp
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Number verified</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: radial-gradient(circle at top, #020617 0, #020617 40%, #020617 80%);
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "SF Pro Text", "Segoe UI", sans-serif;
            color: #e5e7eb;
        }
        .card {
            max-width: 410px;
            width: 100%;
            padding: 1.9rem 1.7rem;
            border-radius: 1.1rem;
            background: rgba(15,23,42,0.96);
            border: 1px solid rgba(34,197,94,0.7);
            box-shadow: 0 20px 45px rgba(22,163,74,0.65);
            text-align: center;
        }
        h1 {
            font-size: 1.35rem;
            margin-bottom: 0.4rem;
        }
        p {
            font-size: 0.9rem;
            color: #9ca3af;
            margin-bottom: 0.8rem;
        }
        .pill {
            display: inline-block;
            border-radius: 999px;
            padding: 0.25rem 0.8rem;
            background: rgba(22,163,74,0.18);
            color: #bbf7d0;
            font-size: 0.78rem;
            margin-bottom: 0.55rem;
        }
        .meta {
            font-size: 0.8rem;
            color: #64748b;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="pill">WhatsApp verified</div>
        <h1>Number verified successfully</h1>
        <p>
            Your phone <strong><?php echo htmlspecialchars($userPhone); ?></strong>
            has been verified for
            <strong><?php echo htmlspecialchars($tenantName); ?></strong>.
        </p>
        <p class="meta">
            You can now return to the website or app where you started the login.
        </p>
    </div>
</body>
</html>