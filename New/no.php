<?php
// index.php
require_once __DIR__ . '/includes/header.php';
?>



    <!-- HERO SECTION -->
    <section class="hero">
        <div class="container hero-inner">
            <div class="hero-left">
                <div class="hero-badge">New • WhatsApp-first Authentication</div>
                <h1 class="hero-title">
                    Passwordless sign-in<br>
                    <span class="accent-gradient">powered by WhatsApp</span>
                </h1>
                <p class="hero-subtitle">
                    Reduce login drop-offs, cut OTP costs and let users log in with a single tap –
                    directly inside WhatsApp. Built for product teams, loved by developers.
                </p>

                <div class="hero-cta-row">
                    <a href="#get-started" class="btn-primary">
                        Start free &nbsp; →
                    </a>
                    <a href="#code-snippet" class="btn-ghost">
                        View integration code
                    </a>
                </div>

                <div class="hero-metrics">
                    <div class="metric-card">
                        <span class="metric-label">Login success rate</span>
                        <span class="metric-value">98%</span>
                    </div>
                    <div class="metric-card">
                        <span class="metric-label">Drop-offs reduced</span>
                        <span class="metric-value">25%</span>
                    </div>
                    <div class="metric-card">
                        <span class="metric-label">OTP cost saving</span>
                        <span class="metric-value">50%</span>
                    </div>
                </div>

                <p class="hero-footnote">
                    No OTP SMS, no passwords. Just a secure WhatsApp flow using your existing login forms.
                </p>
            </div>

            <div class="hero-right">
                <div class="phone-frame">
                    <div class="phone-notch"></div>
                    <div class="phone-inner">
                        <div class="phone-header">
                            <span class="phone-status-dot"></span>
                            <span class="phone-status-text">Webpeaker Auth</span>
                            <span class="phone-status-right">•••</span>
                        </div>
                        <div class="phone-body">
                            <div class="login-card">
                                <div class="login-logo">WA</div>
                                <h2 class="login-title">Sign in to your account</h2>
                                <p class="login-sub">
                                    Continue with your favourite apps. No OTPs required.
                                </p>

                                <div class="login-social-row">
                                    <button class="login-social-btn">
                                        <span>G</span>
                                    </button>
                                    <button class="login-social-btn">
                                        <span></span>
                                    </button>
                                    <button class="login-social-btn">
                                        <span>in</span>
                                    </button>
                                    <button class="login-social-btn">
                                        <span>MS</span>
                                    </button>
                                </div>

                                <div class="login-divider">
                                    <span>or</span>
                                </div>

                                <div class="login-whatsapp">
                                    <div class="login-whatsapp-left">
                                        <div class="whatsapp-icon"></div>
                                        <span>Continue with WhatsApp</span>
                                    </div>
                                    <div class="login-whatsapp-right">
                                        <span class="pill-success">Recommended</span>
                                    </div>
                                </div>

                                <div class="login-input">
                                    <label for="demo-phone">Phone / Email</label>
                                    <input id="demo-phone" type="text" placeholder="+91 98765 43210" disabled>
                                </div>

                                <button class="login-submit" disabled>Sign in (demo)</button>

                                <p class="login-terms">
                                    By continuing you agree to Webpeaker’s
                                    <a href="#">Terms</a> and <a href="#">Privacy Policy</a>.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="hero-floating-card">
                    <div class="floating-label">Live preview</div>
                    <p>See how your users will experience WhatsApp sign-in inside your existing flow.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- LOGO STRIP -->
    <section class="logo-strip">
        <div class="container logo-strip-inner">
            <span class="logo-strip-label">Works with</span>
            <div class="logo-strip-items">
                <span class="logo-pill">Custom PHP</span>
                <span class="logo-pill">WordPress</span>
                <span class="logo-pill">Shopify</span>
                <span class="logo-pill">Laravel</span>
                <span class="logo-pill">Node.js</span>
            </div>
        </div>
    </section>

    <!-- HOW IT WORKS -->
    <section id="how-it-works" class="section section-alt">
        <div class="container">
            <div class="section-header">
                <h2>How Webpeaker Auth works</h2>
                <p>Keep your existing forms. We only handle WhatsApp verification and callbacks.</p>
            </div>

            <div class="steps-grid">
                <div class="step-card">
                    <div class="step-number">1</div>
                    <h3>Add our script</h3>
                    <p>
                        Drop a single <code>&lt;script&gt;</code> tag on your HTML page or use our PHP helper
                        file in your root directory. No heavy SDKs.
                    </p>
                </div>
                <div class="step-card">
                    <div class="step-number">2</div>
                    <h3>Wire your form</h3>
                    <p>
                        Attach our <code>data-webpeaker-auth</code> attribute to your existing
                        phone / login form. We’ll trigger WhatsApp verification automatically.
                    </p>
                </div>
                <div class="step-card">
                    <div class="step-number">3</div>
                    <h3>Receive verified user</h3>
                    <p>
                        Once the user confirms on WhatsApp, we call your callback URL with a secure
                        token and verified number/email.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- DEVELOPER SECTION -->
    <section id="developers" class="section">
        <div class="container dev-inner">
            <div class="dev-left">
                <h2>Built for developers, not just dashboards.</h2>
                <p>
                    Integrate in minutes using our lightweight REST APIs and PHP helper. No vendor
                    lock-in – you own your session logic, we handle WhatsApp auth.
                </p>

                <ul class="dev-list">
                    <li>🔒 Signed tokens with expiry & IP protection</li>
                    <li>🧩 Works with any backend stack (PHP, Node, Python…)</li>
                    <li>📊 Webhook logs & replay from dashboard</li>
                    <li>🌐 Multi-tenant ready for agencies and SaaS platforms</li>
                </ul>

                <a href="#get-started" class="btn-primary btn-sm">
                    View full API docs
                </a>
            </div>

            <div id="code-snippet" class="dev-right">
                <div class="code-card">
                    <div class="code-card-header">
                        <span class="code-dot red"></span>
                        <span class="code-dot amber"></span>
                        <span class="code-dot green"></span>
                        <span class="code-filename">sample-form.html</span>
                    </div>
