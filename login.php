<?php
// test-auth.php
declare(strict_types=1);

// 1) Yahan apna API key daalo (admin panel se copy karo)
const TEST_API_KEY = 'wk_live_c7b249d99431b28d2f534b81c93a6995';

// 2) API endpoint ka path (same domain pe ho to relative rakho)
const SEND_CODE_ENDPOINT = '/api/send-code.php';

$baseUrl = (function () {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host;
})();

$apiResponse = null;
$errorMsg = '';
$phoneInput = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phoneInput = trim($_POST['phone'] ?? '');

    if ($phoneInput === '') {
        $errorMsg = 'Please enter a phone number.';
    } elseif (TEST_API_KEY === 'wk_live_your_real_api_key_here') {
        $errorMsg = 'Please set TEST_API_KEY in test-auth.php to a real API key from admin panel.';
    } else {
        $payload = [
            'api_key' => TEST_API_KEY,
            'phone' => $phoneInput,
        ];

        $url = rtrim($baseUrl, '/') . SEND_CODE_ENDPOINT;

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT => 15,
        ]);

        $raw = curl_exec($ch);
        if ($raw === false) {
            $errorMsg = 'Request failed: ' . curl_error($ch);
        } else {
            $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $data = json_decode($raw, true);

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
    <title>Hackathon </title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: "Trebuchet MS", "Segoe UI", sans-serif;
            background: #f2f4f7;
            color: #1f2937;
        }

        :root {
            --brand-blue: #0aa5c3;
            --brand-purple: #8a6ad8;
            --brand-teal: #1aa6d6;
            --brand-dark: #124058;
            --accent: #0b6fb0;
            --card-shadow: 0 18px 40px rgba(15, 23, 42, 0.12);
        }

        .page {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding-bottom: 2.5rem;
        }

        .hero {
            width: 100%;
            padding: 36px 24px 64px;
            background: url("image.png") center/cover no-repeat;
            border-bottom-left-radius: 28px;
            border-bottom-right-radius: 28px;
            color: #ffffff;
            position: relative;
            overflow: hidden;
        }

        .hero::after {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(180deg, rgba(10, 165, 195, 0.75), rgba(138, 106, 216, 0.7));
        }

        .hero > * {
            position: relative;
            z-index: 1;
        }

        .hero-top {
            display: flex;
            justify-content: flex-end;
            font-size: 13px;
            font-weight: 600;
            text-transform: lowercase;
            opacity: 0.9;
        }

        .hero-top small {
            display: block;
            font-size: 10px;
            font-weight: 500;
            letter-spacing: 0.4px;
        }

        .logo-wrap {
            margin: 20px auto 0;
            width: min(210px, 58vw);
            aspect-ratio: 1;
            border-radius: 50%;
            background: #ffffff;
            box-shadow: 0 14px 36px rgba(0, 0, 0, 0.25);
            display: grid;
            place-items: center;
            position: relative;
        }

        .logo-wrap::before {
            content: "";
            position: absolute;
            inset: 12px;
            border: 6px solid var(--brand-teal);
            border-radius: 50%;
        }

        .logo-img {
            width: 70%;
            height: auto;
            object-fit: contain;
        }

        .form-card {
            width: min(440px, 90vw);
            background: #ffffff;
            border-radius: 22px;
            margin-top: 16px;
            box-shadow: var(--card-shadow);
            padding: 26px 22px 28px;
        }

        .form-title {
            text-align: center;
            font-size: 22px;
            font-weight: 700;
            color: #0f84b6;
            margin: 0 0 18px;
        }

        .field {
            margin-top: 12px;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .label {
            font-size: 15px;
            color: #6b7280;
            font-weight: 600;
        }

        .label span {
            color: #e11d48;
        }

        .input-wrapper {
            border-radius: 16px;
            border: 1px solid #d1d5db;
            background: #f9fafb;
            padding: 12px 14px;
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .input-prefix {
            font-weight: 700;
            color: #6b7280;
        }

        .country-code {
            border: none;
            background: transparent;
            font-weight: 700;
            color: #6b7280;
            font-size: 15px;
        }

        .country-code:focus {
            outline: none;
        }

        .input {
            flex: 1;
            border: none;
            background: transparent;
            font-size: 16px;
            color: #111827;
        }

        .input:focus {
            outline: none;
        }

        .help {
            font-size: 12px;
            color: #9ca3af;
        }

        .actions {
            margin-top: 18px;
            display: grid;
            gap: 12px;
        }

        .button {
            width: 100%;
            border-radius: 16px;
            border: none;
            padding: 12px 16px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
        }

        .button.primary {
            background: linear-gradient(90deg, rgba(10, 165, 195, 0.75), rgba(138, 106, 216, 0.7));
            color: #ffffff;
        }

        .button.secondary {
            background: #ffffff;
            border: 1px solid #cbd5f5;
            color: #0f84b6;
        }

        .error,
        .result {
            margin-top: 16px;
            border-radius: 14px;
            padding: 12px 14px;
            font-size: 13px;
        }

        .error {
            border: 1px solid #fca5a5;
            background: #fef2f2;
            color: #b91c1c;
        }

        .result {
            border: 1px solid #86efac;
            background: #f0fdf4;
            color: #166534;
        }

        .code-block {
            margin-top: 6px;
            font-family: "Courier New", Courier, monospace;
            font-size: 12px;
            background: #ffffff;
            border-radius: 10px;
            padding: 8px 10px;
            border: 1px solid #e5e7eb;
            word-break: break-all;
        }

        .wa-button {
            margin-top: 10px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 12px;
            border-radius: 999px;
            text-decoration: none;
            background: #22c55e;
            color: #0b3b1a;
            font-size: 13px;
            font-weight: 700;
             cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
        }

        @media (max-width: 480px) {
            .hero {
                padding: 28px 18px 44px;
            }

            .form-card {
                margin-top: 18px;
                padding: 22px 18px 24px;
            }
        }
    </style>
</head>

<body>
    <div class="page">
        <header class="hero">
            <div class="hero-top">
              Team Name : 
                <small>TheHackerTeam</small>
            </div>
            <div class="logo-wrap">
                <img class="logo-img" src="logo.png" alt="PM-JAY logo">
            </div>
        </header>

        <section class="form-card">
            <h1 class="form-title">Register / Login</h1>

            <form method="post" autocomplete="off" id="loginForm">
                <div class="field">
                    <label class="label" for="phone">Mobile Number<span>*</span></label>
                    <div class="input-wrapper">
                        <select class="country-code" id="countryCode" aria-label="Country code">
                            <option value="+91" selected>+91</option>
                            <option value="+1">+1</option>
                            <option value="+44">+44</option>
                            <option value="+971">+971</option>
                        </select>
                        <input class="input" id="phone" name="phone" placeholder="Enter your mobile number"
                            value="<?php echo htmlspecialchars($phoneInput); ?>" required>
                    </div>
                    <div class="help">We will send an OTP to verify your number.</div>
                </div>

                <div class="actions">
                    <?php if ($apiResponse): ?>
                        <a class="button primary" style="text-decoration: none; text-align:center;" href="<?php echo htmlspecialchars($apiResponse['wa_link']); ?>" target="_blank">
                            Register / Login
                        </a>
                    <?php else: ?>
                        <button type="submit" class="button primary">Register / Login</button>
                    <?php endif; ?>
                </div>
            </form>

        </section>
    </div>

    <script>
        const form = document.getElementById("loginForm");
        const codeSelect = document.getElementById("countryCode");
        const phoneInput = document.getElementById("phone");

        form.addEventListener("submit", () => {
            const code = codeSelect.value.replace(/\s+/g, "");
            const phone = phoneInput.value.replace(/\s+/g, "");
            if (phone && !phone.startsWith(code.replace("+", "")) && !phone.startsWith(code)) {
                phoneInput.value = code.replace("+", "") + phone;
            }
        });
    </script>
</body>

</html>
