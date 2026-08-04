<?php
header('Content-Type: text/plain');
try {
    $pdo = new PDO(
        "mysql:host=srv2216.hstgr.io;port=3306;dbname=u689131217_dashboard_db;charset=utf8mb4",
        'u689131217_dashboard_user',
        'ao3P;k~OD+1'
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "Connection OK\n";
    echo "Server Info: " . $pdo->getAttribute(PDO::ATTR_SERVER_INFO) . "\n";
    echo "Server Version: " . $pdo->getAttribute(PDO::ATTR_SERVER_VERSION) . "\n";
    
    // Check current database
    $dbName = $pdo->query("SELECT DATABASE()")->fetchColumn();
    echo "Active DB: {$dbName}\n";

    // Show tables
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Tables:\n" . implode("\n", $tables) . "\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
