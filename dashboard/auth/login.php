<?php
/**
 * Dashboard User Login (Tailwind & Stitch Design System).
 */

require_once __DIR__ . '/../config/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// If session is already active, redirect home
if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['role'] ?? 'client';
    $clientId = $_SESSION['client_id'] ?? null;
    
    if ($role === 'client' && $clientId !== null) {
        header('Location: ' . DASHBOARD_BASE_URL . '/pages/dashboard_home.php');
    } else {
        header('Location: ' . DASHBOARD_BASE_URL . '/admin/clients_overview.php');
    }
    exit();
}

$error = '';
$email = '';
$pdo = null;

try {
    $pdo = require __DIR__ . '/../db/connection.php';
    if (!($pdo instanceof PDO)) {
        $pdo = null;
    }
} catch (Exception $e) {
    $pdo = null;
    $error = 'Database Connection Failed: ' . $e->getMessage() . '. Please verify MySQL is running in XAMPP.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
        || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    if (!($pdo instanceof PDO)) {
        // DB connection error already set in $error
    } else if (empty($email) || empty($password)) {
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

                // Support both hashed passwords and legacy plain text passwords
                $passwordValid = false;
                if ($user) {
                    if ($password === $user['password'] || password_verify($password, $user['password'])) {
                        $passwordValid = true;
                    }
                }

                if ($user && $passwordValid) {
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
                    $_SESSION['user_id'] = (int)$user['id'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['client_id'] = $user['client_id'] !== null ? (int)$user['client_id'] : null;

                    // No need to sync analytics to database upon login since we fetch live from API

                    // Ensure session is written and saved to disk before returning or redirecting
                    session_write_close();

                    $baseUrl = DASHBOARD_BASE_URL;
                    if ($user['role'] === 'client' && $user['client_id'] !== null) {
                        $redirectUrl = $baseUrl . '/pages/dashboard_home.php';
                    } else {
                        $redirectUrl = $baseUrl . '/admin/clients_overview.php';
                    }

                    if ($isAjax) {
                        header('Content-Type: application/json');
                        echo json_encode(['success' => true, 'redirect' => $redirectUrl]);
                        exit();
                    } else {
                        header('Location: ' . $redirectUrl);
                        exit();
                    }
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

    if ($isAjax && !empty($error)) {
        header('Content-Type: application/json', true, 400);
        echo json_encode(['success' => false, 'error' => $error]);
        exit();
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
        <div class="mb-lg flex flex-col items-center gap-sm">
            <div class="w-12 h-12 bg-primary rounded-lg flex items-center justify-center text-on-primary shadow-sm">
                <span class="material-symbols-outlined text-3xl">terminal</span>
            </div>
            <h1 class="font-display-md text-display-md text-primary tracking-tight">Command Center</h1>
            
            <!-- Live Network & Database Connection Badge -->
            <div class="mt-xs">
                <?php if ($pdo instanceof PDO): ?>
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-semibold bg-emerald-500/10 text-emerald-700 border border-emerald-500/20 shadow-xs">
                        <span class="relative flex h-2 w-2">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                        </span>
                        <span>Network OK &bull; Database Online</span>
                    </div>
                <?php else: ?>
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-semibold bg-rose-500/10 text-rose-700 border border-rose-500/20 shadow-xs">
                        <span class="inline-flex rounded-full h-2 w-2 bg-rose-500"></span>
                        <span>Database Disconnected</span>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Login Card -->
        <div class="w-full bg-surface-container-lowest border border-surface-variant rounded-xl p-lg login-card">
            <div class="mb-lg">
                <h2 class="font-headline-sm text-headline-sm text-on-surface">Welcome back</h2>
                <p class="font-body-md text-body-md text-on-surface-variant mt-xs">Access your marketing dashboard</p>
            </div>

            <!-- Fetching Data / Network Progress Banner -->
            <div id="login-status-banner" class="hidden mb-lg p-md rounded-lg text-body-sm flex items-center gap-sm bg-blue-50 border border-blue-200 text-blue-800 transition-all duration-300">
                <span id="status-icon" class="material-symbols-outlined text-xl animate-spin">sync</span>
                <span id="status-message">Establishing secure connection...</span>
            </div>

            <div id="login-error-container">
                <?php if (!empty($error)): ?>
                    <div class="bg-error-container text-on-error-container border border-error/20 p-md rounded-lg mb-lg text-body-sm flex items-center gap-sm">
                        <span class="material-symbols-outlined text-xl">warning</span>
                        <span><?php echo htmlspecialchars($error); ?></span>
                    </div>
                <?php endif; ?>
            </div>

            <form id="login-form" class="space-y-md" method="POST" action="">
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
                <button id="login-submit-btn" class="w-full h-12 bg-primary text-on-primary font-body-lg rounded-lg hover:opacity-90 active:scale-[0.98] transition-all flex items-center justify-center gap-sm mt-lg shadow-sm" type="submit">
                    <span id="btn-text">Login</span>
                    <span id="btn-icon" class="material-symbols-outlined text-xl">login</span>
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

            // Prevent form submit cancellation & handle submission with dynamic status indicators
            const loginForm = document.getElementById('login-form');
            const submitBtn = document.getElementById('login-submit-btn');
            const btnText = document.getElementById('btn-text');
            const btnIcon = document.getElementById('btn-icon');
            const errorContainer = document.getElementById('login-error-container');
            const statusBanner = document.getElementById('login-status-banner');
            const statusMessage = document.getElementById('status-message');
            const statusIcon = document.getElementById('status-icon');

            if (loginForm) {
                loginForm.addEventListener('submit', function(e) {
                    e.preventDefault(); // Prevent standard page reload that cancels fetch requests

                    // Lock button & show animated loader
                    submitBtn.disabled = true;
                    submitBtn.classList.add('opacity-70', 'cursor-not-allowed');
                    btnText.textContent = 'Authenticating...';
                    btnIcon.textContent = 'sync';
                    btnIcon.classList.add('animate-spin');

                    // Reset error container & show progress status banner
                    errorContainer.innerHTML = '';
                    statusBanner.classList.remove('hidden');
                    statusMessage.textContent = 'Connecting to server & verifying credentials...';

                    const formData = new FormData(loginForm);

                    fetch(window.location.href, {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: formData
                    })
                    .then(async (response) => {
                        const data = await response.json().catch(() => ({}));
                        if (response.ok && data.success) {
                            statusMessage.textContent = 'Success! Fetching dashboard session data...';
                            statusBanner.className = 'mb-lg p-md rounded-lg text-body-sm flex items-center gap-sm bg-emerald-50 border border-emerald-200 text-emerald-800 transition-all duration-300';
                            statusIcon.textContent = 'check_circle';
                            statusIcon.classList.remove('animate-spin');

                            btnText.textContent = 'Redirecting...';
                            btnIcon.textContent = 'arrow_forward';

                            setTimeout(() => {
                                let target = data.redirect || '<?php echo DASHBOARD_BASE_URL; ?>/pages/dashboard_home.php';
                                window.location.replace(target);
                            }, 200);
                        } else {
                            throw new Error(data.error || 'Authentication failed. Please check your credentials.');
                        }
                    })
                    .catch((err) => {
                        statusBanner.classList.add('hidden');

                        errorContainer.innerHTML = `
                            <div class="bg-error-container text-on-error-container border border-error/20 p-md rounded-lg mb-lg text-body-sm flex items-center gap-sm">
                                <span class="material-symbols-outlined text-xl">warning</span>
                                <span>${err.message || 'Connection error. Please try again.'}</span>
                            </div>
                        `;
                        submitBtn.disabled = false;
                        submitBtn.classList.remove('opacity-70', 'cursor-not-allowed');
                        btnText.textContent = 'Login';
                        btnIcon.textContent = 'login';
                        btnIcon.classList.remove('animate-spin');
                    });
                });
            }
        });
    </script>
</body>
</html>
