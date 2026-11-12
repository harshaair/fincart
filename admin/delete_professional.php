<?php
include '../auth_admin.php';
include '../db.php';
enforce_csrf();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['id'])) {
    header('Location: professionals.php');
    exit();
}

$id = intval($_POST['id']);

// Get user_id before deleting
$stmt0 = $conn->prepare("SELECT user_id FROM professionals WHERE id = ? LIMIT 1");
$stmt0->bind_param('i', $id);
$stmt0->execute();
$r = $stmt0->get_result()->fetch_assoc();
$user_id = $r ? intval($r['user_id']) : null;

// Delete professional record
$stmt = $conn->prepare("DELETE FROM professionals WHERE id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();

// If linked user existed, reset role to 'user'
if ($user_id) {
    $upd = $conn->prepare("UPDATE users SET role = 'user' WHERE id = ?");
    $upd->bind_param('i', $user_id);
    $upd->execute();
}

header('Location: professionals.php?msg=' . urlencode('Professional listing deleted successfully.'));
exit();
