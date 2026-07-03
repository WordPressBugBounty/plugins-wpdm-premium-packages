<?php
/**
 * Rate Limiter
 *
 * Provides rate limiting functionality to prevent brute-force attacks
 * on guest order lookup, license validation, and other sensitive endpoints.
 *
 * @package WPDMPP\Security
 * @since 7.0.0
 */

namespace WPDMPP\Security;

defined('ABSPATH') || exit;

class RateLimiter
{
    /**
     * Default maximum attempts
     *
     * @var int
     */
    public const DEFAULT_MAX_ATTEMPTS = 5;

    /**
     * Default time window in seconds (15 minutes)
     *
     * @var int
     */
    public const DEFAULT_WINDOW = 900;

    /**
     * Transient prefix
     *
     * @var string
     */
    private const TRANSIENT_PREFIX = 'wpdmpp_rl_';

    /**
     * Check if the current request is rate limited
     *
     * @param string $action      Unique identifier for the action being rate limited
     * @param int    $maxAttempts Maximum number of attempts allowed
     * @param int    $window      Time window in seconds
     * @param string $identifier  Optional custom identifier (defaults to IP address)
     * @return bool True if rate limited (should block), false if allowed
     */
    public static function isLimited(
        string $action,
        int $maxAttempts = self::DEFAULT_MAX_ATTEMPTS,
        int $window = self::DEFAULT_WINDOW,
        string $identifier = ''
    ): bool {
        $identifier = $identifier ?: self::getClientIP();
        $key = self::getKey($action, $identifier);
        $data = get_transient($key);

        if ($data === false) {
            return false;
        }

        return $data['count'] >= $maxAttempts;
    }

    /**
     * Record an attempt for rate limiting
     *
     * @param string $action     Unique identifier for the action
     * @param int    $window     Time window in seconds
     * @param string $identifier Optional custom identifier (defaults to IP address)
     * @return int Current attempt count
     */
    public static function recordAttempt(
        string $action,
        int $window = self::DEFAULT_WINDOW,
        string $identifier = ''
    ): int {
        $identifier = $identifier ?: self::getClientIP();
        $key = self::getKey($action, $identifier);
        $data = get_transient($key);

        if ($data === false) {
            $data = [
                'count' => 0,
                'first_attempt' => time(),
            ];
        }

        $data['count']++;
        $data['last_attempt'] = time();

        set_transient($key, $data, $window);

        return $data['count'];
    }

    /**
     * Check if limited and record attempt in one call
     *
     * @param string $action      Unique identifier for the action
     * @param int    $maxAttempts Maximum attempts allowed
     * @param int    $window      Time window in seconds
     * @param string $identifier  Optional custom identifier
     * @return array ['limited' => bool, 'attempts' => int, 'remaining' => int, 'retry_after' => int]
     */
    public static function check(
        string $action,
        int $maxAttempts = self::DEFAULT_MAX_ATTEMPTS,
        int $window = self::DEFAULT_WINDOW,
        string $identifier = ''
    ): array {
        $identifier = $identifier ?: self::getClientIP();
        $key = self::getKey($action, $identifier);
        $data = get_transient($key);

        if ($data === false) {
            $data = [
                'count' => 0,
                'first_attempt' => time(),
            ];
        }

        $isLimited = $data['count'] >= $maxAttempts;

        // Only record attempt if not already limited
        if (!$isLimited) {
            $data['count']++;
            $data['last_attempt'] = time();
            set_transient($key, $data, $window);
        }

        // Calculate retry_after (seconds until rate limit resets)
        $retryAfter = 0;
        if ($isLimited && isset($data['first_attempt'])) {
            $retryAfter = ($data['first_attempt'] + $window) - time();
            $retryAfter = max(0, $retryAfter);
        }

        return [
            'limited' => $isLimited,
            'attempts' => $data['count'],
            'remaining' => max(0, $maxAttempts - $data['count']),
            'retry_after' => $retryAfter,
        ];
    }

    /**
     * Clear rate limit for a specific action and identifier
     *
     * @param string $action     Action identifier
     * @param string $identifier Optional custom identifier
     */
    public static function clear(string $action, string $identifier = ''): void
    {
        $identifier = $identifier ?: self::getClientIP();
        $key = self::getKey($action, $identifier);
        delete_transient($key);
    }

