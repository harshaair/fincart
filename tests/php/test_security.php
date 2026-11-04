<?php
/**
 * tests/php/test_security.php
 * Automated Unit Tests for FinCart Security Engine
 */

require_once __DIR__ . '/../../includes/security.php';

$tests_passed = 0;
$tests_failed = 0;

function assert_true($condition, $description) {
    global $tests_passed, $tests_failed;
    if ($condition) {
        echo "  [PASS] $description\n";
        $tests_passed++;
    } else {
        echo "  [FAIL] $description\n";
        $tests_failed++;
    }
}

echo "Running Security Suite:\n";

// 1. Session and CSRF Token Generation
init_secure_session();
$token1 = get_csrf_token();
assert_true(!empty($token1) && strlen($token1) === 64, "CSRF token is 64-character hex string (256-bit entropy)");

$token2 = get_csrf_token();
assert_true($token1 === $token2, "get_csrf_token() is idempotent within same session");

// 2. CSRF Verification
assert_true(verify_csrf_token($token1), "verify_csrf_token() succeeds with matching token");
assert_true(!verify_csrf_token("invalid_token_xyz"), "verify_csrf_token() rejects invalid token");
assert_true(!verify_csrf_token(""), "verify_csrf_token() rejects empty token");
assert_true(!verify_csrf_token(null), "verify_csrf_token() rejects null token when POST is empty");

// 3. CSRF Input Field Generator
$field = csrf_field();
assert_true(strpos($field, 'type="hidden"') !== false && strpos($field, $token1) !== false, "csrf_field() renders valid hidden input tag with active token");

// 4. Client IP Resolution
$_SERVER['REMOTE_ADDR'] = '203.0.113.195';
assert_true(get_client_ip() === '203.0.113.195', "get_client_ip() falls back to REMOTE_ADDR correctly");

$_SERVER['HTTP_CF_CONNECTING_IP'] = '198.51.100.42';
assert_true(get_client_ip() === '198.51.100.42', "get_client_ip() prioritizes Cloudflare connecting IP");
unset($_SERVER['HTTP_CF_CONNECTING_IP']);

echo "Security Suite completed: $tests_passed passed, $tests_failed failed.\n\n";

if ($tests_failed > 0) {
    exit(1);
}
