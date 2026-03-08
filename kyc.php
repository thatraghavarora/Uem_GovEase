<?php
declare(strict_types=1);

$userPhone = trim((string) ($_COOKIE['user_phone'] ?? ''));
$userName = trim((string) ($_COOKIE['user_name'] ?? ''));
$userEmail = trim((string) ($_COOKIE['user_email'] ?? ''));

$hasPhone = $userPhone !== '';
$hasName = $userName !== '';
$hasEmail = $userEmail !== '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>PM-JAY KYC</title>
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

        .input[disabled] {
            color: #6b7280;
            cursor: not-allowed;
        }

        .input-locked {
            pointer-events: none;
            user-select: none;
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
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
        }

        .button.primary {
            background: linear-gradient(90deg, rgba(10, 165, 195, 0.75), rgba(138, 106, 216, 0.7));
            color: #ffffff;
        }

        .status {
            margin-top: 16px;
            border-radius: 14px;
            padding: 12px 14px;
            font-size: 13px;
            display: none;
        }

        .status.error {
            border: 1px solid #fca5a5;
            background: #fef2f2;
            color: #b91c1c;
        }

        .status.success {
            border: 1px solid #86efac;
            background: #f0fdf4;
            color: #166534;
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
                national health authority
                <small>government of india</small>
            </div>
            <div class="logo-wrap">
                <img class="logo-img" src="logo.png" alt="PM-JAY logo">
            </div>
        </header>

        <section class="form-card">
            <h1 class="form-title">KYC Details</h1>

            <form id="kycForm" method="post" action="kyc_submit.php" autocomplete="off">
                <div class="field">
                    <label class="label" for="fullName">Full Name<span>*</span></label>
                    <div class="input-wrapper">
                        <input class="input" id="fullName" name="fullName" placeholder="Enter your full name"
                            value="<?php echo htmlspecialchars($userName); ?>"
                            <?php echo $hasName ? 'readonly' : 'required'; ?>>
                    </div>
                </div>

                <div class="field">
                    <label class="label" for="phone">Mobile Number<span>*</span></label>
                    <div class="input-wrapper">
                        <input class="input input-locked" id="phone" name="phone_display" placeholder="Enter your mobile number"
                            value="<?php echo htmlspecialchars($userPhone); ?>" readonly>
                        <input type="hidden" name="phone" id="phoneHidden" value="<?php echo htmlspecialchars($userPhone); ?>">
                    </div>
                    <div class="help">Use the same number as your login.</div>
                </div>

                <div class="field">
                    <label class="label" for="email">Email Address<span>*</span></label>
                    <div class="input-wrapper">
                        <input class="input" id="email" name="email" type="email" placeholder="Enter your email"
                            value="<?php echo htmlspecialchars($userEmail); ?>"
                            <?php echo $hasEmail ? 'readonly' : 'required'; ?>>
                    </div>
                </div>

                <div class="actions">
                    <button type="submit" class="button primary">Submit KYC</button>
                </div>
                <?php if (!empty($_GET['status'])): ?>
                    <div id="status" class="status <?php echo $_GET['status'] === 'success' ? 'success' : 'error'; ?>" style="display: block;">
                        <?php echo $_GET['status'] === 'success' ? 'KYC submitted successfully.' : 'Submission failed. Please try again.'; ?>
                    </div>
                <?php else: ?>
                    <div id="status" class="status"></div>
                <?php endif; ?>
            </form>
        </section>
    </div>

    <script>
        (function () {
            const phoneInput = document.getElementById("phone");
            const phoneHidden = document.getElementById("phoneHidden");
            if (!phoneInput || !phoneHidden) {
                return;
            }

            const cookieValue = document.cookie
                .split("; ")
                .find((row) => row.startsWith("user_phone="));
            const phone = cookieValue ? decodeURIComponent(cookieValue.split("=")[1] || "") : "";

            if (phone) {
                phoneInput.value = phone;
                phoneHidden.value = phone;
            }

            phoneInput.setAttribute("readonly", "readonly");
            phoneInput.classList.add("input-locked");
        })();
    </script>
</body>
</html>
