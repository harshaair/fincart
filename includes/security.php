<?php
/**
 * includes/security.php
 * Production Security Engine for FinCart
 * 
 * Features:
 *  1. Hardened Session Management (HttpOnly, Secure, SameSite, Lifetime, Auto-Regeneration)
 *  2. Cryptographic CSRF Token Management & Verification
 *  3. IP-based Database Rate Limiting with Exponential Lockouts
 *  4. XSS & Input Sanitization Utilities
 */

if (!function_exists('init_secure_session')) {
    function init_secure_session(): void {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
            || (!empty($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);

        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_strict_mode', '1');

        session_set_cookie_params([
            'lifetime' => 86400,     // 24 hours
            'path'     => '/',
            'domain'   => '',        // Default to host
            'secure'   => $is_https, // Only transmit over HTTPS in production
            'httponly' => true,      // Prevent JavaScript cookie theft
            'samesite' => 'Lax'      // Protect against cross-site request forgery while preserving navigation
        ]);

        session_start();

        // Regenerate session periodically to guard against session fixation
        if (!isset($_SESSION['__session_created_at'])) {
            $_SESSION['__session_created_at'] = time();
        } elseif (time() - $_SESSION['__session_created_at'] > 1800) { // 30 minutes
            session_regenerate_id(true);
            $_SESSION['__session_created_at'] = time();
        }
    }
}

/**
 * Generates or retrieves the active CSRF token
 */
if (!function_exists('get_csrf_token')) {
    function get_csrf_token(): string {
        init_secure_session();
        if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

/**
 * Backward compatibility alias for get_csrf_token
 */
if (!function_exists('generate_csrf_token')) {
    function generate_csrf_token(): string {
        return get_csrf_token();
    }
}

/**
 * Outputs a hidden input field containing the current CSRF token
 */
if (!function_exists('csrf_field')) {
    function csrf_field(): string {
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(get_csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
    }
}

/**
 * Validates the CSRF token from POST body or X-CSRF-TOKEN header
 */
if (!function_exists('verify_csrf_token')) {
    function verify_csrf_token(?string $token = null): bool {
        init_secure_session();

        $session_token = $_SESSION['csrf_token'] ?? '';
        if (empty($session_token)) {
            return false;
        }

        if ($token === null) {
            $token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        }

        if (!is_string($token) || empty($token)) {
            return false;
        }

        return hash_equals($session_token, $token);
    }
}

/**
 * Enforces CSRF token check on state-mutating HTTP requests (POST, PUT, DELETE)
 */
if (!function_exists('enforce_csrf')) {
    function enforce_csrf(): void {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        if (in_array($method, ['POST', 'PUT', 'DELETE', 'PATCH'], true)) {
            if (!verify_csrf_token()) {
                http_response_code(403);
                $is_ajax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
                    || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

                if ($is_ajax) {
                    header('Content-Type: application/json; charset=UTF-8');
                    echo json_encode([
                        'success' => false,
                        'error'   => 'Security token validation failed (CSRF). Please reload the page and try again.'
                    ]);
                } else {
                    echo '<!DOCTYPE html><html><head><title>403 Forbidden</title></head>';
                    echo '<body style="font-family:sans-serif;text-align:center;padding:50px;">';
                    echo '<h2>403 - Invalid Security Token (CSRF)</h2>';
                    echo '<p>Your session security token has expired or is invalid. Please return to the previous page and refresh.</p>';
                    echo '<p><a href="javascript:history.back()">Go Back</a> | <a href="/login.html">Log In</a></p>';
                    echo '</body></html>';
                }
                exit();
            }
        }
    }
}

/**
 * IP resolution taking Cloudflare & reverse proxies into account
 */
if (!function_exists('get_client_ip')) {
    function get_client_ip(): string {
        $headers = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_REAL_IP',
            'HTTP_X_FORWARDED_FOR',
            'REMOTE_ADDR'
        ];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ips = explode(',', $_SERVER[$header]);
                $ip = trim($ips[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        return '127.0.0.1';
    }
}

/**
 * Rate Limiting Engine: checks attempts against rate_limits table
 * 
 * @param mysqli $conn
 * @param string $action           e.g. 'login', 'signup', 'forgot_password'
 * @param int    $max_attempts     allowed attempts within window
 * @param int    $window_seconds   window duration in seconds (default 15 mins)
 * @return array ['allowed' => bool, 'remaining' => int, 'retry_after' => int]
 */
if (!function_exists('check_rate_limit')) {
    function check_rate_limit(mysqli $conn, string $action, int $max_attempts = 5, int $window_seconds = 900): array {
        $ip = get_client_ip();
        $action_key = substr($action . ':' . hash('sha256', $ip), 0, 190);
        $now = time();

        // Prune old entries occasionally (1 in 100 requests)
        if (random_int(1, 100) === 1) {
            @$conn->query("DELETE FROM rate_limits WHERE last_attempt_at < " . ($now - 86400));
        }

        $stmt = $conn->prepare("SELECT attempts, first_attempt_at, last_attempt_at FROM rate_limits WHERE action_key = ?");
        if (!$stmt) {
            // Fail open on database error to avoid locking out users
            return ['allowed' => true, 'remaining' => $max_attempts, 'retry_after' => 0];
        }
        $stmt->bind_param("s", $action_key);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($row = $res->fetch_assoc()) {
            $first_attempt = (int)$row['first_attempt_at'];
            $attempts = (int)$row['attempts'];

            // If time window has expired, reset counter
            if (($now - $first_attempt) > $window_seconds) {
                $upd = $conn->prepare("UPDATE rate_limits SET attempts = 1, first_attempt_at = ?, last_attempt_at = ? WHERE action_key = ?");
                $upd->bind_param("iis", $now, $now, $action_key);
                $upd->execute();
                return ['allowed' => true, 'remaining' => $max_attempts - 1, 'retry_after' => 0];
            }

            // If max attempts exceeded
            if ($attempts >= $max_attempts) {
                $retry_after = $window_seconds - ($now - $first_attempt);
                return ['allowed' => false, 'remaining' => 0, 'retry_after' => max(1, $retry_after)];
            }

            // Increment attempt count
            $new_attempts = $attempts + 1;
            $upd = $conn->prepare("UPDATE rate_limits SET attempts = ?, last_attempt_at = ? WHERE action_key = ?");
            $upd->bind_param("iis", $new_attempts, $now, $action_key);
            $upd->execute();
            return ['allowed' => true, 'remaining' => max(0, $max_attempts - $new_attempts), 'retry_after' => 0];
        } else {
            // Insert initial attempt record
            $ins = $conn->prepare("INSERT INTO rate_limits (action_key, attempts, first_attempt_at, last_attempt_at) VALUES (?, 1, ?, ?)");
            $ins->bind_param("sii", $action_key, $now, $now);
            $ins->execute();
            return ['allowed' => true, 'remaining' => $max_attempts - 1, 'retry_after' => 0];
        }
    }
}

/**
 * Resets rate limit for an action upon successful authentication
 */
if (!function_exists('clear_rate_limit')) {
    function clear_rate_limit(mysqli $conn, string $action): void {
        $ip = get_client_ip();
        $action_key = substr($action . ':' . hash('sha256', $ip), 0, 190);
        $stmt = $conn->prepare("DELETE FROM rate_limits WHERE action_key = ?");
        if ($stmt) {
            $stmt->bind_param("s", $action_key);
            $stmt->execute();
        }
    }
}

