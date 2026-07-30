<?php

/**
 * YouTube API Handler.
 * Interacts with YouTube Data API v3 and YouTube Analytics API.
 */
class YouTubeHandler
{
    private static $analyticsApiDisabled = false;
    /**
     * Upload a video using YouTube's Resumable Upload protocol.
     * Quota Cost: 1600 units (1600 units for insert).
     *
     * @param string $token     Google access token
     * @param string $mediaPath Local absolute path of the video file
     * @param string $title     Video title
     * @param string $desc      Video description
     * @return array            Contains video ID on success
     * @throws Exception
     */
    public static function uploadVideo($token, $mediaPath, $title, $desc)
    {
        if (!file_exists($mediaPath)) {
            throw new Exception("Local video file not found at: {$mediaPath}");
        }

        $fileSize = filesize($mediaPath);

        // Step 1: Initiate resumable session
        $initUrl = 'https://www.googleapis.com/upload/youtube/v3/videos?uploadType=resumable&part=snippet,status';

        $metadata = [
            'snippet' => [
                'title' => $title,
                'description' => $desc,
                'categoryId' => '22'  // People & Blogs default
            ],
            'status' => [
                'privacyStatus' => 'public'
            ]
        ];

        $ch = curl_init($initUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($metadata));
        curl_setopt($ch, CURLOPT_HEADER, true);  // We need headers to extract the Location URI
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json; charset=UTF-8',
            'X-Upload-Content-Type: video/*',
            'X-Upload-Content-Length: ' . $fileSize
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new Exception("Failed to initiate YouTube resumable upload session. Code {$httpCode}: " . substr($response, $headerSize));
        }

        // Parse headers to find Location
        $headersStr = substr($response, 0, $headerSize);
        $uploadUrl = '';
        foreach (explode("\r\n", $headersStr) as $headerLine) {
            if (stripos($headerLine, 'Location:') === 0) {
                $uploadUrl = trim(substr($headerLine, 9));
                break;
            }
        }

        if (empty($uploadUrl)) {
            throw new Exception('Did not receive a resumable Location URL from YouTube.');
        }

        // Step 2: Stream file payload to the upload Location URL
        $fileHandle = fopen($mediaPath, 'rb');
        if (!$fileHandle) {
            throw new Exception('Could not open video file handle for streaming.');
        }

