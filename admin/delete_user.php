<?php
include '../auth_admin.php';
include '../db.php';
enforce_csrf();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['id'])) {
    header('Location: users.php');
    exit();
}

$id = intval($_POST['id']);

// Self-deletion guard: cannot delete active session admin
if ($id === (int)$_SESSION['user_id']) {
    header('Location: users.php?error=' . urlencode('Self-deletion forbidden: You cannot delete your own active administrator account.'));
    exit();
}

// Delete user
$stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
$stmt->bind_param('i', $id);
if ($stmt->execute()) {
    header('Location: users.php?msg=' . urlencode("User account #$id removed successfully."));
} else {
    header('Location: users.php?error=' . urlencode("Failed to delete user account #$id."));
}
exit();
