<?php
// test-auth.php
declare(strict_types=1);

// 1) Yahan apna real API key daalo (admin panel se)
const TEST_API_KEY = 'wk_live_df9d23c5822af3a5b73afab500ffe3d0';

// 2) API endpoint
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
        $errorMsg = 'Please set TEST_API_KEY in test-auth.php to a real API key.';
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
                $errorMsg = "Invalid JSON response (HTTP $status)";
            } elseif (empty($data['success'])) {
                $errorMsg = 'API error: ' . ($data['error'] ?? 'Unknown');
            } else {
                // ✅ SUCCESS → DIRECT REDIRECT TO WHATSAPP LINK
                if (!empty($data['wa_link'])) {
                    header('Location: ' . $data['wa_link']);
                    exit;
                } else {
                    $errorMsg = 'Success but wa_link missing in response.';
                }
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
<title>Apna Auth – WhatsApp Login</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet"
      href="https://cdn.jsdelivr.net/npm/intl-tel-input@18.5.2/build/css/intlTelInput.css">

<script src="https://cdn.jsdelivr.net/npm/intl-tel-input@18.5.2/build/js/intlTelInput.min.js"></script>
<style>
    * { box-sizing: border-box; }

    body {
        margin: 0;
        font-family: system-ui, -apple-system, BlinkMacSystemFont, "SF Pro Text", "Segoe UI", sans-serif;
        background: #00bfa5; /* ApnaAuth teal */
        color: #ffffff;
        display: flex;
        align-items: stretch;
        justify-content: center;
    }

    .page {
        width: 100%;
        max-width: 1200px;
        margin: 0 auto;
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 2rem 1.5rem;
        gap: 2rem;
    }

    /* LEFT SIDE HERO */
    .left {
        flex: 1.2;
        max-width: 560px;
    }

    .badge {
        display: inline-flex;
        align-items: center;
        padding: 6px 16px;
        border-radius: 999px;
        background: rgba(0, 118, 102, 0.9);
        font-size: 0.85rem;
        font-weight: 500;
        margin-bottom: 1.1rem;
    }

    .headline {
        font-size: clamp(2rem, 3vw + 1rem, 3rem);
        line-height: 1.15;
        font-weight: 700;
        margin-bottom: 0.7rem;
    }

    .headline span {
        display: block;
    }

    .subtext {
        font-size: 0.98rem;
        line-height: 1.6;
        max-width: 30rem;
        opacity: 0.9;
    }

    /* RIGHT SIDE CARD */
    .right {
        flex: 0.9;
        display: flex;
        justify-content: center;
    }

    .card {
        width: 100%;
        max-width: 380px;
        background: #ffffff;
        color: #111827;
        border-radius: 22px;
        padding: 1.8rem 1.7rem 1.6rem;
        box-shadow: 0 20px 40px rgba(0,0,0,0.18);
    }

    .logo-circle {
        width: 56px;
        height: 56px;
        border-radius: 50%;
        background: #00bfa5;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #ffffff;
        font-weight: 700;
        font-size: 1.4rem;
        margin-bottom: 0.8rem;
    }

    /* replace this text with <img> if you have logo:
       .logo-circle { background:#00bfa5 url('logo.png') center/cover no-repeat; font-size:0; } */

    .card-title {
        font-size: 1.15rem;
        font-weight: 600;
        margin-bottom: 0.25rem;
    }

    .card-desc {
        font-size: 0.9rem;
        color: #6b7280;
        margin-bottom: 1.2rem;
    }

    label {
        display: block;
        font-size: 0.85rem;
        font-weight: 500;
        color: #374151;
        margin-bottom: 0.3rem;
    }

    .input {
        width: 100%;
        padding: 0.7rem 0.9rem;
        border-radius: 10px;
        border: 1px solid #d1d5db;
        background: #f9fafb;
        font-size: 0.95rem;
        color: #111827;
    }

    .input::placeholder {
        color: #9ca3af;
    }

    .input:focus {
        outline: none;
        border-color: #fb923c;
        box-shadow: 0 0 0 1px #fed7aa;
        background: #ffffff;
    }

    .help {
        margin-top: 0.35rem;
        font-size: 0.78rem;
        color: #9ca3af;
    }

    .button {
        margin-top: 1.1rem;
        width: 100%;
        padding: 0.8rem 1rem;
        border-radius: 999px;
        border: none;
        font-size: 0.95rem;
        font-weight: 600;
        cursor: pointer;
        background: #f97316; /* orange */
        color: #ffffff;
    }

    .button:hover {
        background: #ea580c;
    }

    .error {
        margin-top: 0.85rem;
        padding: 0.7rem 0.8rem;
        border-radius: 10px;
        background: #fef2f2;
        border: 1px solid #fecaca;
        color: #b91c1c;
        font-size: 0.82rem;
    }

    @media (max-width: 900px) {
        .page {
            flex-direction: column;
            align-items: flex-start;
            padding-top: 3rem;
            padding-bottom: 3rem;
        }
        .left, .right {
            max-width: 100%;
        }
        .right {
            align-self: center;
        }
    }
</style>
</head>
<body>

<div class="page">
    <!-- LEFT HERO CONTENT -->
    <!--<div class="left">-->
    <!--    <div class="badge">New · Bharat-first OTPless authentication</div>-->
    <!--    <div class="headline">-->
    <!--        <span>Passwordless/OTPless Indian</span>-->
    <!--        <span>authentication</span>-->
    <!--        <span>Fast · Secured · Free.</span>-->
    <!--    </div>-->
    <!--    <p class="subtext">-->
    <!--        Apna Auth lets users verify with a three-tap WhatsApp button flow instead of entering OTPs,-->
    <!--        cutting SMS costs and improving security and conversion.-->
    <!--    </p>-->
    <!--</div>-->

    <!-- RIGHT LOGIN CARD -->
    <div class="right">
        <div class="card">
            <center><img src="https://auth.webpeaker.com/image/logo.png" width="100"></center> <!-- Yahan aapka logo initial / icon -->
            <div class="card-title">Continue with WhatsApp</div>
            <div class="card-desc">
                Enter your phone number to start the Apna Auth login flow.
            </div>
<form method="post" autocomplete="off">  
            <label for="phone">Phone number</label>  <input
    id="phone"
    class="input"
    type="tel"
    placeholder="Enter phone number"
    required
>

<input type="hidden" name="phone" id="phone_e164">

            <button type="submit" class="button">  
                Continue with WhatsApp  
            </button>  
        </form>
<div class="help">Select country and enter mobile number.</div>
            <?php if ($errorMsg): ?>
                <div class="error">
                    <?php echo htmlspecialchars($errorMsg); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<script>
const phoneInput = document.querySelector("#phone");
const hiddenPhone = document.querySelector("#phone_e164");
const form = document.querySelector("form");

const iti = window.intlTelInput(phoneInput, {
    initialCountry: "in",
    separateDialCode: true,
    nationalMode: true,
    formatOnDisplay: true,
    autoPlaceholder: "polite",
    utilsScript:
      "https://cdn.jsdelivr.net/npm/intl-tel-input@18.5.2/build/js/utils.js"
});

// 🔒 HARD DIGIT LIMIT (REAL FIX)
phoneInput.addEventListener("input", () => {
    const country = iti.getSelectedCountryData();

    if (!country.iso2) return;

    // Get example mobile number for that country
    const example = intlTelInputUtils.getExampleNumber(
        country.iso2,
        true,
        intlTelInputUtils.numberType.MOBILE
    );

    if (!example) return;

    const maxDigits = example.replace(/\D/g, "").length;
    const digits = phoneInput.value.replace(/\D/g, "");

    // ✂️ TRIM immediately (works on mobile + paste)
    if (digits.length > maxDigits) {
        phoneInput.value = digits.slice(0, maxDigits);
    }
});

// ✅ SUBMIT (NO UI MUTATION)
form.addEventListener("submit", (e) => {
    if (!iti.isValidNumber()) {
        e.preventDefault();
        alert("Please enter a valid phone number");
        return;
    }

    // Put clean digits into hidden field
    hiddenPhone.value = iti.getNumber().replace("+", "");
});
</script>
<script>
phoneInput.addEventListener("input", () => {
    const country = iti.getSelectedCountryData();
    const digits  = phoneInput.value.replace(/\D/g, '');

    let maxDigits;

    if (country.iso2 === "in") {
        // 🇮🇳 STRICT India rule (WhatsApp style)
        maxDigits = 10;
    } else {
        // 🌍 Other countries → use example length
        const example = intlTelInputUtils.getExampleNumber(
            country.iso2,
            true,
            intlTelInputUtils.numberType.MOBILE
        );

        if (!example) return;
        maxDigits = example.replace(/\D/g, "").length;
    }

    if (digits.length > maxDigits) {
        phoneInput.value = digits.slice(0, maxDigits);
    }
});
</script>
</body>
</html>
