<?php
require_once __DIR__ . '/StorageService.php';
$res = StorageService::cleanOrphanUploads();
header('Content-Type: application/json');
echo json_encode($res, JSON_PRETTY_PRINT);
@unlink(__FILE__); // Self-destruct after running!
