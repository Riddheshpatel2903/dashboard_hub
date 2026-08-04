<?php

/**
 * LinkedIn API Handler.
 * Implements the LinkedIn Posts API (Community Management API) for personal profile scopes.
 *
 * NOTE: Page analytics/follower data is NOT implemented here because it requires
 * Marketing Developer Platform (MDP) authorization approval, which is out of scope.
 *
 * VERSIONING: LinkedIn requires every request to the /rest/ endpoints to include a
 * LinkedIn-Version: YYYYMM header. LinkedIn supports each version for roughly 1-2 years
 * before sunsetting it (returns HTTP 426 "not active"). Bump self::$apiVersion below
 * when that happens — it's the only place the version string lives.
 */
class LinkedInHandler
{
    /**
     * LinkedIn API version (YYYYMM format). Bump this when LinkedIn sunsets it (HTTP 426).
     */
    private static $apiVersion = '202607';

    /**
     * Publishes a personal post to a member's feed, optionally with an image.
     *
     * @param string $token          LinkedIn access token
     * @param string $authorUrn      Member URN (format: urn:li:person:MEMBER_ID)
     * @param string $content        Text content
     * @param string|null $localFilePath Absolute local path to an image file to attach
     * @return array                 Decoded response, with 'id' set to the created post URN
     * @throws Exception             On any failure — never silently drops the image
     */
    public static function publishPost($token, $authorUrn, $content, $localFilePath = null)
    {
        $url = 'https://api.linkedin.com/rest/posts';

        $imageUrn = null;
        if ($localFilePath && file_exists($localFilePath)) {
            $imageUrn = self::uploadImage($token, $authorUrn, $localFilePath);
        } elseif ($localFilePath && !file_exists($localFilePath)) {
            // Fail loudly rather than posting text-only silently.
            throw new Exception("LinkedIn Image Upload Failure: local file not found at {$localFilePath}");
        }

        $payload = [
            'author' => $authorUrn,
            'commentary' => $content,
            'visibility' => 'PUBLIC',
            'distribution' => [
                'feedDistribution' => 'MAIN_FEED',
                'targetEntities' => []
            ],
            'lifecycleState' => 'PUBLISHED'
        ];

        if ($imageUrn) {
            $payload['content'] = [
                'media' => [
                    'id' => $imageUrn
                ]
            ];
        }

        // The Posts API returns the created resource URN in the "x-linkedin-id" header.
        return self::executeRequest('POST', $token, $url, $payload);
    }

