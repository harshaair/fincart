<?php
include '../auth_admin.php';
include '../db.php';

if (!isset($_GET['id'])) {
    header('Location: users.php');
    exit();
}

$id = intval($_GET['id']);
$stmt = $conn->prepare("SELECT id, full_name, email, role FROM users WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
if (!$user) {
    header('Location: users.php');
    exit();
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Edit User</title>
</head>
<body>
    <h1>Edit User #<?php echo $user['id']; ?></h1>
    <form method="post" action="update_user.php">
        <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
        <label>Full name: <input name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" required></label><br>
        <label>Email: <input name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required></label><br>
        <label>Role:
            <select name="role">
                <option value="user" <?php echo $user['role']==='user' ? 'selected' : ''; ?>>User</option>
                <option value="professional_pending" <?php echo $user['role']==='professional_pending' ? 'selected' : ''; ?>>Professional (Pending)</option>
                <option value="professional" <?php echo $user['role']==='professional' ? 'selected' : ''; ?>>Professional (Active)</option>
                <option value="admin" <?php echo $user['role']==='admin' ? 'selected' : ''; ?>>Admin</option>
            </select>
        </label><br>
        <button type="submit">Save</button>
    </form>
    <p><a href="users.php">← Back</a></p>
</body>
</html>
