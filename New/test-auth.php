<?php
// test-auth.php
declare(strict_types=1);

// 1) Yahan apna API key daalo (admin panel se copy karo)
const TEST_API_KEY = 'wk_live_df9d23c5822af3a5b73afab500ffe3d0';

// 2) API endpoint ka path (same domain pe ho to relative rakho)
const SEND_CODE_ENDPOINT = '/api/send-code.php';

$baseUrl = (function () {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host;
})();

$apiResponse = null;
$errorMsg    = '';
$phoneInput  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phoneInput = trim($_POST['phone'] ?? '');

    if ($phoneInput === '') {
        $errorMsg = 'Please enter a phone number.';
    } elseif (TEST_API_KEY === 'wk_live_your_real_api_key_here') {
        $errorMsg = 'Please set TEST_API_KEY in test-auth.php to a real API key from admin panel.';
    } else {
        $payload = [
            'api_key' => TEST_API_KEY,
            'phone'   => $phoneInput,
        ];

        $url = rtrim($baseUrl, '/') . SEND_CODE_ENDPOINT;

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_TIMEOUT        => 15,
        ]);

        $raw = curl_exec($ch);
        if ($raw === false) {
            $errorMsg = 'Request failed: ' . curl_error($ch);
        } else {
            $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $data   = json_decode($raw, true);

            if (!is_array($data)) {
                $errorMsg = 'Invalid JSON response from API. Status ' . $status;
            } elseif (empty($data['success'])) {
                $errorMsg = 'API error: ' . ($data['error'] ?? 'Unknown error') . ' (HTTP ' . $status . ')';
            } else {
                $apiResponse = $data;
            }
        }
        curl_close($ch);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Webpeaker Auth - Test Send Code</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        :root {
            color-scheme: dark;
        }
        * {
            box-sizing: border-box;
        }
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "SF Pro Text", "Segoe UI", sans-serif;
            background:
                radial-gradient(circle at top left, rgba(34,197,94,0.2), transparent 55%),
                radial-gradient(circle at bottom right, rgba(56,189,248,0.18), transparent 55%),
                #020617;
            color: #e5e7eb;
        }
        .card {
            width: 100%;
            max-width: 520px;
            background: rgba(15,23,42,0.96);
            border-radius: 1.1rem;
            border: 1px solid rgba(30,64,175,0.7);
            box-shadow: 0 24px 60px rgba(15,23,42,0.9);
            padding: 1.8rem 1.6rem 1.6rem;
        }
        .card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            margin-bottom: 0.75rem;
        }
        .title {
            font-size: 1.2rem;
            font-weight: 600;
        }
        .subtitle {
            font-size: 0.87rem;
            color: #9ca3af;
            margin-top: 0.2rem;
        }
        .badge {
            padding: 0.25rem 0.8rem;
            border-radius: 999px;
            border: 1px solid rgba(148,163,184,0.7);
            font-size: 0.75rem;
            color: #e5e7eb;
            white-space: nowrap;
        }
        .field {
            margin-top: 1rem;
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }
        .label {
            font-size: 0.85rem;
            color: #e5e7eb;
            font-weight: 500;
        }
        .input-wrapper {
            display: flex;
            border-radius: 0.75rem;
            border: 1px solid rgba(51,65,85,0.95);
            background: rgba(15,23,42,0.9);
            overflow: hidden;
        }
        .input-prefix {
            padding: 0.65rem 0.9rem;
            font-size: 0.85rem;
            color: #9ca3af;
            border-right: 1px solid rgba(30,41,59,0.9);
            min-width: 58px;
            text-align: center;
        }
        .input {
            flex: 1;
            padding: 0.65rem 0.9rem;
            border: none;
            background: transparent;
            color: #e5e7eb;
            font-size: 0.95rem;
        }
        .input:focus {
            outline: none;
        }
        .input-wrapper:focus-within {
            border-color: #22c55e;
            box-shadow: 0 0 0 1px rgba(34,197,94,0.6);
        }
        .help {
            font-size: 0.8rem;
            color: #9ca3af;
        }
        .button {
            margin-top: 1.1rem;
            width: 100%;
            border-radius: 999px;
            border: none;
            padding: 0.7rem 1rem;
            background: linear-gradient(135deg, #22c55e, #4ade80);
            color: #020617;
            font-weight: 600;
            font-size: 0.95rem;
            cursor: pointer;
            box-shadow: 0 18px 40px rgba(34,197,94,0.55);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.35rem;
        }
        .button:hover {
            filter: brightness(1.05);
        }
        .button span.icon {
            font-size: 1rem;
        }
        .error {
            margin-top: 0.8rem;
            border-radius: 0.75rem;
            border: 1px solid rgba(248,113,113,0.85);
            background: rgba(248,113,113,0.08);
            color: #fecaca;
            font-size: 0.82rem;
            padding: 0.6rem 0.8rem;
        }
        .result {
            margin-top: 1.1rem;
            border-radius: 0.9rem;
            border: 1px solid rgba(34,197,94,0.8);
            background: rgba(22,163,74,0.12);
            padding: 0.7rem 0.9rem;
            font-size: 0.85rem;
            color: #bbf7d0;
        }
        .code-block {
            margin-top: 0.4rem;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
            font-size: 0.8rem;
            background: rgba(15,23,42,0.95);
            border-radius: 0.6rem;
            padding: 0.45rem 0.6rem;
            border: 1px solid rgba(30,41,59,0.95);
            word-break: break-all;
        }
        .wa-button {
            margin-top: 0.7rem;
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.5rem 0.9rem;
            border-radius: 999px;
            text-decoration: none;
            background: #22c55e;
            color: #022c22;
            font-size: 0.85rem;
            font-weight: 600;
        }
        .wa-button span.icon {
            font-size: 1rem;
        }
        @media (max-width: 480px) {
            .card {
                padding: 1.4rem 1.2rem 1.3rem;
            }
            .card-header {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>
<div class="card">
    <div class="card-header">
        <div>
            <div class="title">Test WhatsApp Auth API</div>
            <div class="subtitle">
                Use a tenant API key and phone number to trigger the WhatsApp verification flow.
            </div>
        </div>
        <div class="badge">Internal test</div>
    </div>

    <form method="post" autocomplete="off">
        <div class="field">
            <label class="label" for="phone">Phone number (with country code)</label>
            <div class="input-wrapper">
                <div class="input-prefix">+CC</div>
                <input
                    class="input"
                    id="phone"
                    name="phone"
                    placeholder="9198xxxxxxxx"
                    value="<?php echo htmlspecialchars($phoneInput); ?>"
                    required
                >
            </div>
            <div class="help">
                Example: <code>9198xxxxxxx</code> for Indian numbers.
            </div>
        </div>

        <button type="submit" class="button">
            <span>Send verification code</span>
            <span class="icon">→</span>
        </button>
    </form>

    <?php if ($errorMsg): ?>
        <div class="error">
            <?php echo htmlspecialchars($errorMsg); ?>
        </div>
    <?php endif; ?>

    <?php if ($apiResponse): ?>
        <div class="result">
            <strong>Request created successfully.</strong>
            <div class="help" style="margin-top:0.25rem;">
                Open this link on your device to start WhatsApp verification:
            </div>
            <div class="code-block">
                <?php echo htmlspecialchars($apiResponse['wa_link']); ?>
            </div>
            <a href="<?php echo htmlspecialchars($apiResponse['wa_link']); ?>" target="_blank" class="wa-button">
                <span class="icon">💬</span>
                <span>Open in WhatsApp</span>
            </a>
            <div class="help" style="margin-top:0.4rem;">
                Session token (for backend debug):
            </div>
            <div class="code-block">
                <?php echo htmlspecialchars($apiResponse['session_token'] ?? ''); ?>
            </div>
        </div>
    <?php endif; ?>
</div>
</body>
</html>