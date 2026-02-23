<?php
include '../auth_admin.php';
include '../db.php';

// Simple reports: counts for quick review
$counts = [];
$tables = ['users', 'professionals', 'transactions'];
foreach ($tables as $t) {
    $res = $conn->query("SELECT COUNT(*) AS c FROM `" . $t . "`");
    if ($res) {
        $r = $res->fetch_assoc();
        $counts[$t] = $r['c'];
    } else {
        $counts[$t] = 0;
    }
}

// Latest transactions sample
$tx = $conn->query("SELECT id, user_id, type, category, amount, date FROM transactions ORDER BY created_at DESC LIMIT 10");
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Admin Reports</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
    <header class="site-header">
        <div class="logo"><h1>Fincart — Reports</h1></div>
        <div class="header-actions">
            <a href="dashboard.php" class="btn btn-secondary">Back to dashboard</a>
            <a href="../logout.php" class="btn btn-primary">Logout</a>
        </div>
    </header>
    <div class="container">
        <div class="card">
            <h2>Quick Overview</h2>
            <ul>
                <li>Total users: <?php echo $counts['users']; ?></li>
                <li>Total professionals: <?php echo $counts['professionals']; ?></li>
                <li>Total transactions: <?php echo $counts['transactions']; ?></li>
            </ul>

            <h3>Latest Transactions</h3>
            <?php if ($tx && $tx->num_rows > 0): ?>
            <table class="admin-table">
                <thead>
                    <tr><th>ID</th><th>User</th><th>Type</th><th>Category</th><th>Amount</th><th>Date</th></tr>
                </thead>
                <tbody>
                <?php while ($r = $tx->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo $r['id']; ?></td>
                        <td><?php echo $r['user_id']; ?></td>
                        <td><?php echo $r['type']; ?></td>
                        <td><?php echo $r['category']; ?></td>
                        <td><?php echo $r['amount']; ?></td>
                        <td><?php echo $r['date']; ?></td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
            <?php else: ?>
                <p>No transactions found.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
