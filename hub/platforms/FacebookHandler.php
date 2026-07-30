<?php
/**
 * Facebook Graph API Handler.
 */

class FacebookHandler {

    private static $version = null;

    private static function initVersion() {
        if (self::$version === null) {
            $platforms = require __DIR__ . '/../config/platforms.php';
            self::$version = $platforms['facebook']['graph_api_version'] ?? 'v22.0';
        }
    }

    /**
     * Publishes a post to a Facebook Page.
     * Can handle text-only, photo, or video posts.
     *
     * @param string $token     Page access token
     * @param string $pageId    Facebook Page ID
     * @param string $content   Text content / caption
     * @param string $mediaUrl  Cloudflare CDN url of the media file (optional)
     * @return array            Contains response array (including platform post 'id')
     * @throws Exception
     */
    public static function publishPost($token, $pageId, $content, $mediaUrl = null, $localFilePath = null) {
        self::initVersion();
        $endpoint = "https://graph.facebook.com/" . self::$version . "/{$pageId}/";
        $payload = ['access_token' => $token];

        if ($mediaUrl) {
            $ext = strtolower(pathinfo(parse_url($mediaUrl, PHP_URL_PATH), PATHINFO_EXTENSION));
            $isVid = in_array($ext, ['mp4', 'mov', 'avi', 'mpeg']);
            
            if ($isVid) {
                if ($localFilePath && file_exists($localFilePath)) {
                    $endpoint = "https://graph-video.facebook.com/" . self::$version . "/{$pageId}/videos";
                    $payload['source'] = new CURLFile($localFilePath);
                } else {
                    $endpoint .= "videos";
                    $payload['file_url'] = $mediaUrl;
                }
                $payload['description'] = $content;
            } else {
                if ($localFilePath && file_exists($localFilePath)) {
                    $endpoint = "https://graph.facebook.com/" . self::$version . "/{$pageId}/photos";
                    $payload['source'] = new CURLFile($localFilePath);
                } else {
                    $endpoint .= "photos";
                    $payload['url'] = $mediaUrl;
                }
                $payload['message'] = $content;
            }
        } else {
            $endpoint .= "feed";
            $payload['message'] = $content;
        }

        return self::executeRequest('POST', $endpoint, $payload);
    }

    /**
     * Edits an existing Facebook Page post.
     *
     * @param string $token      Page access token
     * @param string $postId     Facebook Post ID (external)
     * @param string $newContent New text content
     * @return array
     * @throws Exception
     */
    public static function editPost($token, $postId, $newContent) {
        self::initVersion();
        $endpoint = "https://graph.facebook.com/" . self::$version . "/{$postId}";
        $payload = [
            'access_token' => $token,
            'message'      => $newContent
        ];
        return self::executeRequest('POST', $endpoint, $payload);
    }

    /**
     * Deletes a Facebook Page post.
     *
     * @param string $token  Page access token
     * @param string $postId Facebook Post ID (external)
     * @return array
     * @throws Exception
     */
    public static function deletePost($token, $postId) {
        self::initVersion();
        $endpoint = "https://graph.facebook.com/" . self::$version . "/{$postId}?access_token=" . urlencode($token);
        return self::executeRequest('DELETE', $endpoint);
    }

    /**
     * Retrieves engagement/performance insights for a Facebook Page or Post.
     *
     * @param string $token   Page access token
     * @param string $targetId Page ID or Post ID
     * @param array  $metrics Array of metric names
     * @return array
     * @throws Exception
     */
    public static function getInsights($token, $targetId, array $metrics, $period = null) {
        self::initVersion();
        $urlParams = [
            'metric'       => implode(',', $metrics),
            'access_token' => $token
        ];
        if ($period) {
            $urlParams['period'] = $period;
        }
        $endpoint = sprintf(
            "https://graph.facebook.com/%s/%s/insights?%s",
            self::$version,
            $targetId,
            http_build_query($urlParams)
        );
        return self::executeRequest('GET', $endpoint);
    }

