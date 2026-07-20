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

        // Validate public media URL reachability
        $ch = curl_init($mediaUrl);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 6);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_exec($ch);
        $curlError = curl_error($ch);
        curl_close($ch);

        if (!empty($curlError)) {
            throw new Exception("Public media URL is unreachable. Local server connection to tunnel failed: {$curlError}. Please ensure your public tunnel (e.g. lhr.life or ngrok) is active and running.");
        }

        $ext = strtolower(pathinfo(parse_url($mediaUrl, PHP_URL_PATH), PATHINFO_EXTENSION));
        $isVideo = in_array($ext, ['mp4', 'mov', 'avi']);

        // Step 1: Create the media container
        $containerUrl = "https://graph.facebook.com/" . self::$version . "/{$igUserId}/media";
        $containerPayload = [
            'access_token' => $token,
            'caption'      => $content,
        ];

        if ($isVideo) {
            $containerPayload['media_type'] = 'VIDEO';
            $containerPayload['video_url'] = $mediaUrl;
        } else {
            $containerPayload['image_url'] = $mediaUrl;
        }

        $containerRes = self::executeRequest('POST', $containerUrl, $containerPayload);
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
            sleep(1);
        }

        // Step 2: Publish the media container using the creation ID
        $publishUrl = "https://graph.facebook.com/" . self::$version . "/{$igUserId}/media_publish";
        $publishPayload = [
            'access_token' => $token,
            'creation_id'  => $creationId
        ];

        return self::executeRequest('POST', $publishUrl, $publishPayload);
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
