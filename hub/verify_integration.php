<?php
/**
 * Backend Integration Verification Dashboard.
 * Run in browser or CLI to test connection, encryption, logger, and directory permissions.
 */

// If requested from CLI, output text. Otherwise, output styled HTML.
$isCli = (PHP_SAPI === 'cli');

$results = [
    'extensions' => [
        'name' => 'Required PHP Extensions',
        'status' => 'pass',
        'details' => []
    ],
    'database' => [
        'name' => 'MySQL Database Connection',
        'status' => 'pass',
        'details' => ''
    ],
    'encryption' => [
        'name' => 'AES-256-CBC Encryption Engine',
        'status' => 'pass',
        'details' => ''
    ],
    'logger' => [
        'name' => 'System Logger Writing',
        'status' => 'pass',
        'details' => ''
    ],
    'permissions' => [
        'name' => 'Directory & File Permissions',
        'status' => 'pass',
        'details' => []
    ]
];

// 1. Verify PHP Extensions
$requiredExts = ['pdo', 'pdo_mysql', 'openssl', 'curl', 'json'];
foreach ($requiredExts as $ext) {
    $loaded = extension_loaded($ext);
    $results['extensions']['details'][$ext] = $loaded;
    if (!$loaded) {
        $results['extensions']['status'] = 'fail';
    }
}

// 2. Verify Database connection
try {
    $pdo = @require __DIR__ . '/db/connection.php';
    if ($pdo instanceof PDO) {
        $results['database']['details'] = 'Connected successfully to host ' . DB_HOST;
    } else {
        $results['database']['status'] = 'fail';
        $results['database']['details'] = 'Returned connection was not an instance of PDO.';
    }
} catch (Exception $e) {
    $results['database']['status'] = 'fail';
    $results['database']['details'] = 'DB Error: ' . $e->getMessage();
}

// 3. Verify Encryption Engine
try {
    require_once __DIR__ . '/utils/encryption.php';
    $testPlain = "secret_platform_token_1234!";
    $cipher = encrypt($testPlain);
    if (!$cipher) {
        throw new Exception("Encryption returned empty or false.");
    }
    
    $decrypted = decrypt($cipher);
    if ($decrypted !== $testPlain) {
        throw new Exception("Decrypted value does not match original plaintext.");
    }
    
    $results['encryption']['details'] = 'Pass: Correctly encrypted and decrypted test tokens.';
} catch (Exception $e) {
    $results['encryption']['status'] = 'fail';
    $results['encryption']['details'] = 'Encryption Error: ' . $e->getMessage();
}

// 4. Verify System Logger
try {
    require_once __DIR__ . '/utils/logger.php';
    log_message('info', 'Integration verification suite run successfully.');
    
    $logFile = __DIR__ . '/logs/app.log';
    if (file_exists($logFile) && is_writable($logFile)) {
        $results['logger']['details'] = 'Successfully initialized and appended test log to ' . basename($logFile);
    } else {
        $results['logger']['status'] = 'fail';
        $results['logger']['details'] = 'Log file could not be written to logs/app.log.';
    }
} catch (Exception $e) {
    $results['logger']['status'] = 'fail';
    $results['logger']['details'] = 'Logger Error: ' . $e->getMessage();
}

// 5. Verify Directory Permissions & Security files
$dirs = [
    'config' => __DIR__ . '/config',
    'db' => __DIR__ . '/db',
    'platforms' => __DIR__ . '/platforms',
    'storage' => __DIR__ . '/storage',
    'logs' => __DIR__ . '/logs',
    'utils' => __DIR__ . '/utils'
];

foreach ($dirs as $name => $path) {
    $exists = is_dir($path);
    $writable = is_writable($path);
    $results['permissions']['details'][$name] = [
        'exists' => $exists,
        'writable' => $writable
    ];
    if (!$exists) {
        $results['permissions']['status'] = 'fail';
    }
}

// Check .htaccess presence
$htaccessExists = file_exists(__DIR__ . '/.htaccess');
$results['permissions']['details']['.htaccess'] = [
    'exists' => $htaccessExists,
    'readable' => $htaccessExists ? is_readable(__DIR__ . '/.htaccess') : false
];
if (!$htaccessExists) {
    $results['permissions']['status'] = 'fail';
}

