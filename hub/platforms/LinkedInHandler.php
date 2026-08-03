<?php
/**
 * LinkedIn API Handler.
 * Implements the LinkedIn Posts API (Community Management API) for personal profile scopes.
 *
 * NOTE: Page analytics/follower data is NOT implemented here because it requires
 * Marketing Developer Platform (MDP) authorization approval, which is out of scope.
 */

class LinkedInHandler {

    /**
     * Publishes a personal post to a member's feed.
     *
     * @param string $token     LinkedIn access token
     * @param string $authorUrn Member URN (format: urn:li:person:MEMBER_ID)
     * @param string $content   Text content
     * @return array            Contains response data (including post URN ID in x-linkedin-id header or body)
     * @throws Exception
     */
    public static function publishPost($token, $authorUrn, $content, $localFilePath = null) {
        $url = "https://api.linkedin.com/v2/posts";
        
        $assetUrn = null;
        if ($localFilePath && file_exists($localFilePath)) {
            // Step A: Register the image asset upload
            $registerUrl = "https://api.linkedin.com/v2/assets?action=registerUpload";
            $registerPayload = [
                'registerUploadRequest' => [
                    'recipes' => [
                        'urn:li:digitalmediaRecipe:feedshare-image'
                    ],
                    'owner' => $authorUrn,
                    'supportedUploadMechanism' => [
                        'SYNCHRONOUS_UPLOAD'
                    ]
                ]
            ];
            
            $regRes = self::executeRequestNoHeader('POST', $token, $registerUrl, $registerPayload);
            
            if (!empty($regRes['value']['uploadMechanism']['com.linkedin.digitalmedia.uploading.MediaUploadMechanism']['uploadUrl'])) {
                $uploadUrl = $regRes['value']['uploadMechanism']['com.linkedin.digitalmedia.uploading.MediaUploadMechanism']['uploadUrl'];
                $assetUrn = $regRes['value']['asset'] ?? null;
                
                // Step B: Upload the binary file to the uploadUrl via PUT
                $ch = curl_init($uploadUrl);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                curl_setopt($ch, CURLOPT_POSTFIELDS, file_get_contents($localFilePath));
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Authorization: Bearer ' . $token,
                    'Content-Type: application/octet-stream'
                ]);
                $uploadRes = curl_exec($ch);
                $uploadCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                if ($uploadCode < 200 || $uploadCode >= 300) {
                    throw new Exception("LinkedIn binary asset upload failed with HTTP Code {$uploadCode}. Response: {$uploadRes}");
                }
            }
        }
        
        $payload = [
            'author'         => $authorUrn,
            'commentary'     => $content,
            'visibility'     => 'PUBLIC',
            'distribution'   => [
                'feedDistribution' => 'MAIN_FEED',
                'targetEntities'   => []
            ],
            'lifecycleState' => 'PUBLISHED'
        ];

        if ($assetUrn) {
            // Convert digitalmediaAsset to image URN:
            // urn:li:digitalmediaAsset:C5522AQ... -> urn:li:image:C5522AQ...
            $imageUrn = str_replace('urn:li:digitalmediaAsset:', 'urn:li:image:', $assetUrn);
            $payload['content'] = [
                'media' => [
                    'id' => $imageUrn
                ]
            ];
        }

        // The Posts API returns the created resource URN in the "x-linkedin-id" header.
        // We will parse headers to retrieve it.
        return self::executeRequest('POST', $token, $url, $payload);
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
    public static function editPost($token, $postId, $newContent) {
        // LinkedIn uses Rest.li PATCH method to modify posts.
        $url = "https://api.linkedin.com/v2/posts/" . urlencode($postId);
        
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
    public static function deletePost($token, $postId) {
        $url = "https://api.linkedin.com/v2/posts/" . urlencode($postId);
        return self::executeRequest('DELETE', $token, $url);
    }

    /**
     * Retrieves recent posts for a member from LinkedIn with paging and 12-month cutoff check.
     */
    public static function getRecentPosts($token, $authorUrn, $limit = 50) {
        $unlimited = ($limit <= 0);
        $pageSize = 50;
        $allPosts = [];
        $start = 0;
        $maxPages = 10;
        
        while (($unlimited || count($allPosts) < $limit) && $maxPages-- > 0) {
            $authors = 'List(' . $authorUrn . ')';
            $url = "https://api.linkedin.com/v2/ugcPosts?q=authors&authors=" . rawurlencode($authors) . "&count=" . $pageSize . "&start=" . $start . "&fields=id,specificContent,created";
            $raw = self::executeRequestNoHeader('GET', $token, $url);
            if (empty($raw['elements']) || !is_array($raw['elements'])) {
                break;
            }
            
            $allPosts = array_merge($allPosts, $raw['elements']);
            
            $lastPost = end($raw['elements']);
            if ($lastPost && !empty($lastPost['created']['time'])) {
                if (($lastPost['created']['time'] / 1000) < strtotime('-12 months')) {
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
     * Helper request executor.
     */
    private static function executeRequest($method, $token, $url, array $payload = []) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true); // Include response headers to capture created ID
        
        $headers = [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
            'X-Restli-Protocol-Version: 2.0.0'
        ];

        if (strtoupper($method) === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        } elseif (strtoupper($method) === 'PATCH') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST'); // Rest.li recommends POST with Method override
            $headers[] = 'X-HTTP-Method-Override: PATCH';
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        } elseif (strtoupper($method) === 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        $headersStr = substr($response, 0, $headerSize);
        $bodyStr = substr($response, $headerSize);

        $data = json_decode($bodyStr, true) ?: [];

        // Parse custom headers like x-linkedin-id on successful creation
        if ($httpCode === 201) {
            foreach (explode("\r\n", $headersStr) as $headerLine) {
                if (stripos($headerLine, 'x-linkedin-id:') === 0) {
                    $data['id'] = trim(substr($headerLine, 14));
                    break;
                }
            }
        }

        if ($httpCode >= 400) {
            $msg = $data['message'] ?? 'LinkedIn REST API Error';
            throw new Exception("LinkedIn API Exception (Code {$httpCode}): {$msg}", $httpCode);
        }

        return $data;
    }

    /**
     * Helper request executor without response headers.
     */
    private static function executeRequestNoHeader($method, $token, $url, array $payload = []) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $headers = [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
            'X-Restli-Protocol-Version: 2.0.0'
        ];

        if (strtoupper($method) === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode($response, true) ?: [];

        if ($httpCode >= 400) {
            $msg = $data['message'] ?? 'LinkedIn REST API Error';
            throw new Exception("LinkedIn API Exception (Code {$httpCode}): {$msg}", $httpCode);
        }

        return $data;
    }
}
