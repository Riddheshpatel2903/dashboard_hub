<?php
/**
 * Hub Clients Management Endpoint.
 * Endpoint: POST /api/clients.php
 */

$pdo = require_once __DIR__ . '/../db/connection.php';
require_once __DIR__ . '/../utils/logger.php';

// Schema update: Add subscription columns to clients table if missing
try {
    $checkCols = $pdo->query("SHOW COLUMNS FROM clients")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('expiry_date', $checkCols, true)) {
        $pdo->exec("ALTER TABLE clients ADD COLUMN expiry_date DATETIME NULL");
    }
    if (!in_array('status', $checkCols, true)) {
        $pdo->exec("ALTER TABLE clients ADD COLUMN status ENUM('active', 'inactive') NOT NULL DEFAULT 'active'");
    }
    if (!in_array('inactive_since', $checkCols, true)) {
        $pdo->exec("ALTER TABLE clients ADD COLUMN inactive_since DATETIME NULL DEFAULT NULL");
    }
    
    // Initialize existing clients with NULL expiry_date to 1 year from their created_at date
    $pdo->exec("UPDATE clients SET expiry_date = DATE_ADD(created_at, INTERVAL 1 YEAR) WHERE expiry_date IS NULL");
} catch (Exception $schemaEx) {
    log_message('error', 'Failed to run client subscription table schema updates: ' . $schemaEx->getMessage());
}

/**
 * Hard-deletes all associated data for a client in the Hub database.
 */
function deleteClientData(PDO $pdo, int $clientId) {
    try {
        $pdo->beginTransaction();

        // 1. Delete from media_files (join with posts)
        $stmt = $pdo->prepare("SELECT id FROM posts WHERE client_id = :client_id");
        $stmt->execute(['client_id' => $clientId]);
        $postIds = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

        if (!empty($postIds)) {
            $inQuery = implode(',', array_map('intval', $postIds));
            $pdo->exec("DELETE FROM media_files WHERE post_id IN ($inQuery)");
        }

        // 2. Delete posts and platform posts
        $pdo->prepare("DELETE FROM posts WHERE client_id = :client_id")->execute(['client_id' => $clientId]);
        $pdo->prepare("DELETE FROM platform_posts WHERE client_id = :client_id")->execute(['client_id' => $clientId]);

        // 3. Delete tokens and connections
        $stmt = $pdo->prepare("SELECT id FROM platform_connections WHERE client_id = :client_id");
        $stmt->execute(['client_id' => $clientId]);
        $connIds = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

        if (!empty($connIds)) {
            $inQuery = implode(',', array_map('intval', $connIds));
            $pdo->exec("DELETE FROM platform_tokens WHERE platform_connection_id IN ($inQuery)");
        }

        $pdo->prepare("DELETE FROM platform_connections WHERE client_id = :client_id")->execute(['client_id' => $clientId]);
        
        // 4. Delete API keys, analytics cache, and the client record
        $pdo->prepare("DELETE FROM client_api_keys WHERE client_id = :client_id")->execute(['client_id' => $clientId]);
        $pdo->prepare("DELETE FROM analytics_cache WHERE client_id = :client_id")->execute(['client_id' => $clientId]);
        $pdo->prepare("DELETE FROM clients WHERE id = :client_id")->execute(['client_id' => $clientId]);

        $pdo->commit();
        log_message('info', "Successfully hard-deleted all Hub database records for client {$clientId}.");
        return true;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        log_message('error', "Failed to delete client {$clientId} data in Hub: " . $e->getMessage());
        return false;
    }
}

/**
 * Sweeps the database to mark expired subscriptions as inactive,
 * and permanently deletes inactive clients after 30 days.
 */
