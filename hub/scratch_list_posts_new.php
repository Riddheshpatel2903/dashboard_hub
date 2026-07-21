<?php
header('Content-Type: application/json');
$pdo = require __DIR__ . '/db/connection.php';

try {
    $results = [];
    
    $results['posts_count'] = $pdo->query("SELECT count(*) FROM posts")->fetchColumn();
    $results['connections_count'] = $pdo->query("SELECT count(*) FROM platform_connections")->fetchColumn();
    $results['tokens_count'] = $pdo->query("SELECT count(*) FROM platform_tokens")->fetchColumn();
    
    // Also fetch last 3 posts in hub db
    $stmt = $pdo->query("SELECT * FROM posts ORDER BY id DESC LIMIT 3");
    $results['last_posts'] = $stmt->fetchAll();
    
    echo json_encode($results, JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
