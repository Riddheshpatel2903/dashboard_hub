<?php
header('Content-Type: application/json');
$pdo = require __DIR__ . '/db/connection.php';

try {
    $stmt = $pdo->prepare("SELECT * FROM posts WHERE id = 93");
    $stmt->execute();
    $post = $stmt->fetch();
    echo json_encode($post, JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