    /**
     * Retrieves comments left on a Facebook Post.
     *
     * @param string $token  Page access token
     * @param string $postId Facebook Post ID (external)
     * @return array
     * @throws Exception
     */
    public static function getComments($token, $postId) {
        self::initVersion();
        $endpoint = sprintf(
            "https://graph.facebook.com/%s/%s/comments?access_token=%s",
            self::$version,
            $postId,
            urlencode($token)
        );
        return self::executeRequest('GET', $endpoint);
    }

    /**
     * Reply to an existing comment on a Facebook Post.
     *
     * @param string $token     Page access token
     * @param string $commentId Facebook Comment ID
     * @param string $reply     Reply text content
     * @return array
     * @throws Exception
     */
    public static function replyToComment($token, $commentId, $reply) {
        self::initVersion();
        $endpoint = "https://graph.facebook.com/" . self::$version . "/{$commentId}/comments";
        $payload = [
            'access_token' => $token,
            'message'      => $reply
        ];
        return self::executeRequest('POST', $endpoint, $payload);
    }

    /**
     * Retrieves Facebook Page profile info (followers count, fans count).
     */
    public static function getAccountInfo($token, $pageId) {
        self::initVersion();
        $endpoint = sprintf(
            "https://graph.facebook.com/%s/%s?fields=followers_count,fan_count,name&access_token=%s",
            self::$version,
            urlencode($pageId),
            urlencode($token)
        );
        return self::executeRequest('GET', $endpoint);
    }

    /**
     * Retrieves basic post details including likes/comments/shares counts.
     *
     * @param string $token Page access token
     * @param string $postId Facebook Post ID
     * @param array  $fields Fields to request (comma separated will be joined)
     * @return array
     * @throws Exception
     */
    public static function getPostDetails($token, $postId, array $fields = []) {
        self::initVersion();
        $fieldsStr = !empty($fields) ? implode(',', $fields) : 'id,message,shares,attachments,full_picture,created_time,permalink_url,likes.summary(true).limit(0),comments.summary(true).limit(0)';
        $endpoint = sprintf(
            "https://graph.facebook.com/%s/%s?fields=%s&access_token=%s",
            self::$version,
            $postId,
            $fieldsStr,
            urlencode($token)
        );
        return self::executeRequest('GET', $endpoint);
    }

    /**
     * Fetch engagement counts (likes, comments, shares) for a post.
     *
     * @param string $token Page access token
     * @param string $postId Facebook Post ID
     * @return array
     * @throws Exception
     */
    public static function getEngagementCounts($token, $postId) {
        self::initVersion();
        $endpoint = sprintf(
            "https://graph.facebook.com/%s/%s?fields=likes.summary(true).limit(0),comments.summary(true).limit(0),shares&access_token=%s",
            self::$version,
            $postId,
            urlencode($token)
        );
        $response = self::executeRequest('GET', $endpoint);
        return [
            'likes' => $response['likes']['summary']['total_count'] ?? 0,
            'comments' => $response['comments']['summary']['total_count'] ?? 0,
            'shares' => $response['shares']['count'] ?? 0,
        ];
    }

