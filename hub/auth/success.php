<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Connection Successful</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f0f2f5; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .card { background: white; padding: 2.5rem; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); text-align: center; max-width: 400px; width: 100%; }
        h2 { color: #2e7d32; margin-bottom: 1rem; }
        p { color: #606770; font-size: 0.95rem; margin-bottom: 2rem; }
        .platform { font-weight: bold; text-transform: uppercase; color: #1565c0; }
    </style>
</head>
<body>
    <div class="card">
        <h2>Connection Successful!</h2>
        <p>Your <span class="platform"><?php echo htmlspecialchars($_GET['platform'] ?? 'platform'); ?></span> account has been successfully linked to the Hub.</p>
        <a href="../../dashboard/pages/connections.php" style="display:inline-block; background:#2e7d32; color:white; border:none; padding:0.6rem 1.2rem; font-size:0.95rem; font-weight:bold; border-radius:6px; cursor:pointer; text-decoration:none; transition:background 0.2s; margin-bottom: 1rem;">Return to Dashboard</a>
        <p style="font-size:0.8rem; color:#888; margin-top: 0;">You can close this window now.</p>
    </div>
</body>
</html>
