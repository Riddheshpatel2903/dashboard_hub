<?php
/**
 * User Profile & Settings Page (Tailwind & Stitch Design System).
 */

require_once __DIR__ . '/../includes/session_check.php';
$pdo = require_once __DIR__ . '/../db/connection.php';
require_once __DIR__ . '/../includes/hub_client.php';

if ($client_id === null) {
    header('Location: ' . DASHBOARD_BASE_URL . '/admin/clients_overview.php');
    exit();
}

$error = '';
$success = '';

// 1. Fetch Client Profile details from Hub
$clientName = '';
$clientWebsite = '';

$hubRes = hubGetClient($client_id);
if (!empty($hubRes['success']) && !empty($hubRes['client'])) {
    $clientName = $hubRes['client']['name'];
    $clientWebsite = $hubRes['client']['website_url'];
} else {
    $error = 'Failed to load profile details from Hub server: ' . ($hubRes['error'] ?? 'Unknown Connection Error');
}

// 2. Handle Form Updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($csrfToken)) {
        $error = 'Security check failed. Invalid CSRF token.';
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'profile') {
            $newName = trim($_POST['name'] ?? '');
            $newWebsite = trim($_POST['website_url'] ?? '');
            
            if (empty($newName) || empty($newWebsite)) {
                $error = 'Business name and website URL cannot be empty.';
            } else {
                // Update profile on Hub
                $updateRes = hubUpdateClient($client_id, $newName, $newWebsite);
                if (!empty($updateRes['success'])) {
                    $clientName = $newName;
                    $clientWebsite = $newWebsite;
                    $success = 'Profile details updated successfully on the Hub!';
                } else {
                    $error = 'Failed to update Hub profile: ' . ($updateRes['error'] ?? 'Unknown Error');
                }
            }
        } elseif ($action === 'password') {
            $currentPass = $_POST['current_password'] ?? '';
            $newPass = $_POST['new_password'] ?? '';
            $confirmPass = $_POST['confirm_password'] ?? '';
            
            if (empty($currentPass) || empty($newPass) || empty($confirmPass)) {
                $error = 'All password fields are required.';
            } elseif (strlen($newPass) < 8) {
                $error = 'New password must be at least 8 characters long.';
            } elseif ($newPass !== $confirmPass) {
                $error = 'New passwords do not match.';
            } else {
                try {
                    // Fetch user's current password
                    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = :user_id LIMIT 1");
                    $stmt->execute(['user_id' => $user_id]);
                    $userPass = $stmt->fetchColumn();
                    
                    // Support both plain text and hashed passwords
                    $currentPassValid = false;
                    if ($userPass) {
                        if ($currentPass === $userPass || password_verify($currentPass, $userPass)) {
                            $currentPassValid = true;
                        }
                    }
                    
                    if ($userPass && $currentPassValid) {
                        // Store the new password securely using bcrypt hashing
                        $hashedPass = password_hash($newPass, PASSWORD_DEFAULT);
                        $stmtUpdate = $pdo->prepare("UPDATE users SET password = :password WHERE id = :user_id");
                        $stmtUpdate->execute(['password' => $hashedPass, 'user_id' => $user_id]);
                        
                        $success = 'Password changed successfully.';
                    } else {
                        $error = 'Current password is incorrect.';
                    }
                } catch (Exception $e) {
                    $error = 'Password update failed: ' . $e->getMessage();
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <title>Settings | Social Hub</title>
    <?php include __DIR__ . '/../includes/head_inc.php'; ?>
</head>
<body class="bg-surface-bright text-on-surface font-body-md antialiased">
    <!-- Sidebar Navigation -->
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <!-- Top App Bar -->
    <?php include __DIR__ . '/../includes/top_bar.php'; ?>

    <!-- Main Content -->
    <main class="ml-[240px] pt-16 min-h-screen">
        <div class="p-lg max-w-[1440px] mx-auto space-y-lg">
            <!-- Header -->
            <div>
                <h1 class="font-display-lg text-display-lg text-on-surface mb-xs">Settings</h1>
                <p class="font-body-md text-on-surface-variant">Update business information and account security credentials.</p>
            </div>

            <!-- Notifications -->
            <?php if (!empty($error)): ?>
                <div class="bg-error-container text-on-error-container p-md rounded-lg flex items-center gap-md border border-error/20 animate-in fade-in slide-in-from-top-2">
                    <span class="material-symbols-outlined text-xl">error</span>
                    <span class="font-body-md font-semibold"><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="bg-green-100 text-green-800 p-md rounded-lg flex items-center gap-md border border-green-200 animate-in fade-in slide-in-from-top-2">
                    <span class="material-symbols-outlined text-xl">check_circle</span>
                    <span class="font-body-md font-semibold"><?php echo htmlspecialchars($success); ?></span>
                </div>
            <?php endif; ?>

            <!-- Settings Form Layout Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-gutter">
                <!-- Business Profile Card -->
                <div class="bg-surface-container-lowest border border-surface-variant rounded-xl p-lg shadow-sm space-y-lg">
                    <div>
                        <h3 class="font-headline-sm text-headline-sm font-bold text-on-surface">Business Profile</h3>
                        <p class="text-on-surface-variant text-xs mt-xs">Update your global workspace metadata details.</p>
                    </div>
                    
                    <form method="POST" action="" class="space-y-md">
                        <input type="hidden" name="csrf_token" value="<?php echo getCsrfToken(); ?>">
                        <input type="hidden" name="action" value="profile">
                        <div class="space-y-xs">
                            <label for="name" class="font-data-label text-data-label text-on-surface-variant block">COMPANY / BUSINESS NAME</label>
                            <input type="text" id="name" name="name" class="w-full h-10 px-md bg-surface-container-low border border-surface-variant rounded-lg font-body-sm focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary" required value="<?php echo htmlspecialchars($clientName); ?>">
                        </div>
                        <div class="space-y-xs">
                            <label for="website_url" class="font-data-label text-data-label text-on-surface-variant block">WEBSITE URL</label>
                            <input type="url" id="website_url" name="website_url" class="w-full h-10 px-md bg-surface-container-low border border-surface-variant rounded-lg font-body-sm focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary" required value="<?php echo htmlspecialchars($clientWebsite); ?>">
                        </div>
                        <button type="submit" class="w-full h-10 bg-primary text-on-primary font-bold rounded-lg shadow-sm hover:opacity-90 active:scale-95 transition-all text-xs flex items-center justify-center gap-xs">
                            <span class="material-symbols-outlined text-sm">save</span>
                            <span>Save Profile</span>
                        </button>
                    </form>
                </div>

                <!-- Security Credentials Card -->
                <div class="bg-surface-container-lowest border border-surface-variant rounded-xl p-lg shadow-sm space-y-lg">
                    <div>
                        <h3 class="font-headline-sm text-headline-sm font-bold text-on-surface">Security Credentials</h3>
                        <p class="text-on-surface-variant text-xs mt-xs">Change your password keys to secure your login access.</p>
                    </div>
                    
                    <form method="POST" action="" class="space-y-md">
                        <input type="hidden" name="csrf_token" value="<?php echo getCsrfToken(); ?>">
                        <input type="hidden" name="action" value="password">
                        <div class="space-y-xs">
                            <label for="current_password" class="font-data-label text-data-label text-on-surface-variant block">CURRENT PASSWORD</label>
                            <input type="password" id="current_password" name="current_password" class="w-full h-10 px-md bg-surface-container-low border border-surface-variant rounded-lg font-body-sm focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary" required>
                        </div>
                        <div class="space-y-xs">
                            <label for="new_password" class="font-data-label text-data-label text-on-surface-variant block">NEW PASSWORD (MIN 8 CHARACTERS)</label>
                            <input type="password" id="new_password" name="new_password" class="w-full h-10 px-md bg-surface-container-low border border-surface-variant rounded-lg font-body-sm focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary" required minlength="8">
                        </div>
                        <div class="space-y-xs">
                            <label for="confirm_password" class="font-data-label text-data-label text-on-surface-variant block">CONFIRM NEW PASSWORD</label>
                            <input type="password" id="confirm_password" name="confirm_password" class="w-full h-10 px-md bg-surface-container-low border border-surface-variant rounded-lg font-body-sm focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary" required minlength="8">
                        </div>
                        <button type="submit" class="w-full h-10 bg-primary text-on-primary font-bold rounded-lg shadow-sm hover:opacity-90 active:scale-95 transition-all text-xs flex items-center justify-center gap-xs">
                            <span class="material-symbols-outlined text-sm">lock</span>
                            <span>Change Password</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
