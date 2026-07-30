<?php
/**
 * Test schema endpoint.
 */
header('Content-Type: text/plain');

try {
    $pdo = require __DIR__ . '/../../hub/db/connection.php';
    echo "Total posts in posts table: " . $pdo->query("SELECT COUNT(*) FROM posts")->fetchColumn() . "\n";
    echo "Total posts in posts table for client 1: " . $pdo->query("SELECT COUNT(*) FROM posts WHERE client_id=1")->fetchColumn() . "\n";
    echo "Statuses in posts table: \n";
    $q = $pdo->query("SELECT status, COUNT(*) as cnt FROM posts GROUP BY status");
    while ($row = $q->fetch()) {
        echo " - {$row['status']}: {$row['cnt']}\n";
    }
} catch (Exception $e) {
    echo "Error querying posts: " . $e->getMessage() . "\n";
}
