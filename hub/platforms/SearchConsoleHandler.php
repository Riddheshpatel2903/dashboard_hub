<?php
/**
 * Search Console platform handler.
 */

class SearchConsoleHandler {
    public static function getSearchAnalytics($token, $siteUrl, $startDate, $endDate, array $dimensions = ['date']) {
        $url = "https://www.googleapis.com/webmasters/v3/sites/" . urlencode($siteUrl) . "/searchAnalytics/query";
        $payload = [
            'startDate' => $startDate,
            'endDate' => $endDate,
            'dimensions' => $dimensions,
            'dataState' => 'all',
            'rowLimit' => 25000
        ];
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $token,
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        $response = curl_exec($ch);
        $ch_err = curl_error($ch);
        curl_close($ch);
        
        if ($response === false) {
             throw new Exception('Search Console Curl Failure: ' . $ch_err);
        }
        
        $decoded = json_decode($response, true);
        if (isset($decoded['error'])) {
            throw new Exception('Search Console Exception: ' . ($decoded['error']['message'] ?? 'Unknown error'));
        }
        return $decoded['rows'] ?? [];
    }

    public static function listSites($token) {
        $url = "https://www.googleapis.com/webmasters/v3/sites";
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $token]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        $response = curl_exec($ch);
        curl_close($ch);
        $decoded = json_decode($response, true);
        return $decoded['siteEntry'] ?? [];
    }
}
