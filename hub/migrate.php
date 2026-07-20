<?php
header('Content-Type: text/plain');
$pdo = require __DIR__ . '/db/connection.php';

try {
    // 1. Delete duplicate tokens, keeping only the newest one (highest ID) for each connection
    $pdo->exec("
        DELETE t1 FROM platform_tokens t1
        INNER JOIN platform_tokens t2 
        WHERE t1.id < t2.id 
          AND t1.platform_connection_id = t2.platform_connection_id
    ");
    echo "Cleaned up duplicate platform tokens.\n";

    // 2. Add unique key to platform_tokens table if it doesn't already exist
    $stmt = $pdo->query("SHOW INDEX FROM platform_tokens WHERE Key_name = 'uq_platform_connection'");
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE platform_tokens ADD UNIQUE KEY uq_platform_connection (platform_connection_id)");
        echo "Added unique key uq_platform_connection to platform_tokens.\n";
    } else {
        echo "Unique key uq_platform_connection already exists.\n";
    }

    // 3. Delete duplicate connections for the same client and platform (keeping only the newest one)
    $pdo->exec("
        DELETE c1 FROM platform_connections c1
        INNER JOIN platform_connections c2
        WHERE c1.id < c2.id
          AND c1.client_id = c2.client_id
          AND c1.platform = c2.platform
    ");
    echo "Cleaned up duplicate platform connections.\n";

} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
