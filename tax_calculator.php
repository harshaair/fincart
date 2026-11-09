<?php
// Unified tax module: redirect all calculator traffic to tax_planning.php
require_once "includes/security.php";
init_secure_session();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

header('Location: tax_planning.php');
exit();
