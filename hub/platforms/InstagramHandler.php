<?php
/**
 * Instagram Graph API Handler.
 * Shares Facebook Page tokens, but uses distinct Instagram endpoint routes.
 */

class InstagramHandler {

    private static $version = 'v18.0';

    /**
     * Publishes a post to an Instagram Business account.
     * Uses a two-step process: (1) create container, (2) publish container.
     *
     * @param string $token     Facebook Page Token (authorized for IG account)
     * @param string $igUserId  Instagram Business Account User ID
     * @param string $content   Post caption
     * @param string $mediaUrl  Cloudflare CDN URL of the media (required by Instagram)
     * @return array            Contains response of final publication (with external ID)
     * @throws Exception
     */
    public static function publishPost($token, $igUserId, $content, $mediaUrl) {
        if (empty($mediaUrl)) {
            throw new Exception("Instagram requires a valid media URL for posting (text-only posts are not supported).");
        }

        // Pre-flight: verify the media URL is publicly reachable before sending to Instagram.
        // Use HEAD request (no body download) and force TLS 1.2 to match tunnel requirements.
        $ch = curl_init($mediaUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1_2); // Force TLS 1.2 (fixes XAMPP OpenSSL mismatch)
        curl_setopt($ch, CURLOPT_NOBODY, true); // HEAD request — no body, just headers
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; InstagramBot/1.0)');
        curl_exec($ch);
        $preflightCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $preflightContentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $preflightError = curl_error($ch);
        curl_close($ch);

        log_message('debug', "Instagram pre-flight URL check", [
            'url'          => $mediaUrl,
            'http_code'    => $preflightCode,
            'content_type' => $preflightContentType,
            'curl_error'   => $preflightError,
        ]);

        if ($preflightCode === 0) {
            log_message('warning', "Instagram pre-flight: URL unreachable from this server. Instagram may still be able to reach it via public internet. Error: {$preflightError}");
        } elseif ($preflightCode >= 400) {
            throw new Exception("Instagram media URL returned HTTP {$preflightCode}. Ensure the tunnel is running and the file exists at: {$mediaUrl}");
        }

        $ext = strtolower(pathinfo(parse_url($mediaUrl, PHP_URL_PATH), PATHINFO_EXTENSION));
        $isVideo = in_array($ext, ['mp4', 'mov', 'avi', 'mkv', 'webm', 'm4v']);

        // Step 1: Create the media container
        $containerUrl = "https://graph.facebook.com/" . self::$version . "/{$igUserId}/media";
        $containerPayload = [
            'access_token' => $token,
            'caption'      => $content,
        ];

        if ($isVideo) {
            $containerPayload['media_type'] = 'VIDEO';
            $containerPayload['video_url']  = $mediaUrl;
        } else {
            // Explicitly declare IMAGE type — required by Meta Graph API to avoid error 9004
            $containerPayload['media_type'] = 'IMAGE';
            $containerPayload['image_url']  = $mediaUrl;
        }

        log_message('debug', "Instagram container create request", [
            'ig_user_id'   => $igUserId,
            'media_type'   => $containerPayload['media_type'],
            'media_url'    => $mediaUrl,
            'url_ext'      => $ext,
        ]);

        $containerRes = self::executeRequest('POST', $containerUrl, $containerPayload);

        log_message('debug', "Instagram container create response", ['response' => $containerRes]);

        if (empty($containerRes['id'])) {
            throw new Exception("Failed to retrieve creation container ID from Instagram.");
        }

        $creationId = $containerRes['id'];

        // If it's a video, wait/poll for it to finish processing (Instagram needs a few seconds to ingest videos)
        if ($isVideo) {
            $statusUrl = "https://graph.facebook.com/" . self::$version . "/{$creationId}?fields=status_code&access_token=" . urlencode($token);
            $maxPoll = 10;
            $poll = 0;
            do {
                sleep(3);
                $statusRes = self::executeRequest('GET', $statusUrl);
                $statusCode = $statusRes['status_code'] ?? 'EXPIRED';
                $poll++;
            } while ($statusCode === 'IN_PROGRESS' && $poll < $maxPoll);

            if ($statusCode !== 'FINISHED') {
                throw new Exception("Instagram video processing timed out or failed. Code: {$statusCode}");
            }
        } else {
            // Give Instagram a brief moment to download the image
            sleep(2);
        }

