<?php
session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || (
    $_SESSION['role'] !== 'professional' && $_SESSION['role'] !== 'professional_pending')) {
    header('Location: ../login.html');
    exit();
}
?>
