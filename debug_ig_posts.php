<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/dashboard/includes/hub_client.php';
$client_id = 1;

try {
    // Force sync to fetch fresh posts from Instagram API
    $posts = loadPlatformPosts($client_id, true);
    
    echo "Total posts returned: " . count($posts) . "\n\n";
    $fbPosts = array_filter($posts, function($p) {
        return $p['platform'] === 'facebook';
    });
    
    echo "Facebook posts count: " . count($fbPosts) . "\n\n";
    $i = 0;
    foreach ($fbPosts as $post) {
        $i++;
        echo "Facebook Post #$i:\n";
        echo "  - ID: " . $post['id'] . " (Hub ID: " . $post['hub_post_id'] . ")\n";
        echo "  - Content: " . substr($post['content'], 0, 40) . "\n";
        echo "  - Status: " . $post['status'] . "\n";
        echo "  - External ID: " . $post['external_post_id'] . "\n";
        echo "  - Views Count: " . json_encode($post['views_count']) . "\n";
        echo "  - Likes Count: " . json_encode($post['likes_count']) . "\n";
        echo "  - Comments Count: " . json_encode($post['comments_count']) . "\n";
        echo "  - Metrics: " . json_encode($post['metrics']) . "\n\n";
        if ($i >= 3) break;
    }

    $igPosts = array_filter($posts, function($p) {
        return $p['platform'] === 'instagram';
    });
    echo "Instagram posts count: " . count($igPosts) . "\n\n";
    $i = 0;
    foreach ($igPosts as $post) {
        $i++;
        echo "Instagram Post #$i:\n";
        echo "  - ID: " . $post['id'] . " (Hub ID: " . $post['hub_post_id'] . ")\n";
        echo "  - Content: " . substr($post['content'], 0, 40) . "\n";
        echo "  - Status: " . $post['status'] . "\n";
        echo "  - External ID: " . $post['external_post_id'] . "\n";
        echo "  - Views Count: " . json_encode($post['views_count']) . "\n";
        echo "  - Likes Count: " . json_encode($post['likes_count']) . "\n";
        echo "  - Comments Count: " . json_encode($post['comments_count']) . "\n";
        echo "  - Metrics: " . json_encode($post['metrics']) . "\n\n";
        if ($i >= 5) break;
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
