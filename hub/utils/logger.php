<?php
/**
 * Simple, consistent file-based logger.
 */

/**
 * Write a message to the hub application log.
 * Named log_message() to avoid collision with PHP's native log() (logarithm) function.
 *
 * @param string $level   e.g., INFO, ERROR, WARNING, DEBUG
 * @param string $message
 * @param array  $context Additional debug context variables
 */
function log_message($level, $message, array $context = []) {
    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $logFile = $logDir . '/app.log';
    $timestamp = date('Y-m-d H:i:s');
    $contextStr = !empty($context) ? ' ' . json_encode($context, JSON_UNESCAPED_SLASHES) : '';
    $logLine = sprintf("[%s] [%s]: %s%s\n", $timestamp, strtoupper($level), $message, $contextStr);
    
    error_log($logLine, 3, $logFile);
}
