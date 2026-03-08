<?php
// Single-page preloader with HTML/CSS/JS in one PHP file.
?><!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Hackathon </title>
    <style>
      :root {
        --bg: #0aa3c4;
        --ink: #ffffff;
        --accent: #00c48c;
        --accent-2: #ff7a00;
        --shadow: rgba(10, 40, 60, 0.35);
      }

      * {
        box-sizing: border-box;
      }

      body {
        margin: 0;
        font-family: "Trebuchet MS", "Segoe UI", sans-serif;
        color: var(--ink);
        min-height: 100vh;
      }

      .screen {
        min-height: 100vh;
        display: grid;
        place-items: center;
        background: var(--bg);
      }

      .preloader {
        position: fixed;
        inset: 0;
        display: grid;
        place-items: center;
        padding: 32px 20px 56px;
        background:
          linear-gradient(180deg, rgba(12, 170, 196, 0.85), rgba(124, 86, 209, 0.9)),
          url("image.png") center/cover no-repeat;
        transition: opacity 0.6s ease, visibility 0.6s ease;
        z-index: 10;
      }

      .preloader.hidden {
        opacity: 0;
        visibility: hidden;
      }

      .stack {
        width: min(360px, 85vw);
        display: grid;
        justify-items: center;
        gap: 18px;
        text-align: center;
      }

      .badge {
        width: min(220px, 60vw);
        aspect-ratio: 1;
        border-radius: 50%;
        background: #ffffff;
        color: #0a2f4f;
        display: grid;
        place-items: center;
        box-shadow: 0 18px 45px var(--shadow);
        position: relative;
      }

      .badge::before {
        content: "";
        position: absolute;
        inset: 14px;
        border-radius: 50%;
        border: 6px solid #0b8db5;
      }

      .badge-content {
        display: grid;
        gap: 4px;
        place-items: center;
        padding: 24px;
      }

      .logo-img {
        width: 110px;
        height: auto;
        object-fit: contain;
        margin-top: 6px;
      }

      .lotus {
        width: 72px;
        height: 72px;
        display: grid;
        place-items: center;
        margin-bottom: 6px;
      }

      .lotus svg {
        width: 100%;
        height: 100%;
      }

      .badge-title {
        font-weight: 700;
        letter-spacing: 0.6px;
        font-size: 15px;
        text-transform: uppercase;
      }

      .badge-sub {
        font-size: 12px;
        font-weight: 600;
        color: #1d8b4c;
      }

      .badge-code {
        font-size: 14px;
        font-weight: 700;
        letter-spacing: 1px;
      }

      .authority {
        font-size: 18px;
        font-weight: 700;
        text-transform: lowercase;
        line-height: 1.05;
      }

      .authority small {
        display: block;
        font-size: 12px;
        font-weight: 500;
        opacity: 0.9;
      }

      .version {
        margin-top: 90px;
        letter-spacing: 4px;
        font-size: 15px;
        font-weight: 600;
        text-transform: uppercase;
      }

      .event {
        margin-top: 10px;
        font-size: 13px;
        letter-spacing: 1px;
        text-transform: uppercase;
        opacity: 0.9;
      }

      .progress-wrap {
        width: 120px;
        height: 6px;
        border-radius: 20px;
        background: rgba(255, 255, 255, 0.35);
        overflow: hidden;
        margin-top: 8px;
      }

      .progress {
        height: 100%;
        width: 0%;
        background: linear-gradient(90deg, var(--accent), var(--accent-2));
        border-radius: inherit;
        animation: loading 3s ease-in-out infinite;
      }

      @keyframes loading {
        0% {
          width: 0%;
        }
        45% {
          width: 70%;
        }
        70% {
          width: 85%;
        }
        100% {
          width: 100%;
        }
      }

      .hint {
        font-size: 12px;
        opacity: 0.85;
      }

      @media (max-width: 480px) {
        .badge-title {
          font-size: 13px;
        }

        .badge-code {
          font-size: 12px;
        }

        .version {
          margin-top: 70px;
          font-size: 13px;
        }
      }
    </style>
  </head>
  <body>
    <div class="preloader" id="preloader">
      <div class="stack">
        <div class="badge">
     
            <img class="logo-img" src="logo.png" alt="Logo">
          </div>
        </div>
        <div class="authority">
          TeamName : 
          <small>TheHackerTeam</small>
        </div>
        <div class="progress-wrap" aria-hidden="true">
          <div class="progress"></div>
        </div>
        <div class="hint">Loading...</div>
        <div class="version">Hackathon Name : AceHack 5.O</div>
        <div class="event">thehackerteam · AceHack</div>
      </div>
    </div>

    <script>
      const preloader = document.getElementById("preloader");
      const bgImage = new Image();
      bgImage.src = "image.png";

      function goToLogin() {
        preloader.classList.add("hidden");
        setTimeout(() => {
          window.location.href = "login.php";
        }, 500);
      }

      bgImage.addEventListener("load", () => {
        setTimeout(goToLogin, 3000);
      });

      bgImage.addEventListener("error", () => {
        setTimeout(goToLogin, 800);
      });
    </script>
  </body>
</html>
