<?php
// Become a Professional - Unified with FinCart Expert Portal
$qs = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '?role=professional&tab=signup';
if (!str_contains($qs, 'role=professional')) {
    $qs .= '&role=professional&tab=signup';
}
header("Location: login.php" . $qs);
exit();
