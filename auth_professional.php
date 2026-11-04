<?php
require_once __DIR__ . '/includes/security.php';
init_secure_session();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || (
    $_SESSION['role'] !== 'professional' && $_SESSION['role'] !== 'professional_approved' && $_SESSION['role'] !== 'professional_pending')) {
    header('Location: ../login.html');
    exit();
}
?>
