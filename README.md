# GovEase Appointment Platform

GovEase is a two-phase platform for secure, low-cost user authentication and real-time appointment/queue management. It replaces slow OTP flows with WhatsApp-based passwordless login, and unifies multi-organization services into a single app. The goal is to reduce waiting lines and overcrowding while giving users transparent queue visibility and organizations a centralized system for managing appointments.

## Project Phases

### Phase 1: Authentication (Passwordless via WhatsApp)
- Replaces paid OTP with WhatsApp-based verification.
- Token/session cookies are used for authenticated access across pages.
- Focused on low-cost, low-latency login and reliable session management.

### Phase 2: Appointments + Queue Management
- Digital appointment booking system with token generation.
- FIFO queue order per center with estimated wait time (5 minutes per token).
- Real-time style queue visibility through token list + status tracking.
- Admin portal for approvals and queue control.

## Problems Addressed

1. Long Waiting Lines  
2. Overcrowding in Service Locations  
3. OTP Authentication Delays and Costs  
4. Multiple Apps for Different Services  
5. Lack of Real-Time Queue Visibility  
6. Inefficient Appointment Management  

## Key Capabilities

- **Unified city-wide access**: multiple organizations, one platform.
- **Passwordless login**: WhatsApp verification (no paid OTP).
- **Token-based booking**: per-center token generation and tracking.
- **FIFO queue**: order by creation time with estimated wait time.
- **User visibility**: users see their bookings and queue status.
- **Admin control**: approve/decline tokens and print lists.

## Project Structure

```
/
├─ home.php               # User home, center list, booking entry point
├─ appointment.php        # Book a token for a selected center
├─ appointments.php       # Centers + user's bookings list
├─ tickets.php            # User tickets (tokens)
├─ profile.php            # User profile from cookies + KYC
├─ scan.php               # QR scan placeholder UI
├─ chat.php               # Assistant UI (layout only)
├─ admin/
│  ├─ admin_login.php     # Admin login
│  ├─ admin_portal.php    # Token approvals + FIFO queue
│  ├─ register.php        # Admin registration
│  ├─ view_admin.php      # Demo admin list (shows credentials)
│  ├─ logout.php          # Admin logout
│  └─ index.php           # Admin entry
├─ api/                   # WhatsApp auth APIs
├─ db/                    # DB config (for auth system)
├─ includes/              # Shared includes (if any)
├─ style.css              # Global UI styles
└─ govease-99021-firebase-adminsdk-*.json  # Firestore service account
```

## How Authentication Works

- Users log in via WhatsApp verification (API endpoints in `api/`).
- On success, `user_phone`, `session_token`, and profile cookies are set.
- Protected pages check cookies and redirect to `preloader.php` if missing.

## Appointment + Queue Flow

1. User selects a center from `home.php` or `appointments.php`.
2. `appointment.php` generates a token with a sequential number.
3. Token document is stored in Firestore under `token` collection.
4. Estimated wait time is shown as `tokenNumber * 5 minutes`.
5. User tickets stored under `kyc_submissions` in `tickets` array.

## Admin Portal Flow

- Admin logs in from `admin/admin_login.php` using Firestore `admins` collection.
- `admin/admin_portal.php` lists active tokens for that admin’s center.
- Admin can approve or decline tokens (status updated in Firestore).
- Print button outputs the FIFO list in a print-friendly layout.

## Firestore Collections Used

### `centers`
- Stores center metadata (name, city, address, type, etc.).

### `token`
- Stores appointment tokens for each center.
- Fields: `centerId`, `userPhone`, `userName`, `tokenNumber`, `status`, `createdAt`, `appointmentTime`.

### `kyc_submissions`
- Stores KYC data and user ticket history.
- Tickets are stored in an array to show booking history.

### `admins`
- Stores admin credentials and center assignment.
- Fields: `username`, `password`, `centerId`, `centerCode`, `centerName`, `centerType`.

## Challenges Encountered (and Solutions)

### 1) Real-time queue consistency
Initially, queue position updates were inconsistent across sessions.  
**Fix:** a centralized queue counter and FIFO ordering based on creation time.

### 2) WhatsApp-based passwordless login
Replacing OTP required secure session and verification handling.  
**Fix:** token-based session validation + WhatsApp-based verification.

### 3) Multi-organization system
Managing different orgs within one app required flexible storage.  
**Fix:** modular schema with center-specific data and shared booking logic.

### 4) UI complexity
Early flows were confusing to users.  
**Fix:** simplified booking UI + clear status and queue feedback.

## How to Run (Local)

- Place the Firestore service account JSON in the project root.
- Use a PHP server (local Apache/Nginx or PHP built-in server).
- Ensure cookies are set after authentication via WhatsApp flow.

## Notes

- Admin credential view (`admin/view_admin.php`) is open for demo use.
- Chat assistant (`chat.php`) is UI-only, no backend integration yet.

## Future Improvements

- Live updates using WebSockets for queue changes.
- Role-based admin control and audit logs.
- Real-time notifications for token status updates.
