<?php
include '../auth_admin.php';
include '../db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['id'])) {
    header('Location: professionals.php');
    exit();
}

$id = intval($_POST['id']);

// Set professional status to rejected
$stmt = $conn->prepare("UPDATE professionals SET status = 'rejected' WHERE id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();

// If professional has user_id, update their users.role back to 'user'
$stmt2 = $conn->prepare("SELECT user_id FROM professionals WHERE id = ?");
$stmt2->bind_param('i', $id);
$stmt2->execute();
$res = $stmt2->get_result()->fetch_assoc();
if ($res && $res['user_id']) {
    $uid = intval($res['user_id']);
    $upd = $conn->prepare("UPDATE users SET role = 'user' WHERE id = ?");
    $upd->bind_param('i', $uid);
    $upd->execute();
}

header('Location: professionals.php');
exit();