// Render CLI output
if ($isCli) {
    echo "=========================================\n";
    echo " HUB BACKEND INTEGRATION STATUS\n";
    echo "=========================================\n";
    foreach ($results as $key => $res) {
        $statusStr = strtoupper($res['status']);
        echo "[{$statusStr}] {$res['name']}\n";
        if (is_array($res['details'])) {
            foreach ($res['details'] as $subKey => $subVal) {
                if (is_array($subVal)) {
                    $detailsStr = implode(', ', array_map(function($k, $v) { return "$k: " . ($v ? 'YES' : 'NO'); }, array_keys($subVal), $subVal));
                    echo "  - {$subKey}: {$detailsStr}\n";
                } else {
                    $valStr = $subVal ? 'LOADED/OK' : 'MISSING/ERROR';
                    echo "  - {$subKey}: {$valStr}\n";
                }
            }
        } else {
            echo "  - Details: {$res['details']}\n";
        }
        echo "-----------------------------------------\n";
    }
    exit(0);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hub Backend Status</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #0b0f19; color: #f3f4f6; margin: 0; padding: 2rem; }
        .container { max-width: 800px; margin: 0 auto; }
        header { text-align: center; margin-bottom: 2rem; }
        h1 { font-size: 2rem; color: #60a5fa; margin: 0 0 0.5rem 0; font-weight: 700; letter-spacing: -0.025em; }
        p.subtitle { color: #9ca3af; margin: 0; }
        .card { background: #111827; border: 1px solid #1f2937; border-radius: 12px; padding: 1.5rem; margin-bottom: 1.25rem; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.2); }
        .card-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #1f2937; padding-bottom: 0.75rem; margin-bottom: 1rem; }
        .card-title { font-size: 1.1rem; font-weight: 600; color: #f9fafb; }
        .badge { padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.8rem; font-weight: bold; text-transform: uppercase; }
        .badge.pass { background: rgba(16, 185, 129, 0.2); color: #34d399; border: 1px solid #10b981; }
        .badge.fail { background: rgba(239, 68, 68, 0.2); color: #f87171; border: 1px solid #ef4444; }
        .details { font-size: 0.9rem; color: #9ca3af; line-height: 1.5; }
        ul { list-style: none; padding: 0; margin: 0; }
        li { display: flex; justify-content: space-between; padding: 0.4rem 0; border-bottom: 1px solid #1f2937; }
        li:last-child { border-bottom: none; }
        .stat-name { color: #d1d5db; }
        .stat-value { font-family: monospace; font-weight: bold; }
        .stat-value.ok { color: #34d399; }
        .stat-value.err { color: #f87171; }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>"The Hub" Integration Verification</h1>
            <p class="subtitle">Real-time status check of core PHP + MySQL platform integrations</p>
        </header>

        <?php foreach ($results as $key => $res): ?>
            <div class="card">
                <div class="card-header">
                    <div class="card-title"><?php echo htmlspecialchars($res['name']); ?></div>
                    <span class="badge <?php echo $res['status']; ?>"><?php echo $res['status']; ?></span>
                </div>
                <div class="details">
                    <?php if (is_array($res['details'])): ?>
                        <ul>
                            <?php foreach ($res['details'] as $name => $val): ?>
                                <li>
                                    <span class="stat-name"><?php echo htmlspecialchars($name); ?></span>
                                    <span class="stat-value <?php 
                                        if (is_array($val)) {
                                            echo $val['exists'] ? 'ok' : 'err';
                                        } else {
                                            echo $val ? 'ok' : 'err'; 
                                        }
                                    ?>">
                                        <?php 
                                            if (is_array($val)) {
                                                echo $val['exists'] ? 'Exists' : 'Missing';
                                                echo $val['writable'] ? ' (Writable)' : ' (Read-Only)';
                                            } else {
                                                echo $val ? 'Loaded / Pass' : 'Failed'; 
                                            }
                                        ?>
                                    </span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <?php echo htmlspecialchars($res['details']); ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</body>
</html>
