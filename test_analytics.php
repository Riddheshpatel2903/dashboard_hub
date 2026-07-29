<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/dashboard/includes/hub_client.php';
$client_id = 1;

$platforms = ['youtube', 'facebook', 'instagram'];
foreach ($platforms as $p) {
    echo "========================================\n";
    echo "Testing hubGetAnalytics for platform: $p\n";
    try {
        $res = hubGetAnalytics($client_id, $p, 0, '2026-07-01', '2026-07-29');
        if (!empty($res['success'])) {
            echo "SUCCESS!\n";
            echo "Metrics count: " . count($res['metrics'] ?? []) . "\n";
            if (!empty($res['metrics'])) {
                echo "Sample metrics:\n";
                $sample = array_slice($res['metrics'], 0, 5);
                foreach ($sample as $m) {
                    echo "  - " . $m['metric_name'] . " (" . $m['period'] . "): " . substr(json_encode($m['value']), 0, 80) . "\n";
                }
            }
        } else {
            echo "FAILED: " . ($res['error'] ?? 'Unknown error') . "\n";
            if (!empty($res['raw'])) {
                echo "Raw Response: " . substr($res['raw'], 0, 500) . "\n";
            }
        }
    } catch (Exception $e) {
        echo "Exception: " . $e->getMessage() . "\n";
    }
}
