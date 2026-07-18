<?php
/**
 * Hub Clients Management Endpoint.
 * Endpoint: POST /api/clients.php
 */

$pdo = require_once __DIR__ . '/../db/connection.php';
require_once __DIR__ . '/../utils/logger.php';

// Verify Admin Master Key
$headers = [];
if (function_exists('getallheaders')) {
    $headers = getallheaders();
} else {
    foreach ($_SERVER as $name => $value) {
        if (substr($name, 0, 5) == 'HTTP_') {
            $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
        }
    }
}

$adminKey = $headers['X-Hub-Admin-Key'] ?? $_SERVER['HTTP_X_HUB_ADMIN_KEY'] ?? null;

if (empty($adminKey) || $adminKey !== HUB_ADMIN_MASTER_KEY) {
    header('Content-Type: application/json', true, 401);
    echo json_encode([
        'success' => false,
        'error'   => 'Unauthorized: Invalid Admin Master Key'
    ]);
    log_message('warning', 'Admin Clients API request rejected: invalid master key');
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    
    $name = $input['name'] ?? '';
    $websiteUrl = $input['website_url'] ?? '';

    if (empty($name) || empty($websiteUrl)) {
        header('Content-Type: application/json', true, 400);
        echo json_encode([
            'success' => false,
            'error'   => 'Missing required fields: name, website_url'
        ]);
        exit();
    }

    $action = $input['action'] ?? 'create';
    $targetClientId = isset($input['client_id']) ? (int)$input['client_id'] : 0;

    if ($action === 'update') {
        if ($targetClientId <= 0) {
            header('Content-Type: application/json', true, 400);
            echo json_encode(['success' => false, 'error' => 'Missing or invalid client_id for update action.']);
            exit();
        }
        try {
            $stmt = $pdo->prepare("
                UPDATE clients SET name = :name, website_url = :website_url
                WHERE id = :client_id
            ");
            $stmt->execute([
                'name'        => $name,
                'website_url' => $websiteUrl,
                'client_id'   => $targetClientId
            ]);
            log_message('info', "Updated client profile details in Hub", ['client_id' => $targetClientId]);
            header('Content-Type: application/json', true, 200);
            echo json_encode(['success' => true, 'message' => 'Client profile updated successfully.']);
            exit();
        } catch (Exception $e) {
            header('Content-Type: application/json', true, 500);
            echo json_encode([
                'success' => false,
                'error'   => 'Database update failed: ' . $e->getMessage()
            ]);
            exit();
        }
    }

    try {
        $pdo->beginTransaction();

        // 1. Insert new client
        $stmt = $pdo->prepare("
            INSERT INTO clients (name, website_url)
            VALUES (:name, :website_url)
        ");
        $stmt->execute([
            'name'        => $name,
            'website_url' => $websiteUrl
        ]);
        $clientId = $pdo->lastInsertId();

        // 2. Generate random 64-character API Key
        $apiKey = bin2hex(random_bytes(32));

        // 3. Insert client API key
        $stmt = $pdo->prepare("
            INSERT INTO client_api_keys (client_id, api_key)
            VALUES (:client_id, :api_key)
        ");
        $stmt->execute([
            'client_id' => $clientId,
            'api_key'   => $apiKey
        ]);

        $pdo->commit();
        log_message('info', "Created new Hub client via Dashboard Admin API", ['client_id' => $clientId, 'name' => $name]);

        header('Content-Type: application/json', true, 201);
        echo json_encode([
            'success'    => true,
            'client_id'  => (int)$clientId,
            'api_key'    => $apiKey,
            'name'       => $name,
            'website_url'=> $websiteUrl
        ]);

    } catch (Exception $e) {
        $pdo->rollBack();
        log_message('error', "Failed to create client in Hub database", ['exception' => $e->getMessage()]);
        header('Content-Type: application/json', true, 500);
        echo json_encode([
            'success' => false,
            'error'   => 'Database transaction error: ' . $e->getMessage()
        ]);
    }
    exit();
}

if ($method === 'GET') {
    $targetClientId = isset($_GET['client_id']) ? (int)$_GET['client_id'] : 0;
    
    if ($targetClientId <= 0) {
        try {
            $stmt = $pdo->prepare("
                SELECT c.id, c.name, c.website_url, c.created_at, COUNT(pc.id) as connection_count
                FROM clients c
                LEFT JOIN platform_connections pc ON c.id = pc.client_id AND pc.status = 'connected'
                GROUP BY c.id
                ORDER BY c.created_at DESC
            ");
            $stmt->execute();
            $clientsList = $stmt->fetchAll();
            
            header('Content-Type: application/json', true, 200);
            echo json_encode([
                'success' => true,
                'clients' => $clientsList
            ]);
            exit();
        } catch (Exception $e) {
            header('Content-Type: application/json', true, 500);
            echo json_encode(['success' => false, 'error' => 'Database list query error: ' . $e->getMessage()]);
            exit();
        }
    }
    
    try {
        $stmt = $pdo->prepare("SELECT id, name, website_url, created_at FROM clients WHERE id = :client_id LIMIT 1");
        $stmt->execute(['client_id' => $targetClientId]);
        $client = $stmt->fetch();
        
        if (!$client) {
            header('Content-Type: application/json', true, 404);
            echo json_encode(['success' => false, 'error' => 'Client not found.']);
            exit();
        }
        
        header('Content-Type: application/json', true, 200);
        echo json_encode([
            'success' => true,
            'client'  => [
                'id'          => (int)$client['id'],
                'name'        => $client['name'],
                'website_url' => $client['website_url'],
                'created_at'  => $client['created_at']
            ]
        ]);
        exit();
    } catch (Exception $e) {
        header('Content-Type: application/json', true, 500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit();
    }
}

header('Content-Type: application/json', true, 405);
echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
exit();