        // Step 2: Publish the media container using the creation ID
        $publishUrl = "https://graph.facebook.com/" . self::$version . "/{$igUserId}/media_publish";
        $publishPayload = [
            'access_token' => $token,
            'creation_id'  => $creationId
        ];

        $publishRes = self::executeRequest('POST', $publishUrl, $publishPayload);
        log_message('debug', "Instagram publish response", ['response' => $publishRes]);

        return $publishRes;
    }

    /**
     * Deletes an Instagram Media post.
     * Note: Deleting carousel albums deletes the entire album.
     * Partial media item deletions are not supported.
     *
     * @param string $token   Facebook Page Token
     * @param string $mediaId Instagram Media ID (external ID)
     * @return array
     * @throws Exception
     */
    public static function deletePost($token, $mediaId) {
        $endpoint = "https://graph.facebook.com/" . self::$version . "/{$mediaId}?access_token=" . urlencode($token);
        return self::executeRequest('DELETE', $endpoint);
    }

    /**
     * Instagram does NOT support post editing via its API.
     * Do NOT add or implement an editPost() method.
     */

    /**
     * Retrieves engagement analytics for an Instagram Media item.
     *
     * @param string $token   Facebook Page Token
     * @param string $mediaId Instagram Media ID (external)
     * @param array  $metrics Array of metrics (e.g., engagement, impressions, reach, saved)
     * @return array
     * @throws Exception
     */
    public static function getInsights($token, $mediaId, array $metrics, $period = null) {
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
            $mediaId,
            http_build_query($urlParams)
        );
        return self::executeRequest('GET', $endpoint);
    }

    /**
     * Gets comment list on an Instagram Media post.
     */
    public static function getComments($token, $mediaId) {
        $endpoint = sprintf(
            "https://graph.facebook.com/%s/%s/comments?access_token=%s",
            self::$version,
            $mediaId,
            urlencode($token)
        );
        return self::executeRequest('GET', $endpoint);
    }

    /**
     * Replies to a comment on Instagram.
     *
     * @param string $token     Facebook Page Token
     * @param string $commentId Instagram Comment ID
     * @param string $reply     Reply text content
     * @return array
     * @throws Exception
     */
    public static function replyToComment($token, $commentId, $reply) {
        $endpoint = "https://graph.facebook.com/" . self::$version . "/{$commentId}/replies";
        $payload = [
            'access_token' => $token,
            'message'      => $reply
        ];
        return self::executeRequest('POST', $endpoint, $payload);
    }

    /**
     * Retrieves Instagram Account profile info (followers, following, media count).
     */
    public static function getAccountInfo($token, $igUserId) {
        $endpoint = sprintf(
            "https://graph.facebook.com/%s/%s?fields=followers_count,follows_count,media_count,username,name&access_token=%s",
            self::$version,
            urlencode($igUserId),
            urlencode($token)
        );
        return self::executeRequest('GET', $endpoint);
    }

    /**
     * Retrieves recent media from Instagram.
     * Supports cursor pagination for older media items.
     */
    public static function getRecentMedia($token, $igUserId, $limit = 50) {
        $limit = max(1, min(500, $limit));
        $fields = 'id,caption,media_type,media_url,timestamp,like_count,comments_count';
        $pageUrl = sprintf(
            "https://graph.facebook.com/%s/%s/media?fields=%s&limit=%d&access_token=%s",
            self::$version,
            urlencode($igUserId),
            $fields,
            min($limit, 100),
            urlencode($token)
        );
        $allData = [];
        $maxPages = 10;

        while ($pageUrl && count($allData) < $limit && $maxPages-- > 0) {
            $raw = self::executeRequest('GET', $pageUrl);
            if (!empty($raw['data']) && is_array($raw['data'])) {
                $allData = array_merge($allData, $raw['data']);
            }

            if (count($allData) >= $limit) {
                break;
            }

            $pageUrl = $raw['paging']['next'] ?? null;
        }

        if (count($allData) > $limit) {
            $allData = array_slice($allData, 0, $limit);
        }

        return ['data' => $allData];
    }

    /**
     * Helper request executor.
     */
    private static function executeRequest($method, $url, array $payload = []) {
        $ch = curl_init();
        
        if (strtoupper($method) === 'POST') {
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
        } else {
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
            $msg = $data['error']['message'] ?? 'Instagram Graph API Error';
            $code = $data['error']['code'] ?? $httpCode;
            throw new Exception("Instagram Graph Exception (Code: {$code}): {$msg}", $httpCode);
        }

        return $data;
    }
}