        $ch = curl_init($uploadUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($ch, CURLOPT_PUT, true);
        curl_setopt($ch, CURLOPT_INFILE, $fileHandle);
        curl_setopt($ch, CURLOPT_INFILESIZE, $fileSize);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $token,
            'Content-Type: video/*'
        ]);

        $uploadResponse = curl_exec($ch);
        $uploadHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        fclose($fileHandle);
        curl_close($ch);

        $data = json_decode($uploadResponse, true);
        if ($uploadHttpCode !== 200 && $uploadHttpCode !== 201) {
            $msg = $data['error']['message'] ?? 'Resumable PUT stream failure';
            throw new Exception("YouTube video byte stream failed (Code {$uploadHttpCode}): {$msg}");
        }

        return $data;
    }

    /**
     * Edits YouTube video metadata.
     * Quota Cost: 50 units (50 units for update).
     *
     * @param string $token    Google access token
     * @param string $videoId  YouTube Video ID (external)
     * @param string $title    New Title
     * @param string $desc     New Description
     * @return array
     * @throws Exception
     */
    public static function editVideoMetadata($token, $videoId, $title, $desc)
    {
        $url = 'https://www.googleapis.com/youtube/v3/videos?part=snippet';

        $payload = [
            'id' => $videoId,
            'snippet' => [
                'title' => $title,
                'description' => $desc,
                'categoryId' => '22'
            ]
        ];

        return self::executeRequest('PUT', $token, $url, $payload);
    }

    /**
     * Deletes a video from YouTube.
     * Quota Cost: 50 units (50 units for delete).
     *
     * @param string $token   Google access token
     * @param string $videoId YouTube Video ID (external)
     * @return array
     * @throws Exception
     */
    public static function deleteVideo($token, $videoId)
    {
        $url = 'https://www.googleapis.com/youtube/v3/videos?id=' . urlencode($videoId);
        return self::executeRequest('DELETE', $token, $url);
    }

    /**
     * Helper to extract clean 11-character YouTube video ID from raw ID or full YouTube URL.
     */
    public static function extractVideoId($idOrUrl)
    {
        if (empty($idOrUrl)) return '';
        if (preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/', $idOrUrl, $matches)) {
            return $matches[1];
        }
        return trim($idOrUrl);
    }

    /**
     * Retrieves channel analytics statistics.
     * Quota Cost: 1 unit (1 unit for list).
     *
     * @param string $token     Google access token
     * @param string $channelId YouTube Channel ID
     * @return array
     * @throws Exception
     */
    public static function getChannelStats($token, $channelId = null)
    {
        if (empty($channelId) || $channelId === 'mine') {
            $url = 'https://www.googleapis.com/youtube/v3/channels?part=statistics,contentDetails&mine=true';
        } else {
            $url = 'https://www.googleapis.com/youtube/v3/channels?part=statistics,contentDetails&id=' . urlencode($channelId);
        }

        $res = self::executeRequest('GET', $token, $url);

        // Fallback: If querying by channel ID returned no items, try mine=true
        if (empty($res['items']) && !empty($channelId) && $channelId !== 'mine') {
            $urlFallback = 'https://www.googleapis.com/youtube/v3/channels?part=statistics,contentDetails&mine=true';
            try {
                $resFallback = self::executeRequest('GET', $token, $urlFallback);
                if (!empty($resFallback['items'])) {
                    return $resFallback;
                }
            } catch (Exception $e) {
                // Return original response if fallback fails
            }
        }

        return $res;
    }

    /**
     * Retrieves video statistics (views, likes, comments, duration) via YouTube Data API v3.
     * Quota Cost: 1 unit.
     *
     * @param string $token   Google access token
     * @param string $videoId YouTube Video ID (external)
     * @return array
     * @throws Exception
     */
    public static function getVideoStats($token, $videoId)
    {
        $cleanId = self::extractVideoId($videoId);
        $url = 'https://www.googleapis.com/youtube/v3/videos?part=statistics,snippet,contentDetails&id=' . urlencode($cleanId);
        return self::executeRequest('GET', $token, $url);
    }

    /**
     * Deletes a video via the YouTube Data API v3.
     */
    public static function deleteVideo($token, $videoId)
    {
        $cleanId = self::extractVideoId($videoId);
        $url = 'https://www.googleapis.com/youtube/v3/videos?id=' . urlencode($cleanId);
        return self::executeRequest('DELETE', $token, $url);
    }

    /**
     * Retrieves recent uploaded videos and their live statistics (views, likes, comments, duration).
     *
     * @param string $token
     * @param int    $maxResults
     * @return array
     */
    public static function getRecentChannelVideos($token, $maxResults = 50)
    {
        $unlimited = ($maxResults <= 0);
        if (!$unlimited) {
            $maxResults = max(1, min(500, $maxResults));
        }

        // 1. Get uploads playlist ID
        $chRes = self::getChannelStats($token, 'mine');
        if (empty($chRes['items'][0]['contentDetails']['relatedPlaylists']['uploads'])) {
            return [];
        }
        $uploadsPlaylistId = $chRes['items'][0]['contentDetails']['relatedPlaylists']['uploads'];

        // 2. Collect playlist video IDs with pagination
        $videoIds = [];
        $nextPageToken = null;
        while ($nextPageToken !== false && ($unlimited || count($videoIds) < $maxResults)) {
            $pageSize = $unlimited ? 50 : min(50, $maxResults - count($videoIds));
            $plUrl = 'https://www.googleapis.com/youtube/v3/playlistItems?part=contentDetails&maxResults=' . $pageSize . '&playlistId=' . urlencode($uploadsPlaylistId);
            if ($nextPageToken) {
                $plUrl .= '&pageToken=' . urlencode($nextPageToken);
            }

            $plRes = self::executeRequest('GET', $token, $plUrl);
            if (empty($plRes['items']) || !is_array($plRes['items'])) {
                break;
            }

            foreach ($plRes['items'] as $item) {
                if (!empty($item['contentDetails']['videoId'])) {
                    $videoIds[] = $item['contentDetails']['videoId'];
                    if (!$unlimited && count($videoIds) >= $maxResults) {
                        break 2;
                    }
                }
            }

            $nextPageToken = $plRes['nextPageToken'] ?? false;
            if (!$nextPageToken) {
                break;
            }
        }

        if (empty($videoIds)) {
            return [];
        }

        // 3. Batch fetch live video stats & ISO duration for all collected IDs
        $allVideoData = ['items' => []];
        foreach (array_chunk($videoIds, 50) as $chunk) {
            $vUrl = 'https://www.googleapis.com/youtube/v3/videos?part=statistics,snippet,contentDetails,status&id=' . implode(',', array_map('urlencode', $chunk));
            $videoRes = self::executeRequest('GET', $token, $vUrl);
            if (!empty($videoRes['items']) && is_array($videoRes['items'])) {
                $allVideoData['items'] = array_merge($allVideoData['items'], $videoRes['items']);
            }
        }

        return $allVideoData;
    }

    /**
     * Retrieves performance analytics via the YouTube Analytics API.
     * Note: This operates on a separate API endpoint and does not count towards standard Data API quota.
     * Quota Cost: 0 units (under separate daily query limits).
     *
     * @param string $token     Google access token
     * @param string $videoId   YouTube Video ID (external)
     * @param string $startDate Start date YYYY-MM-DD
     * @param string $endDate   End date YYYY-MM-DD
     * @return array
     * @throws Exception
     */
    public static function getVideoAnalytics($token, $videoId, $startDate = null, $endDate = null)
    {
        if (self::$analyticsApiDisabled) {
            throw new Exception("YouTube Analytics API is disabled or not activated in GCP project.");
        }

        $startDate = $startDate ?: date('Y-m-d', strtotime('-30 days'));
        $endDate = $endDate ?: date('Y-m-d');

        $url = sprintf(
            'https://youtubeanalytics.googleapis.com/v2/reports?ids=channel==MINE&startDate=%s&endDate=%s&metrics=views,comments,likes,dislikes,estimatedMinutesWatched,averageViewDuration,subscribersGained&filters=video==%s',
            $startDate,
            $endDate,
            $videoId
        );

        try {
            return self::executeRequest('GET', $token, $url);
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'has not been used in project') !== false || strpos($e->getMessage(), 'disabled') !== false) {
                self::$analyticsApiDisabled = true;
            }
            throw $e;
        }
    }

    /**
     * Helper request executor.
     */
    private static function executeRequest($method, $token, $url, array $payload = [])
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $headers = [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json'
        ];

        if (strtoupper($method) === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
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
            $msg = $data['error']['message'] ?? 'YouTube API Error';
            $code = $data['error']['code'] ?? $httpCode;
            throw new Exception("YouTube API Exception (Code {$code}): {$msg}", $httpCode);
        }

        return $data;
    }
}