    /**
     * Reset all rate limits for an identifier across all actions
     *
     * @param string $identifier Client identifier
     */
    public static function resetAll(string $identifier = ''): void
    {
        global $wpdb;

        $identifier = $identifier ?: self::getClientIP();
        $hash = substr(md5($identifier), 0, 12);
        $pattern = '_transient_' . self::TRANSIENT_PREFIX . '%_' . $hash;

        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            $pattern
        ));
    }

    /**
     * Get remaining attempts
     *
     * @param string $action      Action identifier
     * @param int    $maxAttempts Maximum attempts allowed
     * @param string $identifier  Optional custom identifier
     * @return int Remaining attempts
     */
    public static function getRemaining(
        string $action,
        int $maxAttempts = self::DEFAULT_MAX_ATTEMPTS,
        string $identifier = ''
    ): int {
        $identifier = $identifier ?: self::getClientIP();
        $key = self::getKey($action, $identifier);
        $data = get_transient($key);

        if ($data === false) {
            return $maxAttempts;
        }

        return max(0, $maxAttempts - $data['count']);
    }

    /**
     * Get current attempt count
     *
     * @param string $action     Action identifier
     * @param string $identifier Optional custom identifier
     * @return int Current attempt count
     */
    public static function getAttempts(string $action, string $identifier = ''): int
    {
        $identifier = $identifier ?: self::getClientIP();
        $key = self::getKey($action, $identifier);
        $data = get_transient($key);

        if ($data === false) {
            return 0;
        }

        return $data['count'];
    }

    /**
     * Generate transient key for rate limiting
     *
     * @param string $action     Action identifier
     * @param string $identifier Client identifier (IP, user ID, etc.)
     * @return string Transient key
     */
    private static function getKey(string $action, string $identifier): string
    {
        // Hash the identifier to prevent issues with special characters and long IPs (IPv6)
        $hash = md5($identifier);
        return self::TRANSIENT_PREFIX . sanitize_key($action) . '_' . substr($hash, 0, 12);
    }

    /**
     * Get client IP address
     *
     * @return string Client IP address
     */
    public static function getClientIP(): string
    {
        $ip = '';

        // Check for various proxy headers (in order of reliability)
        $headers = [
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_X_REAL_IP',            // Nginx proxy
            'HTTP_X_FORWARDED_FOR',      // Standard proxy header
            'REMOTE_ADDR',               // Direct connection
        ];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                break;
            }
        }

        // Handle comma-separated IPs (X-Forwarded-For can contain multiple)
        if (strpos($ip, ',') !== false) {
            $ips = explode(',', $ip);
            $ip = trim($ips[0]);
        }

        // Validate IP address
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
        }

        // Fallback to REMOTE_ADDR
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    /**
     * Get rate limit error response for JSON APIs
     *
     * @param int    $retryAfter Seconds until rate limit resets
     * @param string $message    Optional custom message
     * @return array Error response array
     */
    public static function getErrorResponse(int $retryAfter = 0, string $message = ''): array
    {
        if (empty($message)) {
            $message = __('Too many requests. Please try again later.', 'wpdm-premium-packages');
        }

        return [
            'status' => 'ERROR',
            'error' => 'RATE_LIMITED',
            'message' => $message,
            'retry_after' => $retryAfter,
        ];
    }

    /**
     * Send rate limit headers
     *
     * @param int $limit     Maximum requests allowed
     * @param int $remaining Remaining requests
     * @param int $reset     Timestamp when limit resets
     */
    public static function sendHeaders(int $limit, int $remaining, int $reset): void
    {
        if (!headers_sent()) {
            header('X-RateLimit-Limit: ' . $limit);
            header('X-RateLimit-Remaining: ' . $remaining);
            header('X-RateLimit-Reset: ' . $reset);
        }
    }

    /**
     * Middleware-style check that sends appropriate response if limited
     *
     * @param string $action      Action identifier
     * @param int    $maxAttempts Maximum attempts allowed
     * @param int    $window      Time window in seconds
     * @param bool   $sendJson    Whether to send JSON response if limited
     * @return bool True if allowed to proceed, false if limited
     */
    public static function throttle(
        string $action,
        int $maxAttempts = self::DEFAULT_MAX_ATTEMPTS,
        int $window = self::DEFAULT_WINDOW,
        bool $sendJson = true
    ): bool {
        $result = self::check($action, $maxAttempts, $window);

        // Send rate limit headers
        $reset = time() + $result['retry_after'];
        self::sendHeaders($maxAttempts, $result['remaining'], $reset);

        if ($result['limited']) {
            if ($sendJson) {
                wp_send_json_error(
                    self::getErrorResponse($result['retry_after']),
                    429
                );
            }
            return false;
        }

        return true;
    }
}
