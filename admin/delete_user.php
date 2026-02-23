<?php
include '../auth_admin.php';
include '../db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['id'])) {
    header('Location: users.php');
    exit();
}

$id = intval($_POST['id']);

// Delete user
$stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();

// Note: professionals.user_id has FK ON DELETE SET NULL, so related professional entries will be unlinked.

header('Location: users.php');
exit();
