<?php
/**
 * DB Connection.
 * Returns a shared PDO instance configured with error exceptions.
 */

require_once __DIR__ . '/../config/config.php';

if (isset($GLOBALS['hub_pdo']) && $GLOBALS['hub_pdo'] instanceof PDO) {
    return $GLOBALS['hub_pdo'];
}

try {
    $dsn = sprintf(
        "mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4",
        DB_HOST,
        DB_PORT,
        DB_NAME
    );
    
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    $pdo->exec("SET time_zone = '+05:30'");
    $GLOBALS['hub_pdo'] = $pdo;
    
    return $pdo;
} catch (PDOException $e) {
    // Return structured JSON error if included in an API or web response context,
    // otherwise throw the database connection exception.
    if (PHP_SAPI !== 'cli' && !headers_sent()) {
        header('Content-Type: application/json', true, 500);
        echo json_encode([
            'success' => false,
            'error'   => 'Database connection failure',
            'details' => $e->getMessage()
        ]);
        exit();
    } else {
        throw new PDOException($e->getMessage(), (int)$e->getCode());
    }
}
