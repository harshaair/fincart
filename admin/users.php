<?php
include '../auth_admin.php';
include '../db.php';

$sql = "SELECT id, full_name, email, role, created_at FROM users ORDER BY created_at DESC";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Manage Users</title>
    <style>
        body { font-family: 'Inter', sans-serif; background: linear-gradient(135deg,#fafafa,#fff); color:#222; margin:0; }
        .site-header{background:#fff;padding:18px 24px;box-shadow:0 2px 12px rgba(0,0,0,0.06);display:flex;justify-content:space-between;align-items:center}
        .site-header .logo{font-family:'Playfair Display', serif;font-size:22px;color:#1a1a1a}
        .site-header a{color:#1a1a1a;text-decoration:none;margin-left:12px}
        .container{max-width:1100px;margin:28px auto;padding:20px}
        .card{background:#fff;border-radius:12px;padding:24px;box-shadow:0 10px 30px rgba(0,0,0,0.04)}
        table{width:100%;border-collapse:collapse}
        th,td{padding:12px 10px;border-bottom:1px solid #f2f2f2;text-align:left}
        a.btn{display:inline-block;padding:6px 10px;border-radius:6px;background:#f5f5f7;color:#111;text-decoration:none;border:1px solid rgba(0,0,0,0.06)}
    </style>
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
    <header class="site-header">
        <div class="logo">Fincart — Admin</div>
        <div>
            <a href="dashboard.php">Dashboard</a>
            <a href="../logout.php">Logout</a>
        </div>
    </header>
    <div class="container">
    <div class="card">
    <h1>Users</h1>
    <a href="dashboard.php">← Back</a>
    <table border="1" cellpadding="6" cellspacing="0">
        <tr><th>ID</th><th>Name</th><th>Email</th><th>Role</th><th>Created</th><th>Actions</th></tr>
        <?php while ($row = $result->fetch_assoc()): ?>
            <tr>
                <td><?php echo $row['id']; ?></td>
                <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                <td><?php echo htmlspecialchars($row['email']); ?></td>
                <td><?php echo $row['role']; ?></td>
                <td><?php echo $row['created_at']; ?></td>
                <td>
                    <a href="edit_user.php?id=<?php echo $row['id']; ?>">Edit</a>
                    |
                    <form method="post" action="delete_user.php" style="display:inline" onsubmit="return confirm('Delete user <?php echo htmlspecialchars($row['full_name']); ?>?');">
                        <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                        <button type="submit">Delete</button>
                    </form>
                </td>
            </tr>
        <?php endwhile; ?>
    </table>
    </div>
    </div>
</body>
</html>
