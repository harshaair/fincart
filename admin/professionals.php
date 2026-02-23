<?php
include '../auth_admin.php';
include '../db.php';

$sql = "SELECT p.*, u.email AS user_email, u.full_name AS user_name FROM professionals p LEFT JOIN users u ON p.user_id = u.id ORDER BY p.created_at DESC";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Manage Professionals</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
    <header class="site-header">
        <div class="logo">Fincart — Admin</div>
        <div>
            <a href="dashboard.php">Dashboard</a>
            <a href="requests.php">Requests</a>
            <a href="../logout.php">Logout</a>
        </div>
    </header>
    <div class="container">
    <div class="card">
    <h1>Professionals</h1>
    <a href="dashboard.php">← Back</a>
    <table border="1" cellpadding="6" cellspacing="0">
        <tr><th>ID</th><th>Name</th><th>Type</th><th>User Account</th><th>Status</th><th>Actions</th></tr>
        <?php while ($row = $result->fetch_assoc()): ?>
            <tr>
                <td><?php echo $row['id']; ?></td>
                <td><?php echo htmlspecialchars($row['name']); ?></td>
                <td><?php echo htmlspecialchars($row['professional_type']); ?></td>
                <td><?php echo $row['user_id'] ? htmlspecialchars($row['user_name'] . ' (' . $row['user_email'] . ')') : '—'; ?></td>
                <td><?php echo htmlspecialchars($row['status']); ?></td>
                <td>
                    <?php if ($row['status'] !== 'active'): ?>
                        <form method="post" action="verify_professional.php" style="display:inline">
                            <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                            <button type="submit">Approve</button>
                        </form>
                        <form method="post" action="reject_professional.php" style="display:inline" onsubmit="return confirm('Reject this professional application?');">
                            <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                            <button type="submit">Reject</button>
                        </form>
                    <?php else: ?>
                        <form method="post" action="revoke_professional.php" style="display:inline" onsubmit="return confirm('Revoke professional status?');">
                            <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                            <button type="submit">Revoke</button>
                        </form>
                    <?php endif; ?>
                    <form method="post" action="delete_professional.php" style="display:inline" onsubmit="return confirm('Delete this professional record?');">
                        <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                        <button type="submit">Delete</button>
                    </form>
                    <a href="../admin/edit_user.php?id=<?php echo $row['user_id']; ?>">Edit User</a>
                </td>
            </tr>
        <?php endwhile; ?>
    </table>
    </div>
    </div>
</body>
</html>
