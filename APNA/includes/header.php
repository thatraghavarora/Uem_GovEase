<?php
// includes/header.php
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Webpeaker Auth – WhatsApp Authentication as a Service</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="OTPless-style WhatsApp authentication as a service. Integrate passwordless WhatsApp login into any website.">
    <link rel="stylesheet" href="assets/css/style.css">

    <style>
        :root {
            --nav-height: 72px;
            --accent-gradient: linear-gradient(135deg, #22c55e, #4ade80);
            --text-main: #e5e7eb;
            --text-soft: #9ca3af;
            --text-muted: #6b7280;
        }

        body {
            margin: 0;
        }

        .container {
            max-width: 1120px;
            margin: 0 auto;
            padding: 0 1.2rem;
        }

        /* HEADER BASE */

        .site-header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 50;
            backdrop-filter: blur(14px);
            background: linear-gradient(to bottom, rgba(2, 6, 23, 0.96), rgba(2, 6, 23, 0.86));
            border-bottom: 1px solid rgba(30, 64, 175, 0.6);
        }

        .header-inner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: var(--nav-height);
        }

        .brand {
            display: inline-flex;
            align-items: center;
            gap: 0.55rem;
            text-decoration: none;
            color: var(--text-main);
        }

        .brand-icon {
            width: 32px;
            height: 32px;
            border-radius: 0.9rem;
            background: radial-gradient(circle at top left, #22c55e, #16a34a);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
            font-weight: 700;
            color: #020617;
        }

        .brand-text {
            display: flex;
            flex-direction: column;
            gap: 0.05rem;
        }

        .brand-title {
            font-size: 0.95rem;
            font-weight: 600;
        }

        .brand-subtitle {
            font-size: 0.75rem;
            color: var(--text-muted);
        }

        .site-nav {
            display: flex;
            align-items: center;
            gap: 1.1rem;
            font-size: 0.9rem;
        }

        .nav-link {
            text-decoration: none;
            color: var(--text-soft);
            padding: 0.35rem 0;
            transition: color 0.15s ease, opacity 0.15s ease;
        }

        .nav-link:hover {
            color: var(--text-main);
        }

        .nav-link-primary {
            padding: 0.45rem 1rem;
            border-radius: 999px;
            background: var(--accent-gradient);
            color: #020617;
            box-shadow: 0 10px 25px rgba(34, 197, 94, 0.4);
        }

        .nav-link-ghost {
            padding: 0.4rem 0.9rem;
            border-radius: 999px;
            border: 1px solid rgba(148, 163, 184, 0.45);
        }

        .nav-toggle {
            display: none;
            border: 1px solid rgba(148, 163, 184, 0.6);
            background: rgba(15, 23, 42, 0.95);
            color: var(--text-main);
            border-radius: 999px;
            padding: 0.3rem 0.55rem;
            cursor: pointer;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            gap: 0.18rem;
        }

        .nav-toggle-bar {
            width: 16px;
            height: 2px;
            border-radius: 999px;
            background: var(--text-main);
        }

        .page {
            padding-top: calc(var(--nav-height) + 32px);
        }

        @media (max-width: 768px) {
            .site-nav {
                position: absolute;
                top: var(--nav-height);
                right: 1.2rem;
                left: 1.2rem;
                background: rgba(15, 23, 42, 0.98);
                border-radius: 1rem;
                border: 1px solid rgba(30, 64, 175, 0.7);
                padding: 0.7rem 0.9rem;
                flex-direction: column;
                align-items: flex-start;
                gap: 0.4rem;
                display: none;
            }

            .site-nav.nav-open {
                display: flex;
            }

            .nav-link-primary,
            .nav-link-ghost {
                width: 100%;
                text-align: center;
                justify-content: center;
                padding: 0.5rem 0.9rem;
            }

            .nav-toggle {
                display: inline-flex;
            }

            .page {
                padding-top: calc(var(--nav-height) + 20px);
            }
        }
    </style>
</head>
<body>
<header class="site-header">
    <div class="container header-inner">
        <a href="/" class="brand">
            <div class="brand-icon">WA</div>
            <div class="brand-text">
                <span class="brand-title">Webpeaker Auth</span>
                <span class="brand-subtitle">WhatsApp Login API</span>
            </div>
        </a>

        <button class="nav-toggle" aria-label="Toggle navigation" aria-expanded="false">
            <span class="nav-toggle-bar"></span>
            <span class="nav-toggle-bar"></span>
        </button>

        <nav class="site-nav" id="primaryNav">
            <a href="#how-it-works" class="nav-link">How it works</a>
            <a href="#developers" class="nav-link">Developers</a>
            <a href="#get-started" class="nav-link">Pricing</a>
            <a href="#cta" class="nav-link nav-link-ghost">Contact</a>
            <a href="#get-started" class="nav-link nav-link-primary">Get API keys</a>
        </nav>
    </div>
</header>

<main class="page">