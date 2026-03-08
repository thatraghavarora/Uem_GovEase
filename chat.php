<?php
declare(strict_types=1);

if (empty($_COOKIE['user_phone']) || empty($_COOKIE['session_token'])) {
    header('Location: preloader.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>GovEase - Assistant</title>
  <link rel="stylesheet" href="style.css">
  <style>
    .page-wrapper {
      padding-top: 2rem;
      padding-bottom: 80px;
    }

    .page-header {
      padding: 0 1.25rem 1.25rem;
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

    .chat-card {
      border: 1px solid var(--border-color);
      border-radius: var(--radius-lg);
      padding: 1.25rem;
      margin: 0 1.25rem 1rem;
      background-color: var(--white);
    }

    .chat-thread {
      display: grid;
      gap: 0.75rem;
      max-height: 52vh;
      overflow-y: auto;
      padding-right: 0.25rem;
    }

    .chat-bubble {
      padding: 0.75rem 0.9rem;
      border-radius: 0.9rem;
      font-size: 0.9rem;
      line-height: 1.4;
      max-width: 80%;
    }

    .chat-bubble.assistant {
      background: #f1f5f9;
      color: #0f172a;
      border: 1px solid var(--border-color);
    }

    .chat-bubble.user {
      background: var(--primary);
      color: var(--white);
      margin-left: auto;
    }

    .chat-input {
      display: flex;
      gap: 0.5rem;
      margin-top: 1rem;
    }

    .chat-input input {
      flex: 1;
      padding: 0.7rem 0.85rem;
      border-radius: var(--radius-md);
      border: 1px solid var(--border-color);
      background: #f8fafc;
    }

    .chat-input button {
      border: none;
      background: var(--primary);
      color: var(--white);
      padding: 0.7rem 1rem;
      border-radius: var(--radius-md);
      font-weight: 600;
    }
  </style>
</head>
<body>
  <div class="container page-wrapper has-bottom-nav">
    <div class="page-header">
      <h1>GovEase Assistant</h1>
      <p>Ask questions about your services and tokens.</p>
    </div>

    <div class="chat-card">
      <div class="chat-thread">
        <div class="chat-bubble assistant">Hi! I can help you with bookings, tokens, and center details.</div>
        <div class="chat-bubble user">Show my latest token status.</div>
        <div class="chat-bubble assistant">Here’s a summary template. Connect this UI to your backend when ready.</div>
      </div>
      <div class="chat-input">
        <input type="text" placeholder="Type your question...">
        <button type="button">Send</button>
      </div>
    </div>

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
      <a href="chat.php" class="nav-item active-bg">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a4 4 0 0 1-4 4H7l-4 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4z"/></svg>
        Assistant
      </a>
      <a href="profile.php" class="nav-item">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
        Profile
      </a>
    </div>
  </div>
</body>
</html>
