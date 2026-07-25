<?php
/**
 * Platform Analytics Endpoint.
 * Endpoint: GET /api/analytics.php
 */

require_once __DIR__ . '/authenticate_request.php'; // Defines $client_id
$pdo = require __DIR__ . '/../db/connection.php';
require_once __DIR__ . '/../utils/encryption.php';
require_once __DIR__ . '/../utils/logger.php';

require_once __DIR__ . '/../platforms/FacebookHandler.php';
require_once __DIR__ . '/../platforms/InstagramHandler.php';
require_once __DIR__ . '/../platforms/YouTubeHandler.php';
require_once __DIR__ . '/../platforms/GoogleBusinessHandler.php';

$platformInput = $_GET['platform'] ?? '';
$postId = isset($_GET['post_id']) ? (int)$_GET['post_id'] : 0;
$startDate = $_GET['start_date'] ?? null;
$endDate = $_GET['end_date'] ?? null;

if (empty($platformInput) && $postId <= 0) {
    header('Content-Type: application/json', true, 400);
    echo json_encode(['success' => false, 'error' => 'Missing platform or post_id parameter']);
    exit();
}

try {
    $platform = $platformInput;
    $externalId = null;
    $token = null;

    if ($postId > 0) {
        // Resolve post's external account / platform
        $stmt = $pdo->prepare("
            SELECT p.external_post_id, pc.platform, pc.external_account_id, pt.access_token_encrypted
            FROM posts p
            JOIN platform_connections pc ON p.platform_connection_id = pc.id
            LEFT JOIN platform_tokens pt ON pc.id = pt.platform_connection_id
            WHERE p.id = :post_id AND p.client_id = :client_id
            LIMIT 1
        ");
        $stmt->execute([
            'post_id'   => $postId,
            'client_id' => $client_id
        ]);
        $postData = $stmt->fetch();
        
        if (!$postData) {
            header('Content-Type: application/json', true, 404);
            echo json_encode(['success' => false, 'error' => 'Post not found or unauthorized']);
            exit();
        }

        $platform = $postData['platform'];
        $externalId = $postData['external_post_id'];
        $token = !empty($postData['access_token_encrypted']) ? decrypt($postData['access_token_encrypted']) : '';
    } else {
        // Get account level metrics by finding the first connection for this client + platform
        $stmt = $pdo->prepare("
            SELECT pc.external_account_id, pt.access_token_encrypted
            FROM platform_connections pc
            LEFT JOIN platform_tokens pt ON pc.id = pt.platform_connection_id
            WHERE pc.client_id = :client_id AND pc.platform = :platform AND pc.status = 'connected'
            LIMIT 1
        ");
        $stmt->execute([
            'client_id' => $client_id,
            'platform'  => $platform
        ]);
        $connData = $stmt->fetch();
        
        if (!$connData) {
            header('Content-Type: application/json', true, 404);
            echo json_encode(['success' => false, 'error' => 'No active connection found for ' . $platform]);
            exit();
        }

        $externalId = $connData['external_account_id'];
        $token = !empty($connData['access_token_encrypted']) ? decrypt($connData['access_token_encrypted']) : '';
    }

    $normalizedMetrics = [];

    // Dispatch and normalize responses
    switch ($platform) {
        case 'facebook':
            if ($postId > 0) {
                $metrics = ['post_engaged_users', 'post_reactions_by_type_total'];
                $raw = FacebookHandler::getInsights($token, $externalId, $metrics);
            } else {
                $metrics = ['page_post_engagements', 'page_views_total'];
                $raw = FacebookHandler::getInsights($token, $externalId, $metrics, 'day');
            }
            
            // Map FB metrics to standard structure
            if (!empty($raw['data'])) {
                foreach ($raw['data'] as $item) {
                    $name = $item['name'];
                    $period = $item['period'] ?? 'lifetime';
                    $val = 0;
                    if (!empty($item['values'])) {
                        $val = end($item['values'])['value'] ?? 0;
                    }
                    
                    $normalizedMetrics[] = [
                        'platform'    => 'facebook',
                        'metric_name' => $name,
                        'value'       => is_array($val) ? json_encode($val) : $val,
                        'period'      => $period
                    ];
                }
            }
            break;

        case 'instagram':
            if ($postId > 0) {
                // Post/Media specific metrics
                $metrics = ['impressions', 'reach', 'engagement', 'saved', 'video_views'];
                $raw = InstagramHandler::getInsights($token, $externalId, $metrics);
            } else {
                // Account level metrics
                $metrics = ['impressions', 'reach', 'profile_views'];
                $raw = InstagramHandler::getInsights($token, $externalId, $metrics, 'day');
            }
            
            if (!empty($raw['data'])) {
                foreach ($raw['data'] as $item) {
                    $name = $item['name'];
                    $period = $item['period'] ?? 'lifetime';
                    $val = 0;
                    if (!empty($item['values'])) {
                        $val = end($item['values'])['value'] ?? 0;
                    }
                    
                    $normalizedMetrics[] = [
                        'platform'    => 'instagram',
                        'metric_name' => $name,
                        'value'       => $val,
                        'period'      => $period
                    ];
                }
            }
            break;

        case 'youtube':
            if ($postId > 0) {
                $raw = YouTubeHandler::getVideoAnalytics($token, $externalId, $startDate, $endDate);
                
                // YouTube reports metrics in rows and columns
                if (!empty($raw['columnHeaders']) && !empty($raw['rows'])) {
                    $headers = array_column($raw['columnHeaders'], 'name');
                    foreach ($raw['rows'] as $row) {
                        foreach ($row as $colIdx => $val) {
                            $metricName = $headers[$colIdx];
                            if ($metricName !== 'video') { // Skip filter headers
                                $normalizedMetrics[] = [
                                    'platform'    => 'youtube',
                                    'metric_name' => $metricName,
                                    'value'       => $val,
                                    'period'      => 'range'
                                ];
                            }
                        }
                    }
                }
            } else {
                $raw = YouTubeHandler::getChannelStats($token, $externalId);
                if (empty($raw['items'])) {
                    $raw = YouTubeHandler::getChannelStats($token, 'mine');
                }
                if (!empty($raw['items'][0]['statistics'])) {
                    $stats = $raw['items'][0]['statistics'];
                    foreach ($stats as $name => $val) {
                        if ($name === 'hiddenSubscriberCount') {
                            continue;
                        }
                        
                        $snakeName = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $name));

                        $normalizedMetrics[] = [
                            'platform'    => 'youtube',
                            'metric_name' => $snakeName,
                            'value'       => (int)$val,
                            'period'      => 'lifetime'
                        ];
                    }
                }
            }
            break;

        case 'google_business':
            $range = [];
            if ($startDate && $endDate) {
                $range = [
                    'start_year'  => (int)date('Y', strtotime($startDate)),
                    'start_month' => (int)date('n', strtotime($startDate)),
                    'start_day'   => (int)date('j', strtotime($startDate)),
                    'end_year'    => (int)date('Y', strtotime($endDate)),
                    'end_month'   => (int)date('n', strtotime($endDate)),
                    'end_day'     => (int)date('j', strtotime($endDate))
                ];
            }
            
            $raw = GoogleBusinessHandler::getPerformanceMetrics($token, $externalId, $range);
            
            // Standardize Performance metrics
            if (!empty($raw['multiDailyMetricTimeSeries'])) {
                foreach ($raw['multiDailyMetricTimeSeries'] as $seriesList) {
                    if (!empty($seriesList['dailyMetricTimeSeries'])) {
                        foreach ($seriesList['dailyMetricTimeSeries'] as $timeSeries) {
                            $metricName = $timeSeries['dailyMetric'] ?? 'unknown_metric';
                            $totalVal = 0;
                            if (!empty($timeSeries['timeSeries']['timeSeriesValues'])) {
                                foreach ($timeSeries['timeSeries']['timeSeriesValues'] as $tsVal) {
                                    $totalVal += (int)($tsVal['value'] ?? 0);
                                }
                            }
                            
                            $normalizedMetrics[] = [
                                'platform'    => 'google_business',
                                'metric_name' => strtolower($metricName),
                                'value'       => $totalVal,
                                'period'      => 'range'
                            ];
                        }
                    }
                }
            }
            break;

        case 'linkedin':
            // Follower and post analytics require MDP.
            $normalizedMetrics[] = [
                'platform'    => 'linkedin',
                'metric_name' => 'analytics_unsupported',
                'value'       => 'Requires LinkedIn Marketing Developer Platform approval. Personal profile analytics not accessible via standard posts API.',
                'period'      => 'n/a'
            ];
            break;

        default:
            throw new Exception("Analytics not supported for platform: {$platform}");
    }

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'metrics' => $normalizedMetrics
    ]);

} catch (Exception $e) {
    log_message('error', "Analytics request failure", ['error' => $e->getMessage()]);
    header('Content-Type: application/json', true, 500);
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage()
    ]);
}
