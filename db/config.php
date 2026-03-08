<?php
/**
 * db/config.php
 *
 * Central configuration & database bootstrap for Webpeaker Auth.
 *
 * - Handles MySQL connection (Hostinger compatible)
 * - Provides secure random token helpers
 * - Provides hashing helpers for API keys & secrets
 * - Defines table names for advanced multi-tenant setup
 *
 * IMPORTANT:
 *   1. Update the $DB_* credentials below as per your Hostinger panel.
 *   2. Never expose this file publicly (don’t echo errors, don’t commit to
 *      public GitHub with real credentials).
 */

declare(strict_types=1);

/**
 * Application root directory (one level above /db).
 * This lets us include other project files from a known base path.
 */
if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

/* ---------------------------------------------------------------------------
 * 1. DATABASE CREDENTIALS (EDIT THESE)
 * ------------------------------------------------------------------------ */

$DB_HOST = 'localhost';        // usually 'localhost' on Hostinger
$DB_NAME = 'u306475203_Authencator';    // TODO: change this to your DB name
$DB_USER = 'u306475203_Otpless1';     // TODO: change this to your DB user
$DB_PASS = 'Otpless@14777'; // TODO: change this to your DB password
$DB_CHARSET = 'utf8mb4';

/**
 * If you want to quickly disable all DB-dependent features for debugging,
 * set this to false.
 */
$DB_ENABLED = true;

/* ---------------------------------------------------------------------------
 * 2. GLOBAL CONFIG (APP + SECURITY)
 * ------------------------------------------------------------------------ */

$appConfig = [
    'app_name'  => 'Webpeaker Auth',
    'app_url'   => 'https://authenticator.webpeaker.com', // change for your domain
    'env'       => 'production',                 // 'local' | 'staging' | 'production'

    // Security / crypto
    'security'  => [
        'token_bytes'          => 32, // random_bytes length => 64 char hex token
        'session_expiry_secs'  => 15 * 60, // 15 min for OTP/verification sessions
        'password_algo'        => PASSWORD_DEFAULT,
        'password_options'     => [
            'cost' => 12, // increase for more security (slower hash)
        ],
    ],

    // Table names for advanced SaaS structure
    'tables'    => [
        'tenants'               => 'wa_tenants',
        'api_keys'              => 'wa_api_keys',
        'verification_sessions' => 'wa_verification_sessions',
        'webhook_events'        => 'wa_webhook_events',
        'auth_logs'             => 'wa_auth_logs',
    ],
];

/* ---------------------------------------------------------------------------
 * 3. DB CONNECTION HELPER (PDO)
 * ------------------------------------------------------------------------ */

/**
 * Get shared PDO instance (singleton style).
 *
 * Usage:
 *   $pdo = db();
 *   $stmt = $pdo->prepare('SELECT ...');
 *
 * @return PDO
 * @throws RuntimeException on connection failure (in production we log only)
 */
function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    global $DB_ENABLED, $DB_HOST, $DB_NAME, $DB_USER, $DB_PASS, $DB_CHARSET, $appConfig;

    if (!$DB_ENABLED) {
        throw new RuntimeException('Database is disabled in config.');
    }

    $dsn = "mysql:host={$DB_HOST};dbname={$DB_NAME};charset={$DB_CHARSET}";

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    try {
        $pdo = new PDO($dsn, $DB_USER, $DB_PASS, $options);
    } catch (Throwable $e) {
        // In production, NEVER echo database errors. Just log.
        error_log('DB connection failed: ' . $e->getMessage());

        if (($appConfig['env'] ?? 'production') !== 'production') {
            // In non-production, we can be noisy for debugging:
            throw new RuntimeException('DB connection failed: ' . $e->getMessage(), 0, $e);
        }

        throw new RuntimeException('Database connection failed.');
    }

    return $pdo;
}

/* ---------------------------------------------------------------------------
 * 4. RANDOM TOKEN HELPERS
 * ------------------------------------------------------------------------ */

/**
 * Generate a cryptographically secure random token (hex string).
 *
 * Example usage (verification token, API key, etc.):
 *   $token = generateToken();       // 64-char hex
 *   $short = generateToken(16);     // 32-char hex
 *
 * @param int $bytes Number of random bytes
 * @return string 2 * $bytes length hex string
 */
function generateToken(int $bytes = null): string
{
    global $appConfig;

    if ($bytes === null) {
        $bytes = (int) ($appConfig['security']['token_bytes'] ?? 32);
    }

    return bin2hex(random_bytes($bytes));
}

/**
 * Generate a human-friendly 6-digit code (for OTP-like UX).
 * Example: 483219
 *
 * @return string
 */
function generateNumericCode(int $length = 6): string
{
    $length = max(4, min($length, 10));

    $min = (int) pow(10, $length - 1);
    $max = (int) pow(10, $length) - 1;

    return (string) random_int($min, $max);
}

/* ---------------------------------------------------------------------------
 * 5. SECRET / API KEY HASHING HELPERS
 * ------------------------------------------------------------------------ */

/**
 * Hash an API key / client secret for storage in DB.
 *
 * We NEVER store raw API keys or secrets — only hashes.
 *
 * @param string $value
 * @return string
 */
function hashSecret(string $value): string
{
    global $appConfig;

    $algo     = $appConfig['security']['password_algo'] ?? PASSWORD_DEFAULT;
    $options  = $appConfig['security']['password_options'] ?? [];

    $hash = password_hash($value, $algo, $options);

    if ($hash === false) {
        throw new RuntimeException('Unable to hash secret.');
    }

    return $hash;
}

/**
 * Verify a provided secret (API key / client secret) against stored hash.
 *
 * @param string $value
 * @param string $hash
 * @return bool
 */
function verifySecret(string $value, string $hash): bool
{
    return password_verify($value, $hash);
}

/* ---------------------------------------------------------------------------
 * 6. TENANT / API KEY BASIC HELPERS (used by future API endpoints)
 * ------------------------------------------------------------------------ */

/**
 * Load tenant by public API key (we will pass api_key from clients).
 *
 * Tables expected:
 *   wa_tenants (id, name, is_active, created_at, ...)
 *   wa_api_keys (id, tenant_id, api_key_hash, label, is_active, ...)
 *
 * We store only hash in DB, so we must match via PHP.
 *
 * @param string $rawApiKey API key sent by client
 * @return array|null Tenant row or null if not found / inactive
 */
function findTenantByApiKey(string $rawApiKey): ?array
{
    global $appConfig;

    $tables   = $appConfig['tables'];
    $tenants  = $tables['tenants'];
    $apiKeys  = $tables['api_keys'];

    $pdo = db();

    // Step 1: Get all active api_key rows (for perf we could do partial match / prefix in future)
    $sql = "
        SELECT k.id AS api_key_id,
               k.api_key_hash,
               k.label,
               k.is_active AS api_active,
               t.*
        FROM {$apiKeys} k
        INNER JOIN {$tenants} t ON t.id = k.tenant_id
        WHERE k.is_active = 1
          AND t.is_active = 1
    ";

    $stmt = $pdo->query($sql);
    $rows = $stmt->fetchAll();

    foreach ($rows as $row) {
        if (verifySecret($rawApiKey, $row['api_key_hash'])) {
            // Remove sensitive hash from result before returning
            unset($row['api_key_hash']);
            return $row;
        }
    }

    return null;
}

/**
 * Simple helper: get table name from config.
 *
 * Usage:
 *   $table = table('verification_sessions');
 *
 * @param string $key
 * @return string
 */
function table(string $key): string
{
    global $appConfig;
    return $appConfig['tables'][$key] ?? $key;
}