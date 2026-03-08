<?php
// api/verify.php
declare(strict_types=1);

require_once __DIR__ . '/../db/config.php';

$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
$format = strtolower(trim($_GET['format'] ?? $_POST['format'] ?? ''));

if ($token === '') {
    if ($format === 'json') {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => 'Missing token.',
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
                background: rgba(15, 23, 42, 0.96);
                border: 1px solid rgba(30, 64, 175, 0.7);
                box-shadow: 0 20px 45px rgba(15, 23, 42, 0.85);
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
    $pdo = db();
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
            'error' => 'Invalid or expired verification token.',
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
                background: rgba(15, 23, 42, 0.96);
                border: 1px solid rgba(30, 64, 175, 0.7);
                box-shadow: 0 20px 45px rgba(15, 23, 42, 0.85);
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
$userPhone = '+' . $session['user_phone'];
$sessionTok = $session['session_token'];
$tenantId = (int) $session['tenant_id'];
$tenantName = $session['tenant_name'];

if ($format === 'json') {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'tenant_id' => $tenantId,
        'tenant_name' => $tenantName,
        'session_token' => $sessionTok,
        'phone' => $userPhone,
        'verified_at' => $session['verified_at'],
    ]);
    exit;
}

// Set secure cookies for the verified session.
$cookieOptions = [
    'expires' => time() + 3600,
    'path' => '/',
    'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    'httponly' => true,
    'samesite' => 'Strict',
];
setcookie('session_token', (string) $sessionTok, $cookieOptions);
setcookie('user_phone', (string) $userPhone, $cookieOptions);
setcookie('tenant_id', (string) $tenantId, $cookieOptions);

// Simple HTML page for users coming from WhatsApp
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Login Successful</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="refresh" content="3;url=home.php">
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            font-family: "Trebuchet MS", "Segoe UI", sans-serif;
            background: #f5f7fb;
            color: #0f172a;
        }

        .card {
            width: min(360px, 90vw);
            background: #ffffff;
            border-radius: 20px;
            padding: 28px 24px 26px;
            box-shadow: 0 18px 40px rgba(15, 23, 42, 0.12);
            text-align: center;
        }

        .badge {
            width: 110px;
            height: 110px;
            border-radius: 50%;
            margin: 0 auto 16px;
            background: #e9fbe9;
            border: 10px solid #b8f0bf;
            display: grid;
            place-items: center;
            position: relative;
        }

        .badge::after {
            content: "";
            width: 64px;
            height: 64px;
            border-radius: 50%;
            background: #22c55e;
            display: block;
        }

        .check {
            position: absolute;
            width: 34px;
            height: 18px;
            border-left: 6px solid #ffffff;
            border-bottom: 6px solid #ffffff;
            transform: rotate(-45deg);
        }

        h1 {
            margin: 0 0 8px;
            font-size: 22px;
            color: #0f172a;
        }

        p {
            margin: 0;
            font-size: 14px;
            color: #64748b;
        }

        .link {
            margin-top: 16px;
            display: inline-block;
            text-decoration: none;
            color: #0f84b6;
            font-weight: 700;
        }
    </style>
</head>

<body>
    <div class="card">
        <div class="badge">
            <span class="check" aria-hidden="true"></span>
        </div>
        <h1>Login Successful</h1>
        <p>Welcome <?php echo htmlspecialchars($userPhone); ?>. Redirecting to home...</p>
        <a class="link" href="home.php">Go to Home</a>
    </div>

    <script>
        setTimeout(() => {
            window.location.href = "../kyc.php";
        }, 1800);
    </script>
</body>

</html>