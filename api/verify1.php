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
    <title>Verification Success</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        :root {
            --brand-blue: #0b3ac9;
            --brand-blue-dark: #082f9c;
            --brand-gray: #f4f6fb;
            --brand-text: #0f172a;
            --brand-muted: #64748b;
            --success: #16a34a;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            background: var(--brand-gray);
            color: var(--brand-text);
            overflow: hidden;
        }

        .preloader {
            position: fixed;
            inset: 0;
            background: #ffffff;
            display: grid;
            place-items: center;
            z-index: 1000;
        }

        .preloader-card {
            text-align: center;
            padding: 2rem 2.5rem;
            border-radius: 16px;
            box-shadow: 0 20px 40px rgba(15, 23, 42, 0.1);
            background: #fff;
        }

        .logo {
            width: 72px;
            height: 72px;
            border-radius: 999px;
            display: grid;
            place-items: center;
            font-weight: 700;
            letter-spacing: 1px;
            color: #9a7500;
            background: #fff6d6;
            border: 2px solid #f1c40f;
            margin: 0 auto 1rem;
        }

        .loader {
            width: 44px;
            height: 44px;
            border-radius: 999px;
            border: 3px solid #e2e8f0;
            border-top-color: var(--brand-blue);
            margin: 0.7rem auto 0.6rem;
            animation: spin 1s linear infinite;
        }

        .preloader-text {
            font-size: 0.95rem;
            color: var(--brand-muted);
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        .hidden {
            display: none;
        }

        .dashboard {
            height: 100vh;
            display: flex;
        }

        .sidebar {
            width: 240px;
            background: #a7a7a7;
            color: #1f2937;
            padding: 1.2rem 1rem;
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
            overflow: hidden;
        }

        .sidebar-logo {
            text-align: center;
            font-weight: 700;
            font-size: 0.95rem;
            line-height: 1.2;
        }

        .sidebar-logo span {
            display: block;
            font-size: 0.7rem;
            letter-spacing: 0.1rem;
            color: #4b5563;
            margin-bottom: 0.25rem;
        }

        .nav {
            display: grid;
            gap: 0.65rem;
            font-size: 0.88rem;
        }

        .nav a {
            color: #1f2937;
            text-decoration: none;
            padding: 0.35rem 0.5rem;
            border-radius: 6px;
        }

        .nav a.active {
            color: #0b3ac9;
            font-weight: 600;
            background: rgba(255, 255, 255, 0.45);
        }

        .sidebar-footer {
            margin-top: auto;
            font-weight: 700;
            color: #1f2937;
        }

        .content {
            flex: 1;
            background: #f4f4f4;
            padding: 1.5rem 1.8rem 2.5rem;
            overflow-y: auto;
        }

        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
        }

        .title-block h1 {
            margin: 0;
            font-size: 1.4rem;
        }

        .title-block p {
            margin: 0.2rem 0 0;
            color: var(--brand-muted);
            font-size: 0.85rem;
        }

        .top-actions {
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }

        .icon-btn,
        .avatar {
            width: 34px;
            height: 34px;
            border-radius: 8px;
            border: 1px solid #cbd5f5;
            display: grid;
            place-items: center;
            font-weight: 700;
            background: #fff;
            color: #1e3a8a;
        }

        .alert {
            background: #fceaea;
            color: #b91c1c;
            padding: 0.6rem 0.9rem;
            border-radius: 8px;
            font-size: 0.82rem;
            border: 1px solid #f3caca;
        }

        .verified-banner {
            margin-top: 1rem;
            background: #ecfdf3;
            color: #15803d;
            border: 1px solid #bbf7d0;
            padding: 0.7rem 0.9rem;
            border-radius: 8px;
            font-size: 0.85rem;
        }

        .progress-wrap {
            margin-top: 1rem;
            background: #fff;
            border-radius: 12px;
            padding: 0.9rem 1.1rem;
            box-shadow: 0 8px 18px rgba(15, 23, 42, 0.06);
            display: none;
        }

        .progress-steps {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 0.6rem;
            font-size: 0.8rem;
            color: var(--brand-muted);
        }

        .progress-step {
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }

        .progress-dot {
            width: 14px;
            height: 14px;
            border-radius: 999px;
            border: 2px solid #cbd5f5;
            background: #fff;
        }

        .progress-step.active .progress-dot {
            border-color: var(--brand-blue);
            background: var(--brand-blue);
        }

        .content-card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 8px 20px rgba(15, 23, 42, 0.08);
            margin-top: 1.4rem;
            padding: 1.2rem 1.4rem;
            display: grid;
            grid-template-columns: 2.2fr 1fr;
            gap: 1.5rem;
        }

        .content-card h2 {
            margin: 0 0 1rem;
            font-size: 1.1rem;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1rem;
            font-size: 0.85rem;
            color: var(--brand-muted);
        }

        .info-grid strong {
            color: var(--brand-text);
            font-size: 0.9rem;
        }

        .action-btn {
            margin-top: 1rem;
            background: #fff;
            color: #0b3ac9;
            border: 1px solid #cbd5f5;
            padding: 0.4rem 0.8rem;
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.85rem;
            cursor: pointer;
        }

        .journey {
            border-left: 1px solid #e5e7eb;
            padding-left: 1rem;
        }

        .journey h3 {
            font-size: 0.95rem;
            margin: 0 0 0.6rem;
        }

        .journey-step {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            margin-bottom: 0.7rem;
            font-size: 0.82rem;
            color: var(--brand-muted);
        }

        .journey-dot {
            width: 12px;
            height: 12px;
            border-radius: 999px;
            border: 1px solid #cbd5f5;
            background: #fff;
        }

        .journey-pill {
            background: #f8fafc;
            border: 1px solid #e5e7eb;
            padding: 0.3rem 0.6rem;
            border-radius: 999px;
        }

        .form-card {
            margin-top: 1.5rem;
            background: #fff;
            border-radius: 12px;
            padding: 1.4rem;
            box-shadow: 0 8px 20px rgba(15, 23, 42, 0.08);
            display: none;
        }

        .form-card h3 {
            margin-top: 0;
            margin-bottom: 0.6rem;
        }

        form {
            display: grid;
            gap: 1rem;
        }

        .form-grid {
            display: grid;
            gap: 1rem;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        }

        label {
            display: block;
            font-size: 0.82rem;
            font-weight: 600;
            color: var(--brand-text);
            margin-bottom: 0.35rem;
        }

        input,
        select,
        textarea {
            width: 100%;
            padding: 0.7rem 0.85rem;
            border-radius: 8px;
            border: 1px solid #d7dce5;
            background: #f8fafc;
            font-size: 0.92rem;
            color: var(--brand-text);
        }

        textarea {
            min-height: 90px;
            resize: vertical;
        }

        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.8rem;
        }

        .btn-primary {
            background: var(--brand-blue);
            color: #fff;
            border: none;
            padding: 0.7rem 1.4rem;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
        }

        .btn-secondary {
            background: #fff;
            color: var(--brand-blue);
            border: 1px solid var(--brand-blue);
            padding: 0.65rem 1.2rem;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
        }

        @media (max-width: 960px) {
            body {
                overflow: auto;
            }

            .dashboard {
                height: auto;
                flex-direction: column;
            }

            .sidebar {
                width: 100%;
                flex-direction: row;
                flex-wrap: wrap;
                gap: 1rem;
                justify-content: space-between;
                overflow: visible;
            }

            .content {
                overflow: visible;
            }

            .content-card {
                grid-template-columns: 1fr;
            }

            .journey {
                border-left: none;
                padding-left: 0;
            }
        }
    </style>
