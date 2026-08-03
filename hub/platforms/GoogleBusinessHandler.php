<?php
/**
 * Google Business Profile API Handler.
 * Manages postings, updates, reviews, and performance metrics.
 */

class GoogleBusinessHandler {

    /**
     * Creates a Local Post for a Google Business location.
     *
     * @param string $token      Google access token
     * @param string $locationId Location URN / ID (format: locations/LOCATION_ID)
     * @param string $content    Post text body
     * @param string $mediaUrl   Cloudflare CDN media URL (optional)
     * @return array
     * @throws Exception
     */
    public static function createPost($token, $locationId, $content, $mediaUrl = null) {
        // Endpoint: mybusinesslocalpost.googleapis.com/v1/locations/LOCATION_ID/localPosts
        $url = "https://mybusinesslocalpost.googleapis.com/v1/" . ltrim($locationId, '/') . "/localPosts";
        
        $payload = [
            'summary'   => $content,
            'topicType' => 'STANDARD'
        ];

        if ($mediaUrl) {
            $payload['media'] = [
                [
                    'mediaFormat' => 'PHOTO',
                    'sourceUrl'   => $mediaUrl
                ]
            ];
        }

        return self::executeRequest('POST', $token, $url, $payload);
    }

    /**
     * Updates Google Business Profile details.
     *
     * @param string $token      Google access token
     * @param string $locationId Location URN (format: locations/LOCATION_ID)
     * @param array  $fields     Key-value pair details to update
     * @return array
     * @throws Exception
     */
    public static function updateBusinessInfo($token, $locationId, array $fields) {
        // Business Information API
        // Endpoint: mybusinessbusinessinformation.googleapis.com/v1/locations/LOCATION_ID
        $updateMask = implode(',', array_keys($fields));
        $url = sprintf(
            "https://mybusinessbusinessinformation.googleapis.com/v1/%s?updateMask=%s",
            ltrim($locationId, '/'),
            $updateMask
        );

        return self::executeRequest('PATCH', $token, $url, $fields);
    }

    /**
     * Deletes a Local Post.
     *
     * @param string $token  Google access token
     * @param string $postId Post URN (format: locations/LOCATION_ID/localPosts/POST_ID)
     * @return array
     * @throws Exception
     */
    public static function deletePost($token, $postId) {
        $url = "https://mybusinesslocalpost.googleapis.com/v1/" . ltrim($postId, '/');
        return self::executeRequest('DELETE', $token, $url);
    }

    /**
     * Retrieves Google reviews for a specific location.
     *
     * @param string $token      Google access token
     * @param string $locationId Location URN (format: locations/LOCATION_ID)
     * @return array
     * @throws Exception
     */
    public static function getReviews($token, $locationId) {
        // My Business Account Management / Verifications API reviews route
        $url = "https://mybusinessverifications.googleapis.com/v1/" . ltrim($locationId, '/') . "/reviews";
        return self::executeRequest('GET', $token, $url);
    }

    /**
     * Replies to a specific Google Business review.
     *
     * @param string $token    Google access token
     * @param string $reviewId Review URN (format: locations/LOCATION_ID/reviews/REVIEW_ID)
     * @param string $reply    Reply comment body
     * @return array
     * @throws Exception
     */
    public static function replyToReview($token, $reviewId, $reply) {
        $url = "https://mybusinessverifications.googleapis.com/v1/" . ltrim($reviewId, '/') . "/reply";
        $payload = [
            'comment' => $reply
        ];
        return self::executeRequest('PUT', $token, $url, $payload);
    }

