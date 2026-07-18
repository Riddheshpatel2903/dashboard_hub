<?php
/**
 * Register New Client (Admin/Staff Only).
 */

require_once __DIR__ . '/../includes/role_check.php'; // Ensures logged-in staff/admin
$pdo = require_once __DIR__ . '/../db/connection.php';
require_once __DIR__ . '/../includes/hub_client.php';

$error = '';
$success = '';
$setupLink = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $websiteUrl = trim($_POST['website_url'] ?? '');
    $email = trim($_POST['email'] ?? '');

    if (empty($name) || empty($websiteUrl) || empty($email)) {
        $error = 'All fields are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        try {
            $pdo->beginTransaction();

            // 1. Check if user already exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
            $stmt->execute(['email' => $email]);
            if ($stmt->fetch()) {
                throw new Exception("A user with this email address already exists.");
            }

            // 2. Register client on the Hub via HTTP API
            $hubRes = hubRegisterClient($name, $websiteUrl);
            if (empty($hubRes['success']) || empty($hubRes['client_id']) || empty($hubRes['api_key'])) {
                $hubError = $hubRes['error'] ?? 'Unknown Hub registration error.';
                throw new Exception("Hub Registration Failed: " . $hubError);
            }

            $clientId = (int)$hubRes['client_id'];
            $hubApiKey = $hubRes['api_key'];

            // 3. Store Hub API Key in Dashboard DB
            $stmt = $pdo->prepare("
                INSERT INTO client_hub_keys (client_id, hub_api_key)
                VALUES (:client_id, :api_key)
            ");
            $stmt->execute([
                'client_id' => $clientId,
                'api_key'   => $hubApiKey
            ]);

            // 4. Create Dashboard user row with Client role (ungessable dummy password until setup)
            $dummyPassword = 'setup_required_' . bin2hex(random_bytes(16));
            $stmt = $pdo->prepare("
                INSERT INTO users (client_id, email, password, role)
                VALUES (:client_id, :email, :password, 'client')
            ");
            $stmt->execute([
                'client_id' => $clientId,
                'email'     => $email,
                'password'  => $dummyPassword
            ]);
            $newUserId = $pdo->lastInsertId();

            // 5. Generate Password Setup Token (using password reset table)
            $token = bin2hex(random_bytes(32));
            $expiresAt = date('Y-m-d H:i:s', time() + (24 * 3600)); // 24-hour expiry

            $stmt = $pdo->prepare("
                INSERT INTO password_resets (user_id, reset_token, expires_at)
                VALUES (:user_id, :token, :expires_at)
            ");
            $stmt->execute([
                'user_id'    => $newUserId,
                'token'      => $token,
                'expires_at' => $expiresAt
            ]);

            $pdo->commit();

            // Formulate activation link
            $setupLink = DASHBOARD_BASE_URL . '/auth/reset_password.php?token=' . $token;
            $success = "Client '{$name}' registered successfully!";

        } catch (Exception $e) {
            $pdo->rollBack();
            $error = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register Client - Agency Admin</title>
    <link rel="stylesheet" href="<?php echo DASHBOARD_BASE_URL; ?>/assets/css/style.css">
    <style>
        body { background: #0b0f19; color: #f3f4f6; padding: 2rem; }
        .admin-card { max-width: 500px; margin: 3rem auto; background: #111827; border: 1px solid #1f2937; border-radius: 12px; padding: 2rem; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.3); }
        h2 { color: #60a5fa; margin-top: 0; margin-bottom: 1.5rem; }
        .form-group { margin-bottom: 1.25rem; }
        label { display: block; margin-bottom: 0.5rem; color: #d1d5db; font-size: 0.85rem; font-weight: 500; }
        .form-input { width: 100%; background: #1f2937; border: 1px solid #374151; border-radius: 6px; padding: 0.7rem; color: #fff; font-size: 0.9rem; box-sizing: border-box; }
        .form-input:focus { outline: none; border-color: #60a5fa; }
        .btn-submit { background: #2563eb; color: #fff; border: none; border-radius: 6px; padding: 0.75rem 1.5rem; font-weight: 600; cursor: pointer; transition: background 0.2s; }
        .btn-submit:hover { background: #1d4ed8; }
        .alert { padding: 0.75rem; border-radius: 6px; font-size: 0.9rem; margin-bottom: 1.25rem; }
        .alert-error { background: rgba(239,68,68,0.15); border: 1px solid #ef4444; color: #f87171; }
        .alert-success { background: rgba(16,185,129,0.15); border: 1px solid #10b981; color: #34d399; }
        .activation-box { background: #1f2937; border: 1px dashed #374151; padding: 1rem; border-radius: 6px; margin-top: 1rem; }
        .activation-box a { color: #60a5fa; word-break: break-all; }
        .nav-back { display: inline-block; margin-top: 1.5rem; color: #9ca3af; text-decoration: none; font-size: 0.9rem; }
        .nav-back:hover { color: #f3f4f6; }
    </style>
</head>
<body>
    <div class="admin-card">
        <h2>Onboard New Client</h2>
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <?php if (!empty($setupLink)): ?>
            <div class="activation-box">
                <p style="margin-top:0; color:#e5e7eb; font-weight:bold;">Password Setup Required</p>
                <p style="font-size:0.85rem; color:#9ca3af;">Since this is a manual onboarding flow, share this link with the client to set up their password:</p>
                <a href="<?php echo htmlspecialchars($setupLink); ?>" target="_blank">Setup Client Account</a>
            </div>
            <a href="<?php echo DASHBOARD_BASE_URL; ?>/admin/clients_overview.php" class="nav-back">&larr; Return to Clients Overview</a>
        <?php else: ?>
            <form method="POST" action="">
                <div class="form-group">
                    <label for="name">Client Company/Business Name</label>
                    <input type="text" id="name" name="name" class="form-input" required placeholder="e.g. Acme Corp">
                </div>
                <div class="form-group">
                    <label for="website_url">Client Website URL</label>
                    <input type="url" id="website_url" name="website_url" class="form-input" required placeholder="https://example.com">
                </div>
                <div class="form-group">
                    <label for="email">User Email (Client Login)</label>
                    <input type="email" id="email" name="email" class="form-input" required placeholder="client@acme.com">
                </div>
                <button type="submit" class="btn-submit">Onboard Client</button>
            </form>
            <a href="<?php echo DASHBOARD_BASE_URL; ?>/admin/clients_overview.php" class="nav-back">&larr; Cancel</a>
        <?php endif; ?>
    </div>
</body>
</html>
