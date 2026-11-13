<?php
/**
 * itr_preparation.php (Branch: simple)
 * Unified 1-Page Smart Tax Optimizer forwarder
 */
require_once "includes/security.php";
init_secure_session();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$fy = trim($_GET['fy'] ?? '');
$redirect_url = 'tax_planning.php' . ($fy ? '?fy=' . urlencode($fy) : '');
header('Location: ' . $redirect_url);
exit();
