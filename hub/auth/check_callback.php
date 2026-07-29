<?php
/**
 * DIAGNOSTIC v2 — deploy to rbfitness.in/new-site/hub/auth/check_callback.php
 * DELETE after use.
 */

session_start();
error_reporting(E_ALL);
ini_set('display_errors', '1');

echo "<h2>Callback Diagnostic v2</h2><pre>";

// 1. PHP & environment
echo "=== ENVIRONMENT ===\n";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "HTTP_HOST: " . ($_SERVER['HTTP_HOST'] ?? '(not set)') . "\n";
echo "HTTPS: " . ($_SERVER['HTTPS'] ?? '(not set)') . "\n\n";

// 2. Config loading
echo "=== HUB CONFIG ===\n";
try {
    require_once __DIR__ . '/../config/config.php';
    echo "HUB_BASE_URL: " . HUB_BASE_URL . "\n";
    echo "DB_HOST: " . DB_HOST . "\n";
    echo "DB_NAME: " . DB_NAME . "\n";
    echo "DB_USER: " . DB_USER . "\n";
    echo "Config loaded: OK\n\n";
} catch (Throwable $e) {
    echo "Config FAILED: " . $e->getMessage() . "\n\n";
}

// 3. Database connection test
echo "=== DATABASE ===\n";
try {
    $dsn = sprintf("mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4", DB_HOST, DB_PORT, DB_NAME);
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    echo "DB Connection: OK\n";

    // Check key tables exist
    foreach (['platform_connections', 'platform_tokens'] as $table) {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        echo "Table '$table': " . ($stmt->fetch() ? 'EXISTS' : 'MISSING!') . "\n";
    }
} catch (Throwable $e) {
    echo "DB Connection FAILED: " . $e->getMessage() . "\n";
}
echo "\n";

// 4. CSRF logic test
echo "=== CSRF LOGIC ===\n";
$fakeState = base64_encode(json_encode([
    'client_id' => 1,
    'nonce'     => 'test_nonce_12345',
    'dashboard_url' => 'http://localhost/dashboard_hub/dashboard'
]));
$stateData = json_decode(base64_decode($fakeState), true);
echo "State decode: " . ($stateData ? 'OK' : 'FAILED') . "\n";
echo "Nonce in state: " . ($stateData['nonce'] ?? 'MISSING') . "\n";
echo "CSRF check result: " . (empty($stateData['nonce']) ? 'FAIL' : 'PASS') . "\n\n";

// 5. Callback file new-logic check
echo "=== CALLBACK FILES ===\n";
foreach (['callback_facebook.php','callback_youtube.php','callback_linkedin.php','callback_google_business.php'] as $f) {
    $path = __DIR__ . '/' . $f;
    $exists = file_exists($path);
    $content = $exists ? file_get_contents($path) : '';
    $hasNew = strpos($content, 'bypass strict session check') !== false;
    echo "$f: " . ($exists ? 'EXISTS' : 'MISSING') . " | New logic: " . ($hasNew ? 'YES' : 'NO - re-upload!') . "\n";
}
echo "\n";

// 6. Encryption test
echo "=== ENCRYPTION ===\n";
try {
    require_once __DIR__ . '/../utils/encryption.php';
    $enc = encrypt('test_token_value');
    $dec = decrypt($enc);
    echo "Encrypt/Decrypt: " . ($dec === 'test_token_value' ? 'OK' : 'FAILED') . "\n";
} catch (Throwable $e) {
    echo "Encryption FAILED: " . $e->getMessage() . "\n";
}
echo "\n";

// 7. Success page redirect target simulation
echo "=== SUCCESS REDIRECT SIMULATION ===\n";
$simulatedDashboardUrl = 'http://localhost/dashboard_hub/dashboard';
$returnUrl = rtrim($simulatedDashboardUrl, '/') . '/pages/connections.php';
echo "dashboard_url: $simulatedDashboardUrl\n";
echo "Redirect target: $returnUrl\n";
echo "HUB success URL: " . HUB_BASE_URL . "/auth/success.php\n";

echo "</pre>";
?>
