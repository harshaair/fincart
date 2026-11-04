<?php
include '../auth_admin.php';
include '../db.php';
enforce_csrf();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: users.php');
    exit();
}

$id = intval($_POST['id']);
$full_name = trim($_POST['full_name'] ?? '');
$email = trim($_POST['email'] ?? '');
$role = $_POST['role'] ?? 'user';
$verified = isset($_POST['verified']) ? 1 : 0;

$stmt = $conn->prepare("UPDATE users SET full_name = ?, email = ?, role = ?, verified = ? WHERE id = ?");
$stmt->bind_param('sssii', $full_name, $email, $role, $verified, $id);
$stmt->execute();

// If role was changed to professional, ensure there's a professionals record or update its status
if ($role === 'professional') {
    $stmt2 = $conn->prepare("SELECT id FROM professionals WHERE user_id = ? LIMIT 1");
    $stmt2->bind_param('i', $id);
    $stmt2->execute();
    $res = $stmt2->get_result()->fetch_assoc();
    if ($res) {
        $stmt3 = $conn->prepare("UPDATE professionals SET status = 'active' WHERE id = ?");
        $stmt3->bind_param('i', $res['id']);
        $stmt3->execute();
    } else {
        $ins = $conn->prepare("INSERT INTO professionals (user_id, professional_type, name, status) VALUES (?, 'Unknown', ?, 'active')");
        $name = $full_name;
        $ins->bind_param('is', $id, $name);
        $ins->execute();
    }
}

header('Location: users.php?msg=updated');
exit();