</head>
<body>
    <div id="preloader" class="preloader">
        <div class="preloader-card">
            <div class="logo">SGVU</div>
            <div class="loader"></div>
            <div class="preloader-text">Verifying your number...</div>
        </div>
    </div>

    <main id="dashboard" class="dashboard hidden">
        <aside class="sidebar">
            <div class="sidebar-logo">
                <span>OFFICIAL UNIVERSITY PARTNER</span>
                SGVU University
            </div>
            <nav class="nav">
                <a href="#">Dashboard</a>
                <a class="active" href="#">All Application Form(s)</a>
                <a href="#">My Payments</a>
                <a href="#">My Queries</a>
                <a href="#">Fees for Session 2026-27</a>
                <a href="#">Courses & Eligibility 2025-26</a>
                <a href="#">Scholarship for 2025-26</a>
                <a href="#">Admission Brochure 2025-26</a>
                <a href="#">Chat With Us Now</a>
                <a href="#">My Communication</a>
            </nav>
            <div class="sidebar-footer">HackerX</div>
        </aside>

        <section class="content">
            <div class="topbar">
                <div class="title-block">
                    <h1>All Application Form(s)</h1>
                    <p>Welcome <?php echo htmlspecialchars($userPhone); ?></p>
                </div>
                <div class="top-actions">
                    <a class="btn-secondary" href="https://authenticator.webpeaker.com/sgvu.php">Logout</a>
                    <div class="icon-btn">?</div>
                    <div class="avatar">CM</div>
                </div>
            </div>

            <div class="alert">
                It looks like you haven't verified your email. <a href="#">Click here</a> to receive the Verification Email.
            </div>
            <div class="verified-banner">
                Number verified successfully for <strong><?php echo htmlspecialchars($tenantName); ?></strong>.
            </div>

            <div id="progress" class="progress-wrap">
                <div class="progress-steps">
                    <div class="progress-step active">
                        <span class="progress-dot"></span>
                        <span>Step 1: Basic Detail</span>
                    </div>
                    <div class="progress-step">
                        <span class="progress-dot"></span>
                        <span>Step 2: Family Detail</span>
                    </div>
                    <div class="progress-step">
                        <span class="progress-dot"></span>
                        <span>Step 3: Document</span>
                    </div>
                    <div class="progress-step">
                        <span class="progress-dot"></span>
                        <span>Step 4: Payment</span>
                    </div>
                    <div class="progress-step">
                        <span class="progress-dot"></span>
                        <span>Step 5: Done</span>
                    </div>
                </div>
            </div>

            <div class="content-card">
                <div>
                    <h2>SGVU University 2026</h2>
                    <div class="info-grid">
                        <div>
                            <div>Application No.</div>
                            <strong>-</strong>
                        </div>
                        <div>
                            <div>Application Submitted On</div>
                            <strong>-</strong>
                        </div>
                        <div>
                            <div>Application Fees</div>
                            <strong>₹1500.00</strong>
                        </div>
                    </div>
                    <button id="applyNow" class="action-btn" type="button">Apply Now</button>
                </div>
                <div class="journey">
                    <h3>Enrolment Journey</h3>
                    <div class="journey-step">
                        <span class="journey-dot"></span>
                        <span class="journey-pill">Application Initiated</span>
                    </div>
                    <div class="journey-step">
                        <span class="journey-dot"></span>
                        <span class="journey-pill">Payment Completed</span>
                    </div>
                    <div class="journey-step">
                        <span class="journey-dot"></span>
                        <span class="journey-pill">Application Completed</span>
                    </div>
                </div>
            </div>

            <section class="form-card">
                <h3>Basic Details</h3>
                <p>Fill out your profile and admission details to complete your application.</p>
                <form>
                    <div class="form-grid">
                        <div>
                            <label for="full_name">Full name</label>
                            <input id="full_name" type="text" placeholder="Enter full name">
                        </div>
                        <div>
                            <label for="email">Email address</label>
                            <input id="email" type="email" placeholder="Enter email address">
                        </div>
                        <div>
                            <label for="dob">Date of birth</label>
                            <input id="dob" type="date">
                        </div>
                        <div>
                            <label for="gender">Gender</label>
                            <select id="gender">
                                <option value="">Select</option>
                                <option>Male</option>
                                <option>Female</option>
                                <option>Other</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-grid">
                        <div>
                            <label for="program">Program name</label>
                            <input id="program" type="text" placeholder="B.Tech / MBA / MCA">
                        </div>
                        <div>
                            <label for="department">Department</label>
                            <input id="department" type="text" placeholder="Engineering / Management">
                        </div>
                        <div>
                            <label for="session">Admission session</label>
                            <input id="session" type="text" placeholder="2026-27">
                        </div>
                        <div>
                            <label for="mode">Mode of study</label>
                            <select id="mode">
                                <option value="">Select</option>
                                <option>Online</option>
                                <option>On-Campus</option>
                                <option>Hybrid</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-grid">
                        <div>
                            <label for="city">City</label>
                            <input id="city" type="text" placeholder="City">
                        </div>
                        <div>
                            <label for="state">State</label>
                            <input id="state" type="text" placeholder="State">
                        </div>
                        <div>
                            <label for="address">Address</label>
                            <textarea id="address" placeholder="Full address"></textarea>
                        </div>
                    </div>

                    <div class="actions">
                        <button class="btn-primary" type="submit">Save Details</button>
                        <button class="btn-secondary" type="button">Edit Later</button>
                    </div>
                </form>
            </section>
        </section>
    </main>

    <script>
        const preloader = document.getElementById("preloader");
        const dashboard = document.getElementById("dashboard");

        window.addEventListener("load", () => {
            setTimeout(() => {
                preloader.classList.add("hidden");
                dashboard.classList.remove("hidden");
            }, 1200);
        });

        const applyNow = document.getElementById("applyNow");
        const formCard = document.querySelector(".form-card");
        const progress = document.getElementById("progress");

        applyNow.addEventListener("click", () => {
            progress.style.display = "block";
            formCard.style.display = "block";
            formCard.scrollIntoView({ behavior: "smooth", block: "start" });
        });
    </script>
</body>
</html>
