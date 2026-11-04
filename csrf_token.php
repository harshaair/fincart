<?php
/**
 * csrf_token.php
 * Public endpoint to fetch active CSRF token for static views / AJAX clients
 */
require_once __DIR__ . '/includes/security.php';
init_secure_session();

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

echo json_encode([
    'success'    => true,
    'csrf_token' => get_csrf_token()
]);

