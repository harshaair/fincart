<?php
include '../auth_admin.php';
include '../db.php';
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
    <header class="site-header">
        <div class="logo"><h1>Fincart — Admin</h1></div>
        <div class="header-actions">
            <a href="../index.html" class="btn btn-secondary">Back to site</a>
            <a href="../logout.php" class="btn btn-primary">Logout</a>
        </div>
    </header>
    <div class="container">
        <div class="card">
            <h1>Admin Dashboard</h1>
            <p>Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?></p>
            <div class="admin-links">
                <a href="users.php" class="btn btn-secondary">Manage Users</a>
                <a href="verify_user.php" class="btn btn-secondary">Verify Users</a>
                <a href="professionals.php" class="btn btn-secondary">Manage Professionals</a>
                <a href="requests.php" class="btn btn-secondary">Professional Requests</a>
                <a href="reports.php" class="btn btn-secondary">Review Reports</a>
            </div>
        </div>
    </div>
</body>
</html>
