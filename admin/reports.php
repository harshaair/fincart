<?php
include '../auth_admin.php';
include '../db.php';

$page_title = 'System Reports - FinCart Admin';

// Aggregate counts
$counts = [];
$tables = ['users', 'professionals', 'transactions', 'budgets', 'goals', 'investments', 'appointments'];
foreach ($tables as $t) {
    $res = $conn->query("SELECT COUNT(*) AS c FROM `" . $t . "`");
    $counts[$t] = ($res) ? (int)$res->fetch_assoc()['c'] : 0;
}

// Financial volumes
$income_sum = 0;
$res = $conn->query("SELECT SUM(amount) as s FROM transactions WHERE type = 'income'");
if ($res && $row = $res->fetch_assoc()) { $income_sum = (float)($row['s'] ?? 0); }

$expense_sum = 0;
$res = $conn->query("SELECT SUM(amount) as s FROM transactions WHERE type = 'expense'");
if ($res && $row = $res->fetch_assoc()) { $expense_sum = (float)($row['s'] ?? 0); }

// Latest transactions sample
$tx = $conn->query("SELECT t.id, t.user_id, u.full_name, t.type, t.category, t.amount, t.date 
                    FROM transactions t 
                    LEFT JOIN users u ON t.user_id = u.id 
                    ORDER BY t.created_at DESC LIMIT 15");

include '../includes/admin_header.php';
include '../includes/admin_navbar.php';
?>

<div class="admin-container">
    <div class="admin-header-row">
        <div>
            <h1 class="admin-title">System Activity & Performance Reports</h1>
            <p class="admin-subtitle">Live health status, platform metrics, and recent transaction audit trail.</p>
        </div>
        <div>
            <a href="dashboard.php" class="btn btn-secondary btn-sm">← Back← Back to Dashboard</a>
        </div>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-label">Total Users</div>
            <div class="stat-value"><?php echo number_format($counts['users']); ?></div>
            <div class="stat-description">Registered customer accounts</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Total Platform Volume</div>
            <div class="stat-value">₹<?php echo number_format($income_sum + $expense_sum, 2); ?></div>
            <div class="stat-description">Combined tracked finances</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Booked Appointments</div>
            <div class="stat-value"><?php echo number_format($counts['appointments']); ?></div>
            <div class="stat-description">Consultation sessions</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Active Portfolios</div>
            <div class="stat-value"><?php echo number_format($counts['investments']); ?></div>
            <div class="stat-description">Investment holdings recorded</div>
        </div>
    </div>

    <div class="admin-card">
        <h2 style="font-size: 20px; margin-bottom: 16px;">Latest Platform Transactions</h2>
        <div class="table-responsive">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th style="width: 50px;">Tx ID</th>
                        <th>User</th>
                        <th>Type</th>
                        <th>Category</th>
                        <th>Amount</th>
                        <th>Transaction Date</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($tx && $tx->num_rows > 0): ?>
                    <?php while ($r = $tx->fetch_assoc()): ?>
                        <tr>
                            <td>#<?php echo (int)$r['id']; ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($r['full_name'] ?? 'User #' . $r['user_id']); ?></strong>
                            </td>
                            <td>
                                <?php if ($r['type'] === 'income'): ?>
                                    <span class="badge badge-success">&#8593; Income</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">&#8595; Expense</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($r['category'] ?? 'General'); ?></td>
                            <td><strong>₹<?php echo number_format((float)$r['amount'], 2); ?></strong></td>
                            <td><?php echo date('d-M-Y', strtotime($r['date'])); ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 30px; color: #888;">
                            No transaction records recorded yet.
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../includes/admin_footer.php'; ?>