    /**
     * Uploads a local image file to LinkedIn via the current Images API and waits until
     * it's fully processed (AVAILABLE) before returning the URN.
     *
     * @throws Exception on any failure at any step — caller must not catch-and-ignore this.
     */
    private static function uploadImage($token, $authorUrn, $localFilePath)
    {
        // Step 1: Initialize the upload.
        $initUrl = 'https://api.linkedin.com/rest/images?action=initializeUpload';
        $initPayload = [
            'initializeUploadRequest' => [
                'owner' => $authorUrn
            ]
        ];

        $initRes = self::executeRequest('POST', $token, $initUrl, $initPayload);

        $uploadUrl = $initRes['value']['uploadUrl'] ?? null;
        $imageUrn = $initRes['value']['image'] ?? null;

        if (!$uploadUrl || !$imageUrn) {
            throw new Exception(
                'LinkedIn Image Upload Failure: initializeUpload did not return an uploadUrl/image. Response: '
                . json_encode($initRes)
            );
        }

        $ch = curl_init($uploadUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($ch, CURLOPT_POSTFIELDS, file_get_contents($localFilePath));

        // SSL verification: enabled by default, can be disabled locally via APP_ENV
        $sslVerify = true;
        if (getenv('APP_ENV') === 'local') {
            $sslVerify = false; // Bypass local XAMPP certificate mismatch issues
        }
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $sslVerify);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $sslVerify ? 2 : 0);

        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $token,
            'LinkedIn-Version: ' . self::$apiVersion,
            'X-Restli-Protocol-Version: 2.0.0',
            'Content-Type:' // Suppress cURL's default application/x-www-form-urlencoded header
        ]);
        $uploadRes = curl_exec($ch);
        $uploadCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            throw new Exception("LinkedIn Image Upload Failure: cURL error during binary upload: {$curlErr}");
        }

        if ($uploadCode < 200 || $uploadCode >= 300) {
            throw new Exception(
                "LinkedIn Image Upload Failure: binary upload failed with HTTP Code {$uploadCode}. Response: "
                . self::truncate($uploadRes)
            );
        }

        // Step 3: Poll until LinkedIn finishes processing the image (status AVAILABLE),
        // instead of guessing with a fixed sleep().
        self::waitForImageAvailable($token, $imageUrn);

        return $imageUrn;
    }

    /**
     * Polls GET /rest/images/{urn} until status is AVAILABLE, or throws on failure/timeout.
     */
    private static function waitForImageAvailable($token, $imageUrn, $maxAttempts = 10, $delaySeconds = 1)
    {
        $statusUrl = 'https://api.linkedin.com/rest/images/' . rawurlencode($imageUrn);

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $res = self::executeRequest('GET', $token, $statusUrl);
            $status = $res['status'] ?? null;

            if ($status === 'AVAILABLE') {
                return true;
            }

            if ($status === 'PROCESSING_FAILED') {
                throw new Exception('LinkedIn Image Upload Failure: image processing failed. Response: ' . json_encode($res));
            }

            sleep($delaySeconds);
        }

        throw new Exception("LinkedIn Image Upload Failure: image did not reach AVAILABLE status within {$maxAttempts} seconds (urn: {$imageUrn}).");
    }

    /**
     * Modifies the commentary (text content) of an existing post.
     *
     * @param string $token      LinkedIn access token
     * @param string $postId     Post URN / ID (format: urn:li:share:ID or urn:li:ugcPost:ID)
     * @param string $newContent New text content
     * @return array
     * @throws Exception
     */
    public static function editPost($token, $postId, $newContent)
    {
        $urn = $postId;
        if (strpos($urn, 'urn:li:') !== 0) {
            $urn = 'urn:li:share:' . $urn;
        }
        $url = 'https://api.linkedin.com/rest/posts/' . rawurlencode($urn);

        $payload = [
            'patch' => [
                '$set' => [
                    'commentary' => $newContent
                ]
            ]
        ];

        return self::executeRequest('PATCH', $token, $url, $payload);
    }

    /**
     * Deletes an existing post.
     *
     * @param string $token  LinkedIn access token
     * @param string $postId Post URN / ID (format: urn:li:share:ID or urn:li:ugcPost:ID)
     * @return array
     * @throws Exception
     */
    public static function deletePost($token, $postId)
    {
        $urn = $postId;
        if (strpos($urn, 'urn:li:') !== 0) {
            $urn = 'urn:li:share:' . $urn;
        }
        $url = 'https://api.linkedin.com/rest/posts/' . rawurlencode($urn);
        return self::executeRequest('DELETE', $token, $url);
    }

    /**
     * Retrieves recent posts for a member from LinkedIn with paging and 12-month cutoff check.
     */
    public static function getRecentPosts($token, $authorUrn, $limit = 50)
    {
        $unlimited = ($limit <= 0);
        $pageSize = 50;
        $allPosts = [];
        $start = 0;
        $maxPages = 10;

        while (($unlimited || count($allPosts) < $limit) && $maxPages-- > 0) {
            $url = 'https://api.linkedin.com/rest/posts?q=author&author=' . rawurlencode($authorUrn)
                . '&count=' . $pageSize . '&start=' . $start;

            $raw = self::executeRequest('GET', $token, $url);
            if (empty($raw['elements']) || !is_array($raw['elements'])) {
                break;
            }

            $allPosts = array_merge($allPosts, $raw['elements']);

            $lastPost = end($raw['elements']);
            if ($lastPost && !empty($lastPost['createdAt'])) {
                if (($lastPost['createdAt'] / 1000) < strtotime('-12 months')) {
                    break;
                }
            }

            if (count($raw['elements']) < $pageSize) {
                break;
            }
            $start += $pageSize;
        }

        if (!$unlimited && count($allPosts) > $limit) {
            $allPosts = array_slice($allPosts, 0, $limit);
        }

        return ['elements' => $allPosts];
    }

    /**
     * Shared request executor. Captures response headers so the created post's URN
     * (returned via the x-linkedin-id / x-restli-id header) can be parsed out.
     *
     * @throws Exception on any HTTP error (>=400) or transport-level cURL failure.
     */
    private static function executeRequest($method, $token, $url, array $payload = [])
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);  // Include response headers to capture created ID

        $headers = [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
            'X-Restli-Protocol-Version: 2.0.0',
            'LinkedIn-Version: ' . self::$apiVersion
        ];

        $method = strtoupper($method);
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        } elseif ($method === 'PATCH') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');  // Rest.li recommends POST with method override
            $headers[] = 'X-HTTP-Method-Override: PATCH';
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        } elseif ($method === 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        } elseif ($method === 'GET' && !empty($payload)) {
            // Not used currently, but keep safe if a future GET needs a body.
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);
        $curlErr = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        if ($curlErr) {
            throw new Exception("LinkedIn API Exception: cURL transport error: {$curlErr}");
        }

        $headersStr = substr($response, 0, $headerSize);
        $bodyStr = substr($response, $headerSize);

        $data = json_decode($bodyStr, true);
        if ($data === null) {
            $data = [];
        }

        // Parse the created resource's URN from response headers on successful creation.
        if ($httpCode === 201) {
            foreach (explode("\r\n", $headersStr) as $headerLine) {
                if (stripos($headerLine, 'x-linkedin-id:') === 0) {
                    $data['id'] = trim(substr($headerLine, strlen('x-linkedin-id:')));
                    break;
                }
                if (stripos($headerLine, 'x-restli-id:') === 0) {
                    $data['id'] = trim(substr($headerLine, strlen('x-restli-id:')));
                    break;
                }
            }
        }

        if ($httpCode >= 400) {
            $msg = $data['message'] ?? self::truncate($bodyStr);
            throw new Exception("LinkedIn API Exception (Code {$httpCode}): {$msg}", $httpCode);
        }

        return $data;
    }

    /**
     * Truncates long response bodies (e.g. HTML error pages) so exception messages
     * and logs stay readable instead of dumping full boilerplate markup.
     */
    private static function truncate($str, $maxLen = 500)
    {
        $str = (string) $str;
        if (strlen($str) <= $maxLen) {
            return $str;
        }
        return substr($str, 0, $maxLen) . '... [truncated]';
    }
}
