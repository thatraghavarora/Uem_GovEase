<?php
declare(strict_types=1);

const TEST_API_KEY = 'wk_live_df9d23c5822af3a5b73afab500ffe3d0';
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
    $errorMsg = 'Please set TEST_API_KEY in sgvu.php to a real API key.';
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
        $errorMsg = "Invalid JSON response (HTTP $status)";
      } elseif (empty($data['success'])) {
        $errorMsg = 'API error: ' . ($data['error'] ?? 'Unknown');
      } else {
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
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>SGVU Industry Aligned Programs</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/intl-tel-input@18.5.2/build/css/intlTelInput.css" rel="stylesheet">
  <style>
    :root {
      --nav-height: 72px;
      --hero-bg: #4a4a4a;
      --accent-blue: #0d57ff;
      --accent-blue-dark: #0b49d6;
      --pill-gray: #6c6f74;
    }

    body {
      font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
      background: #f3f4f6;
      color: #111827;
    }

    .top-bar {
      height: var(--nav-height);
      background: #fff;
      border-bottom: 1px solid #e5e7eb;
    }

    .brand-logo {
      width: 48px;
      height: 48px;
      border-radius: 999px;
      border: 2px solid #f1c40f;
      display: grid;
      place-items: center;
      font-weight: 700;
      color: #9a7500;
      background: #fff6d6;
    }

    .brand-text small {
      display: block;
      font-size: 0.7rem;
      letter-spacing: 0.2rem;
      color: #6b7280;
    }

    .hero {
      min-height: calc(100vh - var(--nav-height) - 64px);
      background: var(--hero-bg);
      color: #fff;
      position: relative;
      overflow: hidden;
    }

    .hero::before,
    .hero::after {
      content: "";
      position: absolute;
      border-radius: 999px;
      filter: blur(0.5px);
      opacity: 0.2;
    }

    .hero::before {
      width: 320px;
      height: 320px;
      background: radial-gradient(circle, rgba(255, 255, 255, 0.7), rgba(255, 255, 255, 0));
      top: -120px;
      left: -80px;
    }

    .hero::after {
      width: 480px;
      height: 480px;
      background: radial-gradient(circle, rgba(13, 87, 255, 0.7), rgba(13, 87, 255, 0));
      bottom: -220px;
      right: -200px;
    }

    .hero-pill {
      background: rgba(255, 255, 255, 0.12);
      border: 1px solid rgba(255, 255, 255, 0.2);
      padding: 0.35rem 1rem;
      border-radius: 999px;
      font-size: 0.9rem;
      color: #f9fafb;
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
    }

    .hero-title {
      font-weight: 800;
      font-size: clamp(2.5rem, 4vw, 3.4rem);
      line-height: 1.1;
      text-shadow: 0 4px 16px rgba(0, 0, 0, 0.25);
    }

    .hero-copy {
      color: #e5e7eb;
      max-width: 520px;
      margin-top: 1.25rem;
      font-size: 1.05rem;
    }

    .apply-btn {
      background: #fff;
      color: #111827;
      border-radius: 8px;
      padding: 0.7rem 1.6rem;
      font-weight: 600;
      border: none;
      box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
    }

    .login-card {
      background: #fff;
      border-radius: 10px;
      box-shadow: 0 12px 28px rgba(0, 0, 0, 0.25);
      padding: 0;
      overflow: hidden;
      max-width: 360px;
      margin-left: auto;
    }

    .login-tabs {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      background: #efefef;
    }

    .login-tabs button {
      padding: 0.45rem 0;
      border: none;
      background: transparent;
      font-weight: 600;
      color: #6b7280;
    }

    .login-tabs button.active {
      background: var(--accent-blue);
      color: #fff;
    }

    .otp-inputs {
      display: flex;
      gap: 0.5rem;
      justify-content: center;
      margin: 1rem 0;
    }

    .otp-inputs input {
      width: 34px;
      height: 40px;
      text-align: center;
      border: none;
      border-bottom: 2px solid #d1d5db;
      font-size: 1.1rem;
      outline: none;
    }

    .otp-inputs input:focus {
      border-color: var(--accent-blue);
    }

    .iti {
      width: 100%;
    }

    .phone-input {
      height: 44px;
    }

    .submit-btn {
      background: #6b8cff;
      border: none;
      color: #fff;
      font-weight: 600;
      padding: 0.6rem 1.4rem;
      border-radius: 4px;
    }

    .bottom-bar {
      background: #0b3ac9;
      color: #fff;
      height: 64px;
    }

    .bottom-bar .apply-mini {
      background: var(--accent-blue);
      border: none;
      color: #fff;
      padding: 0.55rem 1.4rem;
      border-radius: 6px;
    }

    .admission-btn {
      background: var(--accent-blue);
      color: #fff;
      border-radius: 6px;
      padding: 0.55rem 1.2rem;
      border: none;
      box-shadow: 0 4px 10px rgba(13, 87, 255, 0.3);
    }

    @media (max-width: 991px) {
      .hero {
        padding-bottom: 3rem;
      }

      .login-card {
        margin: 2rem auto 0;
      }
    }
  </style>
</head>

<body>
  <header class="top-bar d-flex align-items-center">
    <div class="container d-flex align-items-center justify-content-between">
      <div class="d-flex align-items-center gap-3">
        <div class="brand-logo">SG</div>
        <div class="brand-text">
          <div class="fw-bold text-uppercase">Suresh Gyan Vihar</div>
          <small>UNIVERSITY</small>
        </div>
        <div class="ms-2 d-none d-md-flex align-items-center gap-2">
          <div class="badge bg-light text-dark border">A+</div>
          <span class="text-muted small">NAAC</span>
        </div>
      </div>
      <div class="d-none d-lg-flex align-items-center gap-4 text-muted">
        <div><i class="fa-solid fa-phone me-2 text-primary"></i>1800 309 4545</div>
        <div><i class="fa-solid fa-envelope me-2 text-primary"></i>admissions@mygyanvihar.com</div>
      </div>
      <button class="admission-btn">Admission Open for 2026-27</button>
    </div>
  </header>

  <main class="hero d-flex align-items-center">
    <div class="container position-relative">
      <div class="row align-items-center">
        <div class="col-lg-6">
          <span class="hero-pill mb-3">
            <i class="fa-solid fa-feather-pointed"></i>
            Give wings to your dreams with...
          </span>
          <h1 class="hero-title">SGVU's Industry<br>Aligned Programs</h1>
          <p class="hero-copy">
            An Education Without Limits—Flexible, Affordable, and Global. Your success, your way.
          </p>
          <button class="apply-btn mt-3">Apply Now</button>
        </div>
        <div class="col-lg-6 d-flex justify-content-lg-end">
          <div class="login-card">
            <div class="login-tabs">
              <button class="active" type="button" data-tab="register">Register</button>
              <button type="button" data-tab="login">Login</button>
            </div>
            <div class="p-4">
              <form method="post" autocomplete="off">
                <div class="input-group mb-3">

                  <input id="phone" class="form-control phone-input" type="tel" placeholder="Enter Mobile No *"
                    required>
                </div>
                <input type="hidden" name="phone" id="phone_e164" value="<?php echo htmlspecialchars($phoneInput); ?>">
                <div class="d-grid gap-2">
                  <button class="submit-btn" type="submit" name="auth_method" value="whatsapp">Continue with
                    WhatsApp</button>
                  <div class="text-center mt-2">
                    <a class="small fw-semibold text-decoration-none" href="#">Login another way</a>
                  </div>
                  <button class="btn btn-outline-primary" type="submit" name="auth_method" value="otp">Send OTP</button>
                   <button class="btn btn-outline-danger" type="button">
                    <i class="fa-brands fa-google me-2"></i>Login with Google
                  </button>
                </div>

              </form>
              <div class="text-center mt-3">
                <div class="small fw-semibold">Login Via Password</div>
                <div class="small fw-semibold">Click Here To Register</div>
              </div>
              <?php if ($errorMsg): ?>
                <div class="alert alert-danger mt-3 mb-0 small">
                  <?php echo htmlspecialchars($errorMsg); ?>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    </div>
  </main>

  <section class="bottom-bar d-flex align-items-center">
    <div class="container d-flex align-items-center justify-content-between">
      <div class="fw-semibold">Admissions Open - Session 2026-27</div>
      <button class="apply-mini">Apply Now</button>
    </div>
  </section>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/intl-tel-input@18.5.2/build/js/intlTelInput.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/intl-tel-input@18.5.2/build/js/utils.js"></script>
  <script>
    const tabButtons = document.querySelectorAll(".login-tabs button");
    tabButtons.forEach((btn) => {
      btn.addEventListener("click", () => {
        tabButtons.forEach((item) => item.classList.remove("active"));
        btn.classList.add("active");
      });
    });

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

    phoneInput.addEventListener("input", () => {
      const country = iti.getSelectedCountryData();
      const digits = phoneInput.value.replace(/\D/g, "");

      let maxDigits;

      if (country.iso2 === "in") {
        maxDigits = 10;
      } else {
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

    form.addEventListener("submit", (event) => {
      if (!iti.isValidNumber()) {
        event.preventDefault();
        alert("Please enter a valid phone number");
        return;
      }

      hiddenPhone.value = iti.getNumber().replace("+", "");
    });
  </script>
</body>

</html>