<?php
/**
 * Forgot Password request handler (Tailwind & Stitch Design System).
 */

session_start();
$pdo = require_once __DIR__ . '/../db/connection.php';
require_once __DIR__ . '/../config/config.php';

$error = '';
$success = '';
$resetLink = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if (empty($email)) {
        $error = 'Please enter your email address.';
    } else {
        try {
            // Find user in local database
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
            $stmt->execute(['email' => $email]);
            $user = $stmt->fetch();

            if ($user) {
                $userId = $user['id'];
                $token = bin2hex(random_bytes(32));
                $expiresAt = date('Y-m-d H:i:s', time() + 3600); // 1 hour expiry

                // Store reset token
                $stmt = $pdo->prepare("
                    INSERT INTO password_resets (user_id, reset_token, expires_at)
                    VALUES (:user_id, :token, :expires_at)
                ");
                $stmt->execute([
                    'user_id'    => $userId,
                    'token'      => $token,
                    'expires_at' => $expiresAt
                ]);

                $resetLink = DASHBOARD_BASE_URL . '/auth/reset_password.php?token=' . $token;
                $success = 'Password reset token generated successfully!';
            } else {
                // Return generic success message to prevent user enumeration
                $success = 'If that email address exists in our system, a password reset link has been generated.';
            }
        } catch (Exception $e) {
            $error = 'System error: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <title>Forgot Password | Command Center</title>
    <?php include __DIR__ . '/../includes/head_inc.php'; ?>
    <style>
        body {
            background-color: #f8f9fc; /* bg-background */
        }
        .login-card {
            box-shadow: 0px 4px 12px rgba(19, 26, 44, 0.08);
        }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-md">
    <main class="w-full max-w-[420px] flex flex-col items-center">
        <!-- Logo Section -->
        <div class="mb-xl flex flex-col items-center gap-sm">
            <div class="w-12 h-12 bg-primary rounded-lg flex items-center justify-center text-on-primary">
                <span class="material-symbols-outlined text-3xl">terminal</span>
            </div>
            <h1 class="font-display-md text-display-md text-primary tracking-tight">Command Center</h1>
        </div>

        <!-- Login Card -->
        <div class="w-full bg-surface-container-lowest border border-surface-variant rounded-xl p-lg login-card">
            <div class="mb-lg">
                <h2 class="font-headline-sm text-headline-sm text-on-surface">Reset Password</h2>
                <p class="font-body-md text-body-md text-on-surface-variant mt-xs">Enter your email to request a secure password change token.</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="bg-error-container text-on-error-container border border-error/20 p-md rounded-lg mb-lg text-body-sm flex items-center gap-sm">
                    <span class="material-symbols-outlined text-xl">warning</span>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="bg-secondary-container text-on-secondary-container border border-primary/20 p-md rounded-lg mb-lg text-body-sm flex items-center gap-sm">
                    <span class="material-symbols-outlined text-xl">check_circle</span>
                    <span><?php echo htmlspecialchars($success); ?></span>
                </div>
            <?php endif; ?>

            <?php if (!empty($resetLink)): ?>
                <div class="bg-surface-container border border-outline-variant p-md rounded-lg mb-lg text-body-sm text-center">
                    <p class="font-bold text-on-surface mb-xs">Development Reset Link</p>
                    <a href="<?php echo htmlspecialchars($resetLink); ?>" class="text-primary font-semibold hover:underline break-all">Reset Password Now</a>
                </div>
            <?php endif; ?>

            <form class="space-y-md" method="POST" action="">
                <!-- Email Field -->
                <div class="space-y-sm">
                    <label class="font-data-label text-data-label text-on-surface-variant block" for="email">EMAIL ADDRESS</label>
                    <div class="relative">
                        <input class="w-full h-10 px-md rounded-lg border border-outline-variant bg-surface-bright text-on-surface font-body-md focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary-fixed transition-all" 
                               id="email" name="email" placeholder="name@agency.com" type="email" required/>
                    </div>
                </div>

                <!-- Submit Button -->
                <button class="w-full h-12 bg-primary text-on-primary font-body-lg rounded-lg hover:opacity-90 active:scale-[0.98] transition-all flex items-center justify-center gap-sm mt-lg" type="submit">
                    <span>Request Reset</span>
                    <span class="material-symbols-outlined text-xl">key</span>
                </button>
            </form>

            <div class="mt-xl pt-lg border-t border-surface-variant flex flex-col gap-md">
                <p class="font-body-sm text-body-sm text-on-surface-variant text-center">
                    Remember your credentials? <a class="text-primary font-semibold hover:underline" href="<?php echo DASHBOARD_BASE_URL; ?>/auth/login.php">Back to login</a>
                </p>
            </div>
        </div>

        <!-- Footer / Utility -->
        <footer class="mt-xl flex gap-lg opacity-60">
            <a class="font-data-label text-data-label text-on-surface-variant hover:text-primary transition-colors" href="#">PRIVACY POLICY</a>
            <a class="font-data-label text-data-label text-on-surface-variant hover:text-primary transition-colors" href="#">TERMS OF SERVICE</a>
            <a class="font-data-label text-data-label text-on-surface-variant hover:text-primary transition-colors" href="#">HELP CENTER</a>
        </footer>
    </main>
</body>
</html>
