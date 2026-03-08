<?php
// admin/index.php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/../db/config.php';

// ---------- SIMPLE ADMIN LOGIN (DEV LEVEL) ----------
// TODO: In production, move these to env/config & hash-based check

const ADMIN_USERNAME = 'admin';
const ADMIN_PASSWORD = 'admin123'; // CHANGE THIS IMMEDIATELY IN PRODUCTION

$isLoggedIn = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
$loginError = '';
$newCredentials = null;

// Handle login
if (!$isLoggedIn && ($_SERVER['REQUEST_METHOD'] === 'POST') && ($_POST['action'] ?? '') === 'login') {
    $user = trim($_POST['username'] ?? '');
    $pass = trim($_POST['password'] ?? '');

    if ($user === ADMIN_USERNAME && $pass === ADMIN_PASSWORD) {
        $_SESSION['admin_logged_in'] = true;
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    } else {
        $loginError = 'Invalid admin credentials.';
    }
}

// Handle logout
if ($isLoggedIn && isset($_GET['logout'])) {
    $_SESSION = [];
    session_destroy();
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// If logged in, handle tenant creation
if ($isLoggedIn && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_tenant') {
    $name = trim($_POST['name'] ?? '');
    $webhook = trim($_POST['webhook_url'] ?? '');

    if ($name === '') {
        $tenantError = 'Tenant name is required.';
    } else {
        try {
            $pdo = db();

            // Generate client_id and client_secret
            $clientId        = 'wp_' . substr(generateToken(8), 0, 16);
            $clientSecretRaw = 'cs_' . generateToken(16);
            $clientSecretHash = hashSecret($clientSecretRaw);

            // Insert tenant
            $stmt = $pdo->prepare("
                INSERT INTO wa_tenants (name, client_id, client_secret_hash, webhook_url, is_active)
                VALUES (:name, :client_id, :secret_hash, :webhook_url, 1)
            ");
            $stmt->execute([
                ':name'        => $name,
                ':client_id'   => $clientId,
                ':secret_hash' => $clientSecretHash,
                ':webhook_url' => $webhook !== '' ? $webhook : null,
            ]);

            $tenantId = (int) $pdo->lastInsertId();

            // Generate API key
            $apiKeyRaw    = 'wk_live_' . generateToken(16);
            $apiKeyPublic = substr($apiKeyRaw, 0, 24); // public identifier/prefix
            $apiKeyHash   = hashSecret($apiKeyRaw);

            $stmt = $pdo->prepare("
                INSERT INTO wa_api_keys (tenant_id, api_key_public, api_key_hash, label, is_active)
                VALUES (:tenant_id, :api_key_public, :api_key_hash, :label, 1)
            ");
            $stmt->execute([
                ':tenant_id'      => $tenantId,
                ':api_key_public' => $apiKeyPublic,
                ':api_key_hash'   => $apiKeyHash,
                ':label'          => 'Default key',
            ]);

            $newCredentials = [
                'name'            => $name,
                'client_id'       => $clientId,
                'client_secret'   => $clientSecretRaw,
                'api_key'         => $apiKeyRaw,
                'webhook_url'     => $webhook ?: null,
            ];
        } catch (Throwable $e) {
            error_log('Error creating tenant: ' . $e->getMessage());
            $tenantError = 'Failed to create tenant. Please try again.';
        }
    }
}

// Load tenants list (for admin view)
$tenants = [];
if ($isLoggedIn) {
    try {
        $pdo = db();
        $sql = "
            SELECT t.id,
                   t.name,
                   t.client_id,
                   t.webhook_url,
                   t.is_active,
                   t.created_at,
                   COUNT(k.id) AS api_key_count
            FROM wa_tenants t
            LEFT JOIN wa_api_keys k ON k.tenant_id = t.id
            GROUP BY t.id
            ORDER BY t.created_at DESC
        ";
        $tenants = $pdo->query($sql)->fetchAll();
    } catch (Throwable $e) {
        error_log('Error loading tenants: ' . $e->getMessage());
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<style>
    .admin-wrapper {
        max-width: 1120px;
        margin: 0 auto;
        padding: 1rem 1.2rem 3rem;
        font-family: system-ui, -apple-system, BlinkMacSystemFont, "SF Pro Text",
            "Segoe UI", sans-serif;
    }

    .admin-card {
        background: radial-gradient(circle at top left, rgba(15,23,42,0.95), rgba(15,23,42,0.98));
        border-radius: 1rem;
        border: 1px solid rgba(30,64,175,0.6);
        box-shadow: 0 18px 45px rgba(15, 23, 42, 0.85);
        padding: 1.5rem 1.4rem;
        margin-bottom: 1.4rem;
    }

    .admin-header-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        margin-bottom: 0.75rem;
    }

    .admin-title {
        font-size: 1.3rem;
        font-weight: 600;
        color: #e5e7eb;
    }

    .admin-subtitle {
        font-size: 0.9rem;
        color: #9ca3af;
        margin-top: 0.2rem;
    }

    .admin-badge {
        padding: 0.25rem 0.7rem;
        border-radius: 999px;
        border: 1px solid rgba(148,163,184,0.5);
        font-size: 0.75rem;
        color: #e5e7eb;
    }

    .admin-form-grid {
        display: grid;
        grid-template-columns: minmax(0, 1.2fr) minmax(0, 1fr);
        gap: 1rem;
    }

    .admin-field {
        display: flex;
        flex-direction: column;
        gap: 0.3rem;
        margin-bottom: 0.5rem;
    }

    .admin-label {
        font-size: 0.85rem;
        color: #e5e7eb;
        font-weight: 500;
    }

    .admin-input,
    .admin-text {
        border-radius: 0.7rem;
        border: 1px solid rgba(51,65,85,0.9);
        background: rgba(15,23,42,0.9);
        padding: 0.6rem 0.8rem;
        font-size: 0.9rem;
        color: #e5e7eb;
    }

    .admin-input:focus,
    .admin-text:focus {
        outline: none;
        border-color: #22c55e;
        box-shadow: 0 0 0 1px rgba(34,197,94,0.5);
    }

    .admin-help {
        font-size: 0.78rem;
        color: #9ca3af;
    }

    .admin-button-primary {
        border: none;
        border-radius: 999px;
        background: linear-gradient(135deg, #22c55e, #4ade80);
        padding: 0.55rem 1.4rem;
        font-size: 0.9rem;
        font-weight: 600;
        color: #020617;
        cursor: pointer;
        box-shadow: 0 14px 35px rgba(34,197,94,0.45);
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
    }

    .admin-button-primary:hover {
        filter: brightness(1.05);
    }

    .admin-error {
        margin-top: 0.5rem;
        border-radius: 0.7rem;
        background: rgba(248,113,113,0.07);
        border: 1px solid rgba(248,113,113,0.65);
        color: #fecaca;
        font-size: 0.8rem;
        padding: 0.5rem 0.7rem;
    }

    .admin-success-card {
        margin-top: 0.8rem;
        border-radius: 0.7rem;
        background: rgba(22,163,74,0.08);
        border: 1px solid rgba(34,197,94,0.8);
        padding: 0.7rem 0.9rem;
        font-size: 0.85rem;
        color: #bbf7d0;
    }

    .admin-credentials-grid {
        display: grid;
        grid-template-columns: minmax(0, 1.1fr) minmax(0, 1.1fr);
        gap: 0.6rem;
        margin-top: 0.4rem;
    }

    .admin-code {
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
        font-size: 0.8rem;
        background: rgba(15,23,42,0.9);
        border-radius: 0.5rem;
        padding: 0.35rem 0.6rem;
        border: 1px solid rgba(51,65,85,0.9);
        word-break: break-all;
    }

    .admin-tenants-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 0.6rem;
        font-size: 0.86rem;
    }

    .admin-tenants-table th,
    .admin-tenants-table td {
        padding: 0.45rem 0.4rem;
        border-bottom: 1px solid rgba(30,41,59,0.9);
        text-align: left;
    }

    .admin-tenants-table th {
        font-size: 0.8rem;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: #9ca3af;
    }

    .admin-tenants-table td {
        color: #e5e7eb;
    }

    .badge-pill {
        padding: 0.15rem 0.6rem;
        border-radius: 999px;
        font-size: 0.75rem;
    }

    .badge-green {
        background: rgba(22,163,74,0.15);
        color: #4ade80;
    }

    .badge-red {
        background: rgba(239,68,68,0.15);
        color: #fca5a5;
    }

    .admin-login-card {
        max-width: 420px;
        margin: 0 auto 3rem;
    }

    @media (max-width: 768px) {
        .admin-form-grid {
            grid-template-columns: minmax(0, 1fr);
        }

        .admin-credentials-grid {
            grid-template-columns: minmax(0, 1fr);
        }
    }
</style>

<div class="admin-wrapper">
    <?php if (!$isLoggedIn): ?>
        <div class="admin-card admin-login-card">
            <div class="admin-header-row">
                <div>
                    <div class="admin-title">Admin Login</div>
                    <div class="admin-subtitle">
                        Webpeaker Auth configuration panel.
                    </div>
                </div>
                <div class="admin-badge">Self-hosted</div>
            </div>

            <form method="post" autocomplete="off">
                <input type="hidden" name="action" value="login">
                <div class="admin-field">
                    <label class="admin-label" for="username">Username</label>
                    <input class="admin-input" id="username" name="username" required>
                </div>
                <div class="admin-field">
                    <label class="admin-label" for="password">Password</label>
                    <input class="admin-input" id="password" type="password" name="password" required>
                    <div class="admin-help">
                        Default: <strong>admin / admin123</strong> — change inside <code>admin/index.php</code>.
                    </div>
                </div>
                <button class="admin-button-primary" type="submit">
                    <span>Sign in</span>
                    <span>→</span>
                </button>

                <?php if ($loginError): ?>
                    <div class="admin-error"><?= htmlspecialchars($loginError) ?></div>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <?php else: ?>
        <div class="admin-card">
            <div class="admin-header-row">
                <div>
                    <div class="admin-title">Tenants &amp; API access</div>
                    <div class="admin-subtitle">
                        Create companies, generate credentials, and share Client ID / API key with them.
                    </div>
                </div>
                <div class="admin-badge">
                    <span>Logged in as <?= htmlspecialchars(ADMIN_USERNAME) ?></span>
                    &nbsp;|&nbsp;
                    <a href="?logout=1" style="color:#fca5a5;text-decoration:none;">Logout</a>
                </div>
            </div>

            <form method="post" class="admin-form">
                <input type="hidden" name="action" value="create_tenant">
                <div class="admin-form-grid">
                    <div>
                        <div class="admin-field">
                            <label class="admin-label" for="name">Company / App name</label>
                            <input class="admin-input" id="name" name="name" required placeholder="e.g. My SaaS App">
                            <div class="admin-help">
                                This will appear in your internal dashboard & logs.
                            </div>
                        </div>
                    </div>
                    <div>
                        <div class="admin-field">
                            <label class="admin-label" for="webhook_url">Webhook URL (optional)</label>
                            <input class="admin-input" id="webhook_url" name="webhook_url" placeholder="https://client.com/auth/callback">
                            <div class="admin-help">
                                We will call this URL when a WhatsApp verification completes.
                            </div>
                        </div>
                    </div>
                </div>

                <button class="admin-button-primary" type="submit">
                    <span>Create tenant</span>
                    <span>＋</span>
                </button>

                <?php if (!empty($tenantError)): ?>
                    <div class="admin-error"><?= htmlspecialchars($tenantError) ?></div>
                <?php endif; ?>

                <?php if (!empty($newCredentials)): ?>
                    <div class="admin-success-card">
                        <strong>Tenant created successfully.</strong>
                        <div class="admin-help" style="margin-top:0.2rem;">
                            Copy these credentials now. You won’t be able to see the secrets again later.
                        </div>
                        <div class="admin-credentials-grid">
                            <div>
                                <div class="admin-help">Client ID</div>
                                <div class="admin-code"><?= htmlspecialchars($newCredentials['client_id']) ?></div>
                            </div>
                            <div>
                                <div class="admin-help">Client Secret</div>
                                <div class="admin-code"><?= htmlspecialchars($newCredentials['client_secret']) ?></div>
                            </div>
                            <div>
                                <div class="admin-help">API Key</div>
                                <div class="admin-code"><?= htmlspecialchars($newCredentials['api_key']) ?></div>
                            </div>
                            <?php if (!empty($newCredentials['webhook_url'])): ?>
                                <div>
                                    <div class="admin-help">Webhook URL</div>
                                    <div class="admin-code"><?= htmlspecialchars($newCredentials['webhook_url']) ?></div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </form>
        </div>

        <div class="admin-card">
            <div class="admin-header-row">
                <div>
                    <div class="admin-title">Existing tenants</div>
                    <div class="admin-subtitle">
                        Share only the <strong>Client ID</strong>, <strong>Client Secret</strong> and <strong>API Key</strong> with your customers.
                    </div>
                </div>
            </div>

            <?php if (empty($tenants)): ?>
                <div class="admin-help">
                    No tenants yet. Create your first company using the form above.
                </div>
            <?php else: ?>
                <div class="admin-table-wrapper">
                    <table class="admin-tenants-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Company</th>
                                <th>Client ID</th>
                                <th>Webhook</th>
                                <th>API keys</th>
                                <th>Status</th>
                                <th>Created</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($tenants as $t): ?>
                            <tr>
                                <td>#<?= (int) $t['id'] ?></td>
                                <td><?= htmlspecialchars($t['name']) ?></td>
                                <td>
                                    <span class="admin-code" style="display:inline-block;">
                                        <?= htmlspecialchars($t['client_id']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($t['webhook_url'])): ?>
                                        <a href="<?= htmlspecialchars($t['webhook_url']) ?>" target="_blank" style="color:#93c5fd;font-size:0.8rem;">
                                            <?= htmlspecialchars($t['webhook_url']) ?>
                                        </a>
                                    <?php else: ?>
                                        <span style="font-size:0.8rem;color:#64748b;">—</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= (int) $t['api_key_count'] ?></td>
                                <td>
                                    <?php if ((int)$t['is_active'] === 1): ?>
                                        <span class="badge-pill badge-green">Active</span>
                                    <?php else: ?>
                                        <span class="badge-pill badge-red">Disabled</span>
                                    <?php endif; ?>
                                </td>
                                <td style="font-size:0.8rem;color:#9ca3af;">
                                    <?= htmlspecialchars($t['created_at']) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php
require_once __DIR__ . '/../includes/footer.php';