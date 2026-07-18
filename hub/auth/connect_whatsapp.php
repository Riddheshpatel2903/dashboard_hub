<?php
/**
 * WhatsApp Embedded Signup handler.
 * Serves the Embedded Signup JS interface and processes the resulting OAuth token exchange.
 */

session_start();
$pdo = require_once __DIR__ . '/../db/connection.php';
$platforms = require_once __DIR__ . '/../config/platforms.php';
require_once __DIR__ . '/../utils/encryption.php';
require_once __DIR__ . '/../utils/logger.php';

$fbConfig = $platforms['facebook']; // WhatsApp uses the same FB App ID/Secret

// Handle code exchange when JavaScript posts the token/code
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true);
    
    $code = $input['code'] ?? '';
    $clientId = isset($input['client_id']) ? (int)$input['client_id'] : 0;
    
    if (empty($code) || $clientId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing code or client_id']);
        exit();
    }
    
    // 1. Exchange the signup code for a long-lived access token
    $tokenUrl = sprintf(
        "https://graph.facebook.com/%s/oauth/access_token?client_id=%s&client_secret=%s&code=%s",
        $fbConfig['graph_api_version'],
        $fbConfig['app_id'],
        $fbConfig['app_secret'],
        urlencode($code)
    );
    
    $ch = curl_init($tokenUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $tokenData = json_decode($response, true);
    if ($httpCode !== 200 || empty($tokenData['access_token'])) {
        log_message('error', "WhatsApp token exchange failed", ['response' => $response]);
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Token exchange failed']);
        exit();
    }
    
    $accessToken = $tokenData['access_token'];
    
    // 2. Fetch WhatsApp Business Accounts (WABA)
    $wabaUrl = sprintf(
        "https://graph.facebook.com/%s/me/whatsapp_business_accounts?access_token=%s",
        $fbConfig['graph_api_version'],
        urlencode($accessToken)
    );
    
    $ch = curl_init($wabaUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $wabaResponse = curl_exec($ch);
    $wabaHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $wabaData = json_decode($wabaResponse, true);
    if ($wabaHttpCode !== 200 || !isset($wabaData['data'])) {
        log_message('error', "WhatsApp fetch WABA failed", ['response' => $wabaResponse]);
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Failed to retrieve WABA list']);
        exit();
    }
    
    $phoneNumbersRegistered = 0;
    
    try {
        $pdo->beginTransaction();
        
        foreach ($wabaData['data'] as $waba) {
            $wabaId = $waba['id'];
            
            // 3. Fetch phone numbers for each WABA
            $phoneUrl = sprintf(
                "https://graph.facebook.com/%s/%s/phone_numbers?access_token=%s",
                $fbConfig['graph_api_version'],
                $wabaId,
                urlencode($accessToken)
            );
            
            $ch = curl_init($phoneUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $phoneResponse = curl_exec($ch);
            $phoneHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            $phoneData = json_decode($phoneResponse, true);
            if ($phoneHttpCode === 200 && !empty($phoneData['data'])) {
                foreach ($phoneData['data'] as $phone) {
                    $phoneNumberId = $phone['id']; // Used for sending messages and webhook checking
                    
                    // A. Insert or update platform connection (using phone_number_id as external_account_id)
                    $stmt = $pdo->prepare("
                        INSERT INTO platform_connections (client_id, platform, external_account_id, status)
                        VALUES (:client_id, 'whatsapp', :external_id, 'connected')
                        ON DUPLICATE KEY UPDATE status = 'connected', connected_at = CURRENT_TIMESTAMP
                    ");
                    $stmt->execute([
                        'client_id'   => $clientId,
                        'external_id' => $phoneNumberId
                    ]);
                    
                    $connectionId = $pdo->lastInsertId();
                    if (!$connectionId) {
                        $stmt = $pdo->prepare("
                            SELECT id FROM platform_connections 
                            WHERE client_id = :client_id AND platform = 'whatsapp' AND external_account_id = :external_id
                        ");
                        $stmt->execute([
                            'client_id'   => $clientId,
                            'external_id' => $phoneNumberId
                        ]);
                        $connectionId = $stmt->fetchColumn();
                    }
                    
                    // B. Store encrypted access token
                    $encryptedToken = encrypt($accessToken);
                    $stmt = $pdo->prepare("
                        INSERT INTO platform_tokens (platform_connection_id, access_token_encrypted, expires_at)
                        VALUES (:connection_id, :token, :expires_at)
                        ON DUPLICATE KEY UPDATE access_token_encrypted = VALUES(access_token_encrypted), expires_at = VALUES(expires_at)
                    ");
                    $stmt->execute([
                        'connection_id' => $connectionId,
                        'token'         => $encryptedToken,
                        'expires_at'    => null // WhatsApp system user / WABA tokens generally do not expire
                    ]);
                    
                    $phoneNumbersRegistered++;
                }
            }
        }
        
        $pdo->commit();
        log_message('info', "WhatsApp Embedded Signup successful", ['client_id' => $clientId, 'numbers_registered' => $phoneNumbersRegistered]);
        echo json_encode(['success' => true, 'registered_count' => $phoneNumbersRegistered]);
        exit();
        
    } catch (Exception $e) {
        $pdo->rollBack();
        log_message('error', "WhatsApp database transaction failed", ['exception' => $e->getMessage()]);
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Database transaction failure']);
        exit();
    }
}

// GET Request: Render Meta's Embedded Signup integration page
$clientId = isset($_GET['client_id']) ? (int)$_GET['client_id'] : 0;
if ($clientId <= 0) {
    http_response_code(400);
    echo "Error: Missing or invalid client_id parameter.";
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>WhatsApp Embedded Signup</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f0f2f5; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .card { background: white; padding: 2.5rem; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); text-align: center; max-width: 400px; width: 100%; }
        h2 { color: #128c7e; margin-bottom: 1rem; }
        p { color: #606770; font-size: 0.95rem; margin-bottom: 2rem; }
        .btn-whatsapp { background: #25D366; color: white; border: none; padding: 0.8rem 1.5rem; font-size: 1rem; font-weight: bold; border-radius: 6px; cursor: pointer; transition: background 0.2s; }
        .btn-whatsapp:hover { background: #128c7e; }
    </style>
</head>
<body>
    <div class="card">
        <h2>Connect WhatsApp</h2>
        <p>Click below to log in with Facebook and link your WhatsApp Business Account (WABA) to the Hub.</p>
        
        <!-- Meta Embedded Signup Button Trigger -->
        <button class="btn-whatsapp" onclick="launchWhatsAppSignup()">Connect with WhatsApp</button>
    </div>

    <!-- Meta JS SDK Hook -->
    <script>
        window.fbAsyncInit = function() {
            FB.init({
                appId      : '<?php echo htmlspecialchars($fbConfig['app_id']); ?>',
                cookie     : true,
                xfbml      : true,
                version    : '<?php echo htmlspecialchars($fbConfig['graph_api_version']); ?>'
            });
        };

        (function(d, s, id){
            var js, fjs = d.getElementsByTagName(s)[0];
            if (d.getElementById(id)) {return;}
            js = d.createElement(s); js.id = id;
            js.src = "https://connect.facebook.net/en_US/sdk.js";
            fjs.parentNode.insertBefore(js, fjs);
        }(document, 'script', 'facebook-jssdk'));

        function launchWhatsAppSignup() {
            // Meta Embedded Signup SDK window
            FB.login(function(response) {
                if (response.authResponse) {
                    const code = response.authResponse.code;
                    if (code) {
                        // Send the code to our backend for exchange
                        fetch(window.location.href, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                                code: code,
                                client_id: <?php echo $clientId; ?>
                            })
                        })
                        .then(res => res.json())
                        .then(data => {
                            if (data.success) {
                                window.location.href = "<?php echo HUB_BASE_URL; ?>/auth/success.php?platform=whatsapp";
                            } else {
                                alert("Failed to exchange WhatsApp token: " + data.error);
                            }
                        })
                        .catch(err => {
                            console.error(err);
                            alert("Server network communication error");
                        });
                    } else {
                        alert("Access token code was not returned by Meta.");
                    }
                } else {
                    alert("User cancelled login or did not fully authorize.");
                }
            }, {
                scope: 'whatsapp_business_management,whatsapp_business_messaging',
                extras: {
                    feature: 'whatsapp_embedded_signup'
                }
            });
        }
    </script>
</body>
</html>
