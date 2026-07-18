<?php
/**
 * Facebook Graph API Handler.
 */

class FacebookHandler {

    private static $version = 'v18.0';

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
    public static function publishPost($token, $pageId, $content, $mediaUrl = null) {
        $endpoint = "https://graph.facebook.com/" . self::$version . "/{$pageId}/";
        $payload = ['access_token' => $token];

        if ($mediaUrl) {
            $ext = strtolower(pathinfo(parse_url($mediaUrl, PHP_URL_PATH), PATHINFO_EXTENSION));
            $isVid = in_array($ext, ['mp4', 'mov', 'avi', 'mpeg']);
            
            if ($isVid) {
                $endpoint .= "videos";
                $payload['file_url'] = $mediaUrl;
                $payload['description'] = $content;
            } else {
                $endpoint .= "photos";
                $payload['url'] = $mediaUrl;
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
    public static function getInsights($token, $targetId, array $metrics) {
        $endpoint = sprintf(
            "https://graph.facebook.com/%s/%s/insights?metric=%s&access_token=%s",
            self::$version,
            $targetId,
            implode(',', $metrics),
            urlencode($token)
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
        $endpoint = "https://graph.facebook.com/" . self::$version . "/{$commentId}/comments";
        $payload = [
            'access_token' => $token,
            'message'      => $reply
        ];
        return self::executeRequest('POST', $endpoint, $payload);
    }

    /**
     * Shared helper to handle cURL requests.
     */
    private static function executeRequest($method, $url, array $payload = []) {
        $ch = curl_init();
        
        if (strtoupper($method) === 'POST') {
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
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
