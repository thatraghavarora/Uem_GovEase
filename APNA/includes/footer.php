<?php
// includes/footer.php
?>
</main>

<style>
    .site-footer {
        padding-top: 2.4rem;
        border-top: 1px solid rgba(30, 64, 175, 0.6);
        background: radial-gradient(circle at top, rgba(15, 23, 42, 0.98), #020617 60%);
        margin-top: 2rem;
        color: #e5e7eb;
        font-family: system-ui, -apple-system, BlinkMacSystemFont, "SF Pro Text",
            "Segoe UI", sans-serif;
    }

    .footer-inner {
        max-width: 1120px;
        margin: 0 auto;
        padding: 0 1.2rem 1.8rem;
        display: grid;
        grid-template-columns: minmax(0, 1.2fr) repeat(3, minmax(0, 0.7fr));
        gap: 1.8rem;
    }

    .footer-col {
        font-size: 0.9rem;
    }

    .footer-brand {
        max-width: 280px;
    }

    .footer-logo {
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
        margin-bottom: 0.5rem;
    }

    .footer-tagline {
        margin: 0;
        color: #9ca3af;
        font-size: 0.85rem;
    }

    .footer-heading {
        margin: 0 0 0.5rem;
        font-size: 0.9rem;
        font-weight: 600;
    }

    .footer-link {
        display: block;
        margin-bottom: 0.25rem;
        font-size: 0.85rem;
        color: #9ca3af;
        text-decoration: none;
        transition: color 0.15s ease;
    }

    .footer-link:hover {
        color: #e5e7eb;
    }

    .footer-bottom {
        border-top: 1px solid rgba(15, 23, 42, 0.9);
        padding: 0.8rem 0 1.1rem;
        font-size: 0.8rem;
        color: #6b7280;
        background: rgba(2, 6, 23, 0.98);
    }

    .footer-bottom-inner {
        max-width: 1120px;
        margin: 0 auto;
        padding: 0 1.2rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.8rem;
    }

    .footer-bottom-right {
        color: #9ca3af;
    }

    @media (max-width: 900px) {
        .footer-inner {
            grid-template-columns: minmax(0, 1.1fr) repeat(2, minmax(0, 0.8fr));
        }
    }

    @media (max-width: 768px) {
        .footer-inner {
            grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
            gap: 1.4rem;
        }

        .footer-brand {
            grid-column: 1 / -1;
        }

        .footer-bottom-inner {
            flex-direction: column;
            align-items: flex-start;
        }
    }

    @media (max-width: 540px) {
        .footer-inner {
            grid-template-columns: minmax(0, 1fr);
        }

        .footer-bottom-inner {
            align-items: flex-start;
        }
    }
</style>

<footer class="site-footer">
    <div class="footer-inner">
        <div class="footer-col footer-brand">
            <div class="footer-logo">WA</div>
            <p class="footer-tagline">
                OTPless-style WhatsApp authentication for modern websites and products.
            </p>
        </div>

        <div class="footer-col">
            <h4 class="footer-heading">Product</h4>
            <a href="#how-it-works" class="footer-link">How it works</a>
            <a href="#get-started" class="footer-link">Pricing</a>
            <a href="#cta" class="footer-link">Book a demo</a>
        </div>

        <div class="footer-col">
            <h4 class="footer-heading">Developers</h4>
            <a href="#developers" class="footer-link">Integration guide</a>
            <a href="#code-snippet" class="footer-link">Code samples</a>
            <a href="#" class="footer-link">API reference</a>
        </div>

        <div class="footer-col">
            <h4 class="footer-heading">Company</h4>
            <a href="#cta" class="footer-link">Contact</a>
            <a href="#" class="footer-link">Terms</a>
            <a href="#" class="footer-link">Privacy</a>
        </div>
    </div>

    <div class="footer-bottom">
        <div class="footer-bottom-inner">
            <span>© <?php echo date('Y'); ?> Webpeaker Auth. All rights reserved.</span>
            <span class="footer-bottom-right">
                Made for high-intent, WhatsApp-first experiences.
            </span>
        </div>
    </div>
</footer>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const toggle = document.querySelector('.nav-toggle');
    const nav = document.getElementById('primaryNav');

    if (!toggle || !nav) return;

    toggle.addEventListener('click', function () {
        const isOpen = nav.classList.toggle('nav-open');
        toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    });

    nav.querySelectorAll('a').forEach(function (link) {
        link.addEventListener('click', function () {
            if (nav.classList.contains('nav-open')) {
                nav.classList.remove('nav-open');
                toggle.setAttribute('aria-expanded', 'false');
            }
        });
    });
});
</script>

</body>
</html>