    /**
     * Retrieves search, map, and view performance metrics.
     * Note: This is an invocation of the separate Business Profile Performance API.
     *
     * @param string $token      Google access token
     * @param string $locationId Location URN (format: locations/LOCATION_ID)
     * @param array  $dateRange  Contains keys: start_year, start_month, start_day, end_year, end_month, end_day
     * @return array
     * @throws Exception
     */
    public static function getPerformanceMetrics($token, $locationId, array $dateRange = []) {
        // Performance API
        // Endpoint: businessprofileperformance.googleapis.com/v1/locations/LOCATION_ID:fetchMultiDailyMetricsTimeSeries
        $url = "https://businessprofileperformance.googleapis.com/v1/" . ltrim($locationId, '/') . ":fetchMultiDailyMetricsTimeSeries";
        
        $startYear = $dateRange['start_year'] ?? (int)date('Y', strtotime('-30 days'));
        $startMonth = $dateRange['start_month'] ?? (int)date('n', strtotime('-30 days'));
        $startDay = $dateRange['start_day'] ?? (int)date('j', strtotime('-30 days'));
        
        $endYear = $dateRange['end_year'] ?? (int)date('Y');
        $endMonth = $dateRange['end_month'] ?? (int)date('n');
        $endDay = $dateRange['end_day'] ?? (int)date('j');

        $payload = [
            'dailyMetrics' => [
                'BUSINESS_IMPRESSIONS_DESKTOP_MAPS',
                'BUSINESS_IMPRESSIONS_MOBILE_SEARCH',
                'BUSINESS_DIRECTION_REQUESTS'
            ],
            'dailyRange' => [
                'startDate' => [
                    'year'  => $startYear,
                    'month' => $startMonth,
                    'day'   => $startDay
                ],
                'endDate' => [
                    'year'  => $endYear,
                    'month' => $endMonth,
                    'day'   => $endDay
                ]
            ]
        ];

        return self::executeRequest('POST', $token, $url, $payload);
    }

    /**
     * Lists recent Local Posts with pagination and a 12-month cutoff.
     */
    public static function getRecentPosts($token, $locationId, $limit = 50) {
        $unlimited = ($limit <= 0);
        $pageSize = $unlimited ? 100 : min($limit, 100);
        $url = "https://mybusinesslocalpost.googleapis.com/v1/" . ltrim($locationId, '/') . "/localPosts?pageSize=" . $pageSize;
        
        $allPosts = [];
        $nextPageToken = null;
        $maxPages = 50;
        
        while ($url && ($unlimited || count($allPosts) < $limit) && $maxPages-- > 0) {
            $pageUrl = $url;
            if ($nextPageToken) {
                $pageUrl .= '&pageToken=' . urlencode($nextPageToken);
            }
            $raw = self::executeRequest('GET', $token, $pageUrl);
            if (!empty($raw['localPosts']) && is_array($raw['localPosts'])) {
                $allPosts = array_merge($allPosts, $raw['localPosts']);
                
                $lastPost = end($raw['localPosts']);
                if ($lastPost && !empty($lastPost['createTime'])) {
                    if (strtotime($lastPost['createTime']) < strtotime('-12 months')) {
                        break;
                    }
                }
            }
            $nextPageToken = $raw['nextPageToken'] ?? null;
            if (!$nextPageToken) {
                break;
            }
        }
        
        if (!$unlimited && count($allPosts) > $limit) {
            $allPosts = array_slice($allPosts, 0, $limit);
        }
        
        return ['localPosts' => $allPosts];
    }

    /**
     * Shared helper to handle Google API requests.
     */
    private static function executeRequest($method, $token, $url, array $payload = []) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $headers = [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json'
        ];

        if (strtoupper($method) === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        } elseif (strtoupper($method) === 'PATCH') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        } elseif (strtoupper($method) === 'PUT') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        } elseif (strtoupper($method) === 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode($response, true);
        if ($httpCode >= 400 || isset($data['error'])) {
            $msg = $data['error']['message'] ?? 'Google Business API Error';
            $code = $data['error']['code'] ?? $httpCode;
            throw new Exception("Google Business API Exception (Code {$code}): {$msg}", $httpCode);
        }

        return $data;
    }
}
