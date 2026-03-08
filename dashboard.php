<?php
if (empty($_COOKIE['user_phone']) || empty($_COOKIE['session_token'])) {
    header('Location: preloader.php');
    exit;
}
header('Location: home.php');
exit;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>GovEase - Dashboard</title>
  <link rel="stylesheet" href="style.css">
  <style>
    /* Small inline styles for alignment where flex generic isn't enough */
    .dashboard-wrapper {
        padding-bottom: 80px; /* Space for bottom nav */
    }
  </style>
</head>
<body>
  
  <div class="container dashboard-wrapper has-bottom-nav">
    <div class="dashboard-scroll-area">
      <!-- Header -->
      <div class="dash-header">
      <div style="display: flex; gap: 1rem; align-items: flex-start;">
        <div class="brand-sm" style="margin-top: 4px;">
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M12 2L2 7l10 5 10-5-10-5Z"/>
              <path d="M2 17l10 5 10-5"/>
              <path d="M2 12l10 5 10-5"/>
          </svg>
          <span style="font-size: 0.9rem;">GovEase</span>
        </div>
        <div class="user-greeting">
          <h2>Hi, Manansh singh<br>shekhawat</h2>
          <p>Find your service quickly</p>
        </div>
      </div>
      <button class="btn btn-signout" onclick="window.location.href='login.php'">Sign out</button>
    </div>

    <!-- Search -->
    <div class="search-bar-container">
      <input type="text" class="search-input" placeholder="Search hospital, RTO, or location">
    </div>

    <!-- Services Header -->
    <div class="section-header">
      <h3>Services</h3>
      <a href="#" class="link-text">50 categories</a>
    </div>

    <!-- Services Scroll -->
    <div class="services-scroll">
      <div class="service-pill active">All</div>
      <div class="service-pill">OPD Consultation</div>
      <div class="service-pill">Emergency Care</div>
      <div class="service-pill">Pediatrics</div>
      <div class="service-pill">Cardiology</div>
    </div>

    <!-- Scroll Indicators -->
    <div class="scroll-indicators">
      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
      <div class="scroll-dots">
        <div class="dot active"></div>
        <div class="dot"></div>
        <div class="dot"></div>
      </div>
      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
    </div>

    <!-- Location Panel -->
    <div class="panel-card bg-light flex-between">
      <div class="location-access">
        <h4>Location access</h4>
        <p>Allow location to show Jaipur hospitals.</p>
      </div>
      <button class="btn btn-sm-outline">Allow</button>
    </div>

    <!-- Dropdowns -->
    <div class="form-group">
      <label class="form-label">City</label>
      <select class="custom-select" style="width: 100%; padding: 0.8rem 1rem; border: 1px solid var(--border-color); border-radius: var(--radius-md); outline: none;">
        <option>All</option>
        <option>Jaipur</option>
        <option>Delhi</option>
      </select>
    </div>

    <div class="form-group mb-4">
      <label class="form-label">Location</label>
      <select class="custom-select" style="width: 100%; padding: 0.8rem 1rem; border: 1px solid var(--border-color); border-radius: var(--radius-md); outline: none;">
        <option>All</option>
        <option>North District</option>
        <option>South District</option>
      </select>
    </div>

    <!-- Active Tokens -->
    <div class="active-tokens-panel">
      <span>Active tokens</span>
      <span class="token-count">7</span>
    </div>

      <!-- Action Card -->
      <div class="action-card">
        <h3 style="font-size: 1.25rem;">Scan QR to get token</h3>
        <p style="margin-top: 0.25rem;">Open your camera and scan the queue QR</p>
        <div class="flex-between" style="margin-top: 1.5rem;">
          <button class="btn btn-outline" style="background: var(--white); color: var(--primary); border: none; padding: 0.5rem 1.25rem; font-size: 0.85rem; border-radius: var(--radius-full); width: auto;">Scan Now</button>
          <div class="qrcode-box">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <rect x="3" y="3" width="7" height="7" rx="1"/>
              <rect x="14" y="3" width="7" height="7" rx="1"/>
              <rect x="14" y="14" width="7" height="7" rx="1"/>
              <rect x="3" y="14" width="7" height="7" rx="1"/>
            </svg>
          </div>
        </div>
      </div>
    </div>

    <!-- Bottom Navigation -->
    <div class="bottom-nav">
      <a href="dashboard.php" class="nav-item active-bg">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
        Home
      </a>
      <a href="appointments.php" class="nav-item">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="4" rx="2" ry="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/><path d="M8 14h.01"/><path d="M12 14h.01"/><path d="M16 14h.01"/><path d="M8 18h.01"/><path d="M12 18h.01"/><path d="M16 18h.01"/></svg>
        Appointments
      </a>
      <a href="scan.php" class="nav-item nav-primary">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7V5a2 2 0 0 1 2-2h2"/><path d="M17 3h2a2 2 0 0 1 2 2v2"/><path d="M21 17v2a2 2 0 0 1-2 2h-2"/><path d="M7 21H5a2 2 0 0 1-2-2v-2"/><rect x="7" y="7" width="10" height="10" rx="1"/></svg>
        Scan
      </a>
      <a href="tickets.php" class="nav-item">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2Z"/><path d="M13 5v2"/><path d="M13 17v2"/><path d="M13 11v2"/></svg>
        Tickets
      </a>
      <a href="profile.php" class="nav-item">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
        Profile
      </a>
    </div>

  </div>

</body>
</html>