    /**
     * Retrieves recent posts from Page feed with basic interaction stats.
     * Supports cursor pagination to retrieve older posts when needed.
     */
    public static function getRecentPosts($token, $pageId, $limit = 50) {
        self::initVersion();
        $unlimited = ($limit <= 0);
        if (!$unlimited) {
            $limit = min(500, max(1, $limit));
        }
        $fields = 'id,message,created_time,attachments,full_picture,permalink_url,shares,likes.summary(true).limit(0),comments.summary(true).limit(0)';
        $pageSize = $unlimited ? 100 : min($limit, 100);
        $pageUrl = "https://graph.facebook.com/" . self::$version . "/me/posts?fields={$fields}&limit={$pageSize}&access_token=" . urlencode($token);
        $allData = [];
        $maxPages = 50;
        $isFallbackMode = false;

        while ($pageUrl && ($unlimited || count($allData) < $limit) && $maxPages-- > 0) {
            try {
                $raw = self::executeRequest('GET', $pageUrl);
            } catch (Exception $e) {
                if (!$isFallbackMode && (strpos($e->getMessage(), 'Code: 10') !== false || strpos($e->getMessage(), 'pages_read_engagement') !== false)) {
                    $isFallbackMode = true;
                    $fallbackFields = 'id,message,created_time,attachments,full_picture,permalink_url,shares';
                    $pageUrl = "https://graph.facebook.com/" . self::$version . "/me/posts?fields={$fallbackFields}&limit={$pageSize}&access_token=" . urlencode($token);
                    continue; // Retry current iteration with fallback URL
                } else {
                    throw $e;
                }
            }
            
            if (!empty($raw['data']) && is_array($raw['data'])) {
                $allData = array_merge($allData, $raw['data']);
            }

            if (!$unlimited && count($allData) >= $limit) {
                break;
            }

            $pageUrl = $raw['paging']['next'] ?? null;
            if ($isFallbackMode && $pageUrl) {
                // Ensure next cursor URL also uses fallback fields
                $urlParts = parse_url($pageUrl);
                parse_str($urlParts['query'] ?? '', $queryParts);
                $queryParts['fields'] = 'id,message,created_time,attachments,full_picture,permalink_url,shares';
                $pageUrl = $urlParts['scheme'] . '://' . $urlParts['host'] . $urlParts['path'] . '?' . http_build_query($queryParts);
            }
        }

        if (count($allData) === 0) {
            $fallbackUrl = "https://graph.facebook.com/" . self::$version . "/me/feed?fields=" . ($isFallbackMode ? 'id,message,created_time,attachments,full_picture,permalink_url,shares' : $fields) . "&limit={$pageSize}&access_token=" . urlencode($token);
            try {
                $raw = self::executeRequest('GET', $fallbackUrl);
            } catch (Exception $e) {
                if (!$isFallbackMode && (strpos($e->getMessage(), 'Code: 10') !== false || strpos($e->getMessage(), 'pages_read_engagement') !== false)) {
                    $fallbackUrl = "https://graph.facebook.com/" . self::$version . "/me/feed?fields=id,message,created_time,attachments,full_picture,permalink_url,shares&limit={$pageSize}&access_token=" . urlencode($token);
                    $raw = self::executeRequest('GET', $fallbackUrl);
                } else {
                    throw $e;
                }
            }
            if (!empty($raw['data']) && is_array($raw['data'])) {
                $allData = $raw['data'];
            }
        }

        if (!$unlimited && count($allData) > $limit) {
            $allData = array_slice($allData, 0, $limit);
        }

        return ['data' => $allData];
    }

    /**
     * Shared helper to handle cURL requests.
     */
    private static function executeRequest($method, $url, array $payload = []) {
        $ch = curl_init();
        
        if (strtoupper($method) === 'POST') {
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            
            $hasFile = false;
            foreach ($payload as $val) {
                if ($val instanceof CURLFile) {
                    $hasFile = true;
                    break;
                }
            }
            
            if ($hasFile) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            } else {
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
            }
        } else {
            // GET, DELETE
            curl_setopt($ch, CURLOPT_URL, $url);
            if (strtoupper($method) === 'DELETE') {
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
            }
        }
        
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode($response, true);
        
        if ($httpCode >= 400 || isset($data['error'])) {
            $msg = $data['error']['message'] ?? 'Unknown Graph API Error';
            $code = $data['error']['code'] ?? $httpCode;
            throw new Exception("Facebook Graph API Exception (Code: {$code}): {$msg}", $httpCode);
        }

        return $data;
    }
}
