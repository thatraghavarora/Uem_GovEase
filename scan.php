<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>GovEase - Scan QR</title>
  <link rel="stylesheet" href="style.css">
  <style>
    .page-wrapper {
        padding-top: 2rem;
    }
    
    .page-header {
      padding: 0 1.25rem 1.5rem;
    }
    
    .page-header h1 {
      font-size: 1.75rem;
      color: #0b2239;
      margin-bottom: 0.25rem;
    }
    
    .page-header p {
      color: #5c728a;
      font-size: 0.95rem;
    }

    .btn-block {
      width: 100%;
      padding: 1rem;
      font-size: 1rem;
      border-radius: var(--radius-full);
    }
  </style>
</head>
<body>
  
  <div class="container page-wrapper has-bottom-nav">
    <div class="dashboard-scroll-area">
      <!-- Header -->
      <div class="page-header">
      <h1>Scan QR</h1>
      <p>Scan center QR codes to book tokens</p>
    </div>

      <!-- Scanner Box -->
      <div class="scan-container">
        <div class="scan-placeholder">
          Allow camera to start scanning.
        </div>
        <button class="btn btn-primary btn-block">Allow Camera</button>
      </div>
    </div>

    <!-- Bottom Navigation -->
    <div class="bottom-nav">
      <a href="home.php" class="nav-item">
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
      <a href="chat.php" class="nav-item">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a4 4 0 0 1-4 4H7l-4 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4z"/></svg>
        Assistant
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
