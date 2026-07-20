<?php
header('Content-Type: text/plain');
ini_set('display_errors', 1);
error_reporting(E_ALL);

$pdo = require __DIR__ . '/../db/connection.php';
$client_id = 1;
$stmt = $pdo->prepare("SELECT COUNT(*) FROM posts_cache WHERE client_id = :client_id");
$stmt->execute(['client_id' => $client_id]);
$count = $stmt->fetchColumn();
echo "Post count for client 1: {$count}\n";

$stmt = $pdo->query("SELECT DISTINCT platform, status FROM posts_cache");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
