<?php
/**
 * Dashboard → Hub Sync Tool.
 * Reads all clients + their hub API keys from the local dashboard DB
 * and registers them into the production hub DB via the sync_client endpoint.
 *
 * Run this once from: http://localhost/dashboard_hub/dashboard/admin/sync_hub.php
 * DELETE or restrict access after use.
 */

require_once __DIR__ . '/../includes/role_check.php'; // Must be admin
require_once __DIR__ . '/../config/config.php';
$pdo = require_once __DIR__ . '/../db/connection.php';

$results = [];
$errors  = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 1. Fetch all clients + their hub API keys from local dashboard DB
    $rows = $pdo->query("
        SELECT c.id AS client_id, c.name, c.website_url, k.hub_api_key
        FROM clients c
        JOIN client_hub_keys k ON k.client_id = c.id
    ")->fetchAll();

    if (empty($rows)) {
        $errors[] = "No clients with hub API keys found in local dashboard DB.";
    }

    $syncUrl = 'https://rbfitness.in/new-site/hub/api/sync_client.php';

    foreach ($rows as $row) {
        $payload = json_encode([
            'client_id'   => (int)$row['client_id'],
            'client_name' => $row['name'],
            'website_url' => $row['website_url'] ?? '',
            'api_key'     => $row['hub_api_key'],
        ]);

        $ch = curl_init($syncUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'X-Admin-Key: ' . HUB_ADMIN_MASTER_KEY,
            ],
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        $decoded = json_decode($response, true);

        if ($curlError) {
            $errors[] = "Client {$row['client_id']} ({$row['name']}): cURL error — $curlError";
        } elseif ($httpCode === 200 && !empty($decoded['success'])) {
            $results[] = "✅ Client {$row['client_id']} ({$row['name']}): synced successfully";
        } else {
            $errMsg = $decoded['error'] ?? $response;
            $errors[] = "❌ Client {$row['client_id']} ({$row['name']}): HTTP $httpCode — $errMsg";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Sync Clients to Hub</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #0b0f19; color: #f3f4f6; padding: 2rem; }
        .card { max-width: 640px; margin: 2rem auto; background: #111827; border: 1px solid #1f2937; border-radius: 12px; padding: 2rem; }
        h2 { color: #60a5fa; margin-top: 0; }
        .info { background: rgba(59,130,246,0.1); border: 1px solid #3b82f6; border-radius: 6px; padding: 1rem; margin-bottom: 1.5rem; font-size: 0.9rem; color: #93c5fd; }
        .result { padding: 0.5rem 0; font-size: 0.9rem; }
        .ok   { color: #34d399; }
        .err  { color: #f87171; }
        .btn  { background: #2563eb; color: #fff; border: none; border-radius: 6px; padding: 0.75rem 1.5rem; font-weight: 600; cursor: pointer; font-size: 1rem; }
        .btn:hover { background: #1d4ed8; }
    </style>
</head>
<body>
<div class="card">
    <h2>🔗 Sync Local Clients → Production Hub</h2>
    <div class="info">
        This tool reads all clients and their API keys from your <strong>local dashboard DB</strong>
        and registers them into the <strong>production hub DB</strong> at
        <code>rbfitness.in/new-site/hub</code>.<br><br>
        Run this <strong>once</strong> to fix the 500 errors after pointing the dashboard to the production hub.
    </div>

    <?php if (!empty($results) || !empty($errors)): ?>
        <h3 style="color:#d1d5db;">Sync Results</h3>
        <?php foreach ($results as $r): ?>
            <div class="result ok"><?= htmlspecialchars($r) ?></div>
        <?php endforeach; ?>
        <?php foreach ($errors as $e): ?>
            <div class="result err"><?= htmlspecialchars($e) ?></div>
        <?php endforeach; ?>
        <br>
        <?php if (empty($errors)): ?>
            <div class="result ok" style="font-weight:bold;">
                ✅ All clients synced! The dashboard now points to the production hub correctly.
            </div>
        <?php endif; ?>
    <?php else: ?>
        <form method="POST">
            <p style="color:#9ca3af; font-size:0.9rem;">
                Hub URL: <strong><?= htmlspecialchars(HUB_BASE_URL) ?></strong><br>
                Admin key: <strong><?= substr(HUB_ADMIN_MASTER_KEY, 0, 6) ?>...</strong>
            </p>
            <button class="btn" type="submit">Run Sync Now</button>
        </form>
    <?php endif; ?>
</div>
</body>
</html>
