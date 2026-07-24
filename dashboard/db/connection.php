<?php
/**
 * Dashboard PDO Database Connection.
 * Decoupled from the Hub database connection.
 */

require_once __DIR__ . '/../config/config.php';

if (isset($GLOBALS['dashboard_pdo']) && $GLOBALS['dashboard_pdo'] instanceof PDO) {
    return $GLOBALS['dashboard_pdo'];
}

try {
    $dsn = sprintf(
        "mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4",
        DASHBOARD_DB_HOST,
        DASHBOARD_DB_PORT,
        DASHBOARD_DB_NAME
    );

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    $pdo = new PDO($dsn, DASHBOARD_DB_USER, DASHBOARD_DB_PASS, $options);
    $GLOBALS['dashboard_pdo'] = $pdo;
    
    return $pdo;
} catch (PDOException $e) {
    $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
        || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

    if ($isAjax && !headers_sent()) {
        header('Content-Type: application/json', true, 500);
        echo json_encode([
            'success' => false,
            'error'   => 'Dashboard database connection failure',
            'details' => $e->getMessage()
        ]);
        exit();
    } else {
        throw new PDOException($e->getMessage(), (int)$e->getCode());
    }
}
