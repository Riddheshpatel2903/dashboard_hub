<?php
/**
 * Reset / Set Password page (Tailwind & Stitch Design System).
 */

session_start();
$pdo = require_once __DIR__ . '/../db/connection.php';
require_once __DIR__ . '/../config/config.php';

$token = $_GET['token'] ?? $_POST['token'] ?? '';
$error = '';
$success = '';
$validToken = false;
$userId = 0;

if (empty($token)) {
    $error = 'Missing password reset token.';
} else {
    try {
        // Look up token in DB
        $stmt = $pdo->prepare("
            SELECT user_id, expires_at, used FROM password_resets 
            WHERE reset_token = :token 
            LIMIT 1
        ");
        $stmt->execute(['token' => $token]);
        $resetData = $stmt->fetch();

        if (!$resetData) {
            $error = 'Invalid password reset token.';
        } elseif ($resetData['used'] == 1) {
            $error = 'This password reset token has already been used.';
        } elseif (strtotime($resetData['expires_at']) < time()) {
            $error = 'This password reset token has expired.';
        } else {
            $validToken = true;
            $userId = (int)$resetData['user_id'];
        }
    } catch (Exception $e) {
        $error = 'Database check failed: ' . $e->getMessage();
    }
}

if ($validToken && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        try {
            $pdo->beginTransaction();

            // 1. Update user password
            $stmt = $pdo->prepare("
                UPDATE users SET password = :password 
                WHERE id = :user_id
            ");
            $stmt->execute([
                'password' => $password,
                'user_id'  => $userId
            ]);

            // 2. Mark token as used
            $stmt = $pdo->prepare("
                UPDATE password_resets SET used = 1 
                WHERE reset_token = :token
            ");
            $stmt->execute(['token' => $token]);

            $pdo->commit();
            $success = 'Your password has been updated successfully!';
            $validToken = false; // Hide form on success

        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Failed to reset password: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <title>Set Password | Command Center</title>
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

        <!-- Card Container -->
        <div class="w-full bg-surface-container-lowest border border-surface-variant rounded-xl p-lg login-card">
            <div class="mb-lg">
                <h2 class="font-headline-sm text-headline-sm text-on-surface">Set New Password</h2>
                <p class="font-body-md text-body-md text-on-surface-variant mt-xs">Update your credentials to secure your workspace.</p>
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
                <div class="mt-lg">
                    <a href="<?php echo DASHBOARD_BASE_URL; ?>/auth/login.php" class="w-full h-12 bg-primary text-on-primary font-body-lg rounded-lg hover:opacity-90 active:scale-[0.98] transition-all flex items-center justify-center gap-sm">
                        <span>Proceed to Log In</span>
                        <span class="material-symbols-outlined text-xl">arrow_forward</span>
                    </a>
                </div>
            <?php endif; ?>

            <?php if ($validToken): ?>
                <form class="space-y-md" method="POST" action="">
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                    
                    <!-- Password Field -->
                    <div class="space-y-sm">
                        <label class="font-data-label text-data-label text-on-surface-variant block" for="password">NEW PASSWORD</label>
                        <div class="relative">
                            <input class="w-full h-10 px-md rounded-lg border border-outline-variant bg-surface-bright text-on-surface font-body-md focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary-fixed transition-all" 
                                   id="password" name="password" placeholder="Min 8 characters" type="password" required minlength="8"/>
                        </div>
                    </div>

                    <!-- Confirm Password Field -->
                    <div class="space-y-sm">
                        <label class="font-data-label text-data-label text-on-surface-variant block" for="confirm_password">CONFIRM NEW PASSWORD</label>
                        <div class="relative">
                            <input class="w-full h-10 px-md rounded-lg border border-outline-variant bg-surface-bright text-on-surface font-body-md focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary-fixed transition-all" 
                                   id="confirm_password" name="confirm_password" placeholder="••••••••" type="password" required minlength="8"/>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <button class="w-full h-12 bg-primary text-on-primary font-body-lg rounded-lg hover:opacity-90 active:scale-[0.98] transition-all flex items-center justify-center gap-sm mt-lg" type="submit">
                        <span>Save Password</span>
                        <span class="material-symbols-outlined text-xl">save</span>
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
