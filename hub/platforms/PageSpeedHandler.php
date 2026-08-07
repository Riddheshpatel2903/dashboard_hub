<?php
/**
 * PageSpeed Insights API handler.
 */

class PageSpeedHandler {
    public static function analyze($url, $strategy = 'mobile') {
        $apiKey = PAGESPEED_API_KEY; // from config/platforms.php
        $endpoint = "https://www.googleapis.com/pagespeedonline/v5/runPagespeed?"
            . http_build_query([
                'url' => $url,
                'key' => $apiKey,
                'strategy' => $strategy,
            ]);
        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60); // PageSpeed audits can be slow
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        $response = curl_exec($ch);
        $ch_err = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new Exception('PageSpeed Curl Failure: ' . $ch_err);
        }

        $decoded = json_decode($response, true);
        return [
            'performance_score' => $decoded['lighthouseResult']['categories']['performance']['score'] ?? null,
            'seo_score' => $decoded['lighthouseResult']['categories']['seo']['score'] ?? null,
            'accessibility_score' => $decoded['lighthouseResult']['categories']['accessibility']['score'] ?? null,
            'core_web_vitals' => $decoded['loadingExperience']['metrics'] ?? null,
        ];
    }
}