function processClientExpirations(PDO $pdo) {
    try {
        // 1. Mark active expired clients as inactive
        $stmt = $pdo->prepare("
            UPDATE clients 
            SET status = 'inactive', inactive_since = NOW() 
            WHERE status = 'active' AND expiry_date < NOW()
        ");
        $stmt->execute();

        // 2. Select clients inactive for more than 30 days
        $stmt = $pdo->prepare("
            SELECT id FROM clients 
            WHERE status = 'inactive' AND inactive_since < DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        $stmt->execute();
        $expiredClientIds = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

        foreach ($expiredClientIds as $clientId) {
            deleteClientData($pdo, (int)$clientId);
        }
    } catch (Exception $e) {
        log_message('error', "Error processing client expirations: " . $e->getMessage());
    }
}

// Automatically process client expirations and purging
processClientExpirations($pdo);

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
    
    $action = $input['action'] ?? 'create';
    $targetClientId = isset($input['client_id']) ? (int)$input['client_id'] : 0;

    if ($action === 'create' || $action === 'update') {
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
    }

    if ($action === 'delete') {
        if ($targetClientId <= 0) {
            header('Content-Type: application/json', true, 400);
            echo json_encode(['success' => false, 'error' => 'Missing or invalid client_id for delete action.']);
            exit();
        }
        $ok = deleteClientData($pdo, $targetClientId);
        if ($ok) {
            header('Content-Type: application/json', true, 200);
            echo json_encode(['success' => true, 'message' => 'Client account and all associated data permanently deleted.']);
        } else {
            header('Content-Type: application/json', true, 500);
            echo json_encode(['success' => false, 'error' => 'Failed to delete client data from Hub.']);
        }
        exit();
    }

    if ($action === 'extend') {
        if ($targetClientId <= 0) {
            header('Content-Type: application/json', true, 400);
            echo json_encode(['success' => false, 'error' => 'Missing or invalid client_id for extend action.']);
            exit();
        }
        try {
            $stmt = $pdo->prepare("
                UPDATE clients 
                SET expiry_date = DATE_ADD(IFNULL(expiry_date, NOW()), INTERVAL 1 YEAR), status = 'active', inactive_since = NULL
                WHERE id = :client_id
            ");
            $stmt->execute(['client_id' => $targetClientId]);
            
            // Fetch updated expiry date
            $stmtExpiry = $pdo->prepare("SELECT expiry_date FROM clients WHERE id = :client_id LIMIT 1");
            $stmtExpiry->execute(['client_id' => $targetClientId]);
            $newExpiry = $stmtExpiry->fetchColumn();

            log_message('info', "Extended subscription plan for client {$targetClientId} by 1 year", ['expiry_date' => $newExpiry]);
            header('Content-Type: application/json', true, 200);
            echo json_encode([
                'success' => true, 
                'message' => 'Subscription plan extended successfully.',
                'expiry_date' => $newExpiry
            ]);
        } catch (Exception $e) {
            header('Content-Type: application/json', true, 500);
            echo json_encode(['success' => false, 'error' => 'Plan extension failed: ' . $e->getMessage()]);
        }
        exit();
    }

    if ($action === 'status') {
        $newStatus = $input['status'] ?? '';
        if ($targetClientId <= 0 || !in_array($newStatus, ['active', 'inactive'], true)) {
            header('Content-Type: application/json', true, 400);
            echo json_encode(['success' => false, 'error' => 'Missing or invalid client_id or status value.']);
            exit();
        }
        try {
            $inactiveSince = ($newStatus === 'inactive') ? date('Y-m-d H:i:s') : null;
            $stmt = $pdo->prepare("
                UPDATE clients 
                SET status = :status, inactive_since = :inactive_since
                WHERE id = :client_id
            ");
            $stmt->execute([
                'status' => $newStatus,
                'inactive_since' => $inactiveSince,
                'client_id' => $targetClientId
            ]);
            log_message('info', "Updated client {$targetClientId} status to {$newStatus}");
            header('Content-Type: application/json', true, 200);
            echo json_encode(['success' => true, 'message' => "Client status updated to {$newStatus} successfully."]);
        } catch (Exception $e) {
            header('Content-Type: application/json', true, 500);
            echo json_encode(['success' => false, 'error' => 'Status update failed: ' . $e->getMessage()]);
        }
        exit();
    }

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
            INSERT INTO clients (name, website_url, expiry_date, status, inactive_since)
            VALUES (:name, :website_url, DATE_ADD(NOW(), INTERVAL 1 YEAR), 'active', NULL)
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
                SELECT c.id, c.name, c.website_url, c.created_at, c.status, c.expiry_date, c.inactive_since, COUNT(pc.id) as connection_count
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
        $stmt = $pdo->prepare("SELECT id, name, website_url, created_at, status, expiry_date, inactive_since FROM clients WHERE id = :client_id LIMIT 1");
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
                'created_at'  => $client['created_at'],
                'status'      => $client['status'],
                'expiry_date' => $client['expiry_date'],
                'inactive_since' => $client['inactive_since']
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
