<?php
include '../auth_admin.php';
include '../db.php';

// Add 'verified' column if missing when an action is performed
function ensure_verified_column($conn) {
    $check = $conn->query("SHOW COLUMNS FROM users LIKE 'verified'");
    if ($check->num_rows == 0) {
        $conn->query("ALTER TABLE users ADD COLUMN verified TINYINT(1) NOT NULL DEFAULT 0");
    }
}

if (isset($_GET['action']) && $_GET['action'] === 'verify' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    ensure_verified_column($conn);
    $stmt = $conn->prepare("UPDATE users SET verified = 1 WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    header('Location: verify_user.php');
    exit();
}

// Fetch users and their verified status if column exists
$hasVerified = false;
$colCheck = $conn->query("SHOW COLUMNS FROM users LIKE 'verified'");
if ($colCheck && $colCheck->num_rows > 0) {
    $hasVerified = true;
}

$result = $conn->query("SELECT id, full_name, email" . ($hasVerified ? ", verified" : "") . " FROM users ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Verify Users - Admin</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
    <header class="site-header">
        <div class="logo"><h1>Fincart — Verify Users</h1></div>
        <div class="header-actions">
            <a href="dashboard.php" class="btn btn-secondary">Back to dashboard</a>
            <a href="../logout.php" class="btn btn-primary">Logout</a>
        </div>
    </header>
    <div class="container">
        <div class="card">
            <h2>Users</h2>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <?php if ($hasVerified) echo '<th>Verified</th>'; ?>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
<?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo $row['id']; ?></td>
                        <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                        <td><?php echo htmlspecialchars($row['email']); ?></td>
                        <?php if ($hasVerified): ?>
                        <td><?php echo $row['verified'] ? 'Yes' : 'No'; ?></td>
                        <?php endif; ?>
                        <td>
                            <?php if (!$hasVerified || !$row['verified']): ?>
                                <a class="btn btn-primary" href="verify_user.php?action=verify&id=<?php echo $row['id']; ?>">Verify</a>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                    </tr>
<?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