<pre class="code-block"><code>&lt;!-- 1. Include Webpeaker Auth --&gt;
&lt;script src="https://auth.webpeaker.com/sdk.min.js"&gt;&lt;/script&gt;

&lt;!-- 2. Your existing form --&gt;
&lt;form id="loginForm" data-webpeaker-auth&gt;
  &lt;input type="tel" name="phone" placeholder="+91 98765 43210" required&gt;
  &lt;button type="submit"&gt;Login with WhatsApp&lt;/button&gt;
&lt;/form&gt;</code></pre>
                    <div class="code-footer">
                        <span>Copy • Paste • Go live</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- STATS STRIP -->
    <section class="stats-strip">
        <div class="container stats-inner">
            <div class="stat-item">
                <span class="stat-number">5K+</span>
                <span class="stat-label">Sites ready to integrate</span>
            </div>
            <div class="stat-item">
                <span class="stat-number">50%</span>
                <span class="stat-label">Average OTP cost saving</span>
            </div>
            <div class="stat-item">
                <span class="stat-number">2 min</span>
                <span class="stat-label">Typical integration time</span>
            </div>
        </div>
    </section>

    <!-- USE CASES -->
    <section class="section section-alt">
        <div class="container">
            <div class="section-header">
                <h2>Perfect for high-intent funnels</h2>
                <p>Where every lead and login drop-off directly hits your revenue.</p>
            </div>

            <div class="usecase-grid">
                <article class="usecase-card">
                    <h3>Contact & lead forms</h3>
                    <p>
                        Verify phone numbers on your “Contact us”, “Book demo” and “Request call”
                        forms without sending OTPs.
                    </p>
                    <ul>
                        <li>Reduce fake leads</li>
                        <li>WhatsApp opt-in by default</li>
                        <li>Higher reply rate</li>
                    </ul>
                </article>

                <article class="usecase-card">
                    <h3>Login / signup flows</h3>
                    <p>
                        Drop WhatsApp sign-in next to your existing email & password login. No
                        migration needed.
                    </p>
                    <ul>
                        <li>Faster sign-in on mobile</li>
                        <li>No password resets</li>
                        <li>Better activation rate</li>
                    </ul>
                </article>

                <article class="usecase-card">
                    <h3>Agency & SaaS products</h3>
                    <p>
                        Offer “Login with WhatsApp” as a value-add to all your client websites with a
                        single multi-tenant integration.
                    </p>
                    <ul>
                        <li>Per-client isolation</li>
                        <li>Shared infrastructure</li>
                        <li>Custom branding</li>
                    </ul>
                </article>
            </div>
        </div>
    </section>

    <!-- TESTIMONIAL STRIP -->
    <section class="testimonial">
        <div class="container testimonial-inner">
            <div class="testimonial-content">
                <p class="testimonial-quote">
                    “We plugged Webpeaker Auth into our checkout in under 10 minutes and immediately
                    saw more users finishing login. No OTP chaos, no extra support tickets.”
                </p>
                <div class="testimonial-meta">
                    <span class="testimonial-name">Product Lead</span>
                    <span class="testimonial-role">D2C brand, India</span>
                </div>
            </div>
            <div class="testimonial-image">
                <img src="https://images.pexels.com/photos/3183197/pexels-photo-3183197.jpeg?auto=compress&cs=tinysrgb&w=800"
                     alt="Team using analytics dashboard">
            </div>
        </div>
    </section>

    <!-- PRICING -->
    <section class="section" id="get-started">
        <div class="container">
            <div class="section-header">
                <h2>Start free. Scale when you’re ready.</h2>
                <p>Simple, transparent pricing as you grow. No surprise SMS bills.</p>
            </div>

            <div class="pricing-grid">
                <div class="pricing-card">
                    <div class="pricing-badge">Starter</div>
                    <h3>Free</h3>
                    <p class="pricing-sub">For testing, MVPs and side projects.</p>
                    <ul class="pricing-list">
                        <li>Up to 1,000 verifications / month</li>
                        <li>All core WhatsApp auth features</li>
                        <li>Developer-friendly API keys</li>
                    </ul>
                    <a href="#" class="btn-outline full-width">Create free account</a>
                </div>

                <div class="pricing-card pricing-card-featured">
                    <div class="pricing-badge badge-primary">Growth</div>
                    <h3>Custom</h3>
                    <p class="pricing-sub">For brands and SaaS platforms at scale.</p>
                    <ul class="pricing-list">
                        <li>Volume-based discounts</li>
                        <li>Dedicated support & SLAs</li>
                        <li>Multi-tenant & whitelabel options</li>
                    </ul>
                    <a href="#cta" class="btn-primary full-width">Talk to us</a>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA SECTION -->
    <section id="cta" class="cta-section">
        <div class="container cta-inner">
            <div class="cta-left">
                <h2>Ready to go OTPless with WhatsApp?</h2>
                <p>Drop a single script tag or PHP helper and ship your new auth flow today.</p>
            </div>
            <div class="cta-right">
                <a href="#" class="btn-primary">Get API keys</a>
                <a href="#developers" class="btn-ghost">View integration guide</a>
            </div>
        </div>
    </section>
</main>

<?php
require_once __DIR__ . '/includes/footer.php';
?>