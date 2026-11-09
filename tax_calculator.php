<?php
// Unified tax module: redirect all calculator traffic to tax_planning.php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

header('Location: tax_planning.php');
exit();
