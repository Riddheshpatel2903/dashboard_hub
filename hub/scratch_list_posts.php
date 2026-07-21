<?php
header('Content-Type: application/json');
$pdo = require __DIR__ . '/db/connection.php';

try {
    $results = [];
    
    $results['posts_count'] = $pdo->query("SELECT count(*) FROM posts")->fetchColumn();
    $results['connections_count'] = $pdo->query("SELECT count(*) FROM platform_connections")->fetchColumn();
    $results['tokens_count'] = $pdo->query("SELECT count(*) FROM platform_tokens")->fetchColumn();
    
    echo json_encode($results, JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
