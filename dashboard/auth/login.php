<?php
/**
 * Dashboard User Login (Tailwind & Stitch Design System).
 */

session_start();
$pdo = require_once __DIR__ . '/../db/connection.php';
require_once __DIR__ . '/../config/config.php';

// If session is already active, redirect home
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'client') {
        header('Location: ' . DASHBOARD_BASE_URL . '/pages/dashboard_home.php');
    } else {
        header('Location: ' . DASHBOARD_BASE_URL . '/admin/clients_overview.php');
    }
    exit();
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    if (empty($email) || empty($password)) {
        $error = 'Please enter your email and password.';
    } else {
        try {
            // 1. Check rate-limit: Max 5 failed attempts in the last 15 minutes
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM login_attempts 
                WHERE email = :email AND ip_address = :ip AND attempted_at >= DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 15 MINUTE)
            ");
            $stmt->execute(['email' => $email, 'ip' => $ipAddress]);
            $failedAttempts = $stmt->fetchColumn();

            if ($failedAttempts >= 5) {
                $error = 'Too many failed login attempts. Locked for 15 minutes.';
            } else {
                // 2. Fetch user
                $stmt = $pdo->prepare("
                    SELECT id, client_id, password, role FROM users 
                    WHERE email = :email 
                    LIMIT 1
                ");
                $stmt->execute(['email' => $email]);
                $user = $stmt->fetch();

                if ($user && $password === $user['password']) {
                    // Success: Clear login attempts history for this IP & email
                    $stmt = $pdo->prepare("
                        DELETE FROM login_attempts 
                        WHERE email = :email AND ip_address = :ip
                    ");
                    $stmt->execute(['email' => $email, 'ip' => $ipAddress]);

                    // Update last login timestamp
                    $stmt = $pdo->prepare("
                        UPDATE users SET last_login_at = CURRENT_TIMESTAMP WHERE id = :id
                    ");
                    $stmt->execute(['id' => $user['id']]);

                    // Populate Session
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['client_id'] = $user['client_id'];

                    if ($user['role'] === 'client') {
                        header('Location: ' . DASHBOARD_BASE_URL . '/pages/dashboard_home.php');
                    } else {
                        header('Location: ' . DASHBOARD_BASE_URL . '/admin/clients_overview.php');
                    }
                    exit();
                } else {
                    // Record failure for rate limiting
                    $stmt = $pdo->prepare("
                        INSERT INTO login_attempts (email, ip_address) 
                        VALUES (:email, :ip)
                    ");
                    $stmt->execute(['email' => $email, 'ip' => $ipAddress]);

                    $error = 'Incorrect email or password.';
                }
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
    <title>Login | Command Center</title>
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
                <h2 class="font-headline-sm text-headline-sm text-on-surface">Welcome back</h2>
                <p class="font-body-md text-body-md text-on-surface-variant mt-xs">Access your marketing dashboard</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="bg-error-container text-on-error-container border border-error/20 p-md rounded-lg mb-lg text-body-sm flex items-center gap-sm">
                    <span class="material-symbols-outlined text-xl">warning</span>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>

            <form class="space-y-md" method="POST" action="">
                <!-- Email Field -->
                <div class="space-y-sm">
                    <label class="font-data-label text-data-label text-on-surface-variant block" for="email">EMAIL ADDRESS</label>
                    <div class="relative">
                        <input class="w-full h-10 px-md rounded-lg border border-outline-variant bg-surface-bright text-on-surface font-body-md focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary-fixed transition-all" 
                               id="email" name="email" placeholder="name@agency.com" type="email" required value="<?php echo htmlspecialchars($email); ?>"/>
                    </div>
                </div>

                <!-- Password Field -->
                <div class="space-y-sm">
                    <div class="flex justify-between items-center">
                        <label class="font-data-label text-data-label text-on-surface-variant block" for="password">PASSWORD</label>
                        <a class="font-body-sm text-body-sm text-primary hover:underline transition-all" href="<?php echo DASHBOARD_BASE_URL; ?>/auth/forgot_password.php">Forgot password?</a>
                    </div>
                    <div class="relative">
                        <input class="w-full h-10 px-md rounded-lg border border-outline-variant bg-surface-bright text-on-surface font-body-md focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary-fixed transition-all" 
                               id="password" name="password" placeholder="••••••••" type="password" required/>
                    </div>
                </div>

                <!-- Login Button -->
                <button class="w-full h-12 bg-primary text-on-primary font-body-lg rounded-lg hover:opacity-90 active:scale-[0.98] transition-all flex items-center justify-center gap-sm mt-lg" type="submit">
                    <span>Login</span>
                    <span class="material-symbols-outlined text-xl">login</span>
                </button>
            </form>
        </div>

        <!-- Footer / Utility -->
        <footer class="mt-xl flex gap-lg opacity-60">
            <a class="font-data-label text-data-label text-on-surface-variant hover:text-primary transition-colors" href="#">PRIVACY POLICY</a>
            <a class="font-data-label text-data-label text-on-surface-variant hover:text-primary transition-colors" href="#">TERMS OF SERVICE</a>
            <a class="font-data-label text-data-label text-on-surface-variant hover:text-primary transition-colors" href="#">HELP CENTER</a>
        </footer>
    </main>

    <!-- Micro-interaction Script -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const inputs = document.querySelectorAll('input');
            
            inputs.forEach(input => {
                input.addEventListener('focus', () => {
                    input.parentElement.previousElementSibling?.classList.add('text-primary');
                });
                
                input.addEventListener('blur', () => {
                    input.parentElement.previousElementSibling?.classList.remove('text-primary');
                });
            });

            // Lightweight atmospheric interaction: subtle mouse-tracking movement on the card
            const card = document.querySelector('.login-card');
            document.addEventListener('mousemove', (e) => {
                const xAxis = (window.innerWidth / 2 - e.pageX) / 100;
                const yAxis = (window.innerHeight / 2 - e.pageY) / 100;
                card.style.transform = `translate(${xAxis}px, ${yAxis}px)`;
            });
        });
    </script>
</body>
</html>
