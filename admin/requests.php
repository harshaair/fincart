<?php
include '../auth_admin.php';
include '../db.php';

$sql = "SELECT pa.*, u.email AS user_email, u.full_name AS user_name FROM professional_applications pa LEFT JOIN users u ON pa.user_id = u.id WHERE pa.status = 'pending' ORDER BY pa.created_at DESC";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Professional Applications</title>
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
    <h1>Pending Professional Applications</h1>
    <a href="dashboard.php">← Back</a>
    <table border="1" cellpadding="6" cellspacing="0">
        <tr><th>ID</th><th>User Email</th><th>User Name</th><th>Professional Type</th><th>Experience</th><th>Applied</th><th>Actions</th></tr>
        <?php while ($row = $result->fetch_assoc()): ?>
            <tr>
                <td><?php echo $row['id']; ?></td>
                <td><?php echo htmlspecialchars($row['user_email'] ?? 'N/A'); ?></td>
                <td><?php echo htmlspecialchars($row['user_name'] ?? 'N/A'); ?></td>
                <td><?php echo htmlspecialchars($row['professional_type']); ?></td>
                <td><?php echo htmlspecialchars($row['experience_years'] ?? 'N/A'); ?> years</td>
                <td><?php echo date('d-M-Y H:i', strtotime($row['created_at'])); ?></td>
                <td>
                    <form method="post" action="verify_professional.php" style="display:inline">
                        <input type="hidden" name="application_id" value="<?php echo $row['id']; ?>">
                        <input type="hidden" name="user_id" value="<?php echo $row['user_id']; ?>">
                        <button type="submit" class="btn btn-success">Approve</button>
                    </form>
                    <form method="post" action="reject_professional.php" style="display:inline" onsubmit="return confirm('Reject this application?');">
                        <input type="hidden" name="application_id" value="<?php echo $row['id']; ?>">
                        <button type="submit" class="btn btn-danger">Reject</button>
                    </form>
                </td>
            </tr>
        <?php endwhile; ?>
    </table>
    </div>
    </div>
</body>
</html>
