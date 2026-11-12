<?php
require_once "includes/security.php";
init_secure_session();
include "db.php";

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$message = trim($_GET['msg'] ?? '');
$error = trim($_GET['err'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    enforce_csrf();
}

// Resolve active month (format YYYY-MM)
$selected_month = trim($_GET['month'] ?? '');
if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $selected_month)) {
    $selected_month = date('Y-m');
}
$active_month = $selected_month;
$active_month_time = strtotime($active_month . '-01');
$prev_month = date('Y-m', strtotime('-1 month', $active_month_time));
$next_month = date('Y-m', strtotime('+1 month', $active_month_time));
$month_display = date('F Y', $active_month_time);
$is_current_month = ($active_month === date('Y-m'));

// Handle copy budgets from previous month
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'copy_previous') {
    $target_month = trim($_POST['month_year'] ?? $active_month);
    if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $target_month)) {
        $target_month = $active_month;
    }
    $src_month = date('Y-m', strtotime('-1 month', strtotime($target_month . '-01')));

    $prev_sql = "SELECT category, limit_amount FROM budgets WHERE user_id = ? AND month_year = ?";
    $prev_stmt = $conn->prepare($prev_sql);
    $prev_stmt->bind_param("is", $user_id, $src_month);
    $prev_stmt->execute();
    $prev_res = $prev_stmt->get_result();

    $copied = 0;
    while ($prow = $prev_res->fetch_assoc()) {
        $cat = $prow['category'];
        $lim = (float)$prow['limit_amount'];

        $c_sql = "SELECT id FROM budgets WHERE user_id = ? AND category = ? AND month_year = ?";
        $c_stmt = $conn->prepare($c_sql);
        $c_stmt->bind_param("iss", $user_id, $cat, $target_month);
        $c_stmt->execute();
        if ($c_stmt->get_result()->num_rows > 0) {
            $u_sql = "UPDATE budgets SET limit_amount = ? WHERE user_id = ? AND category = ? AND month_year = ?";
            $u_stmt = $conn->prepare($u_sql);
            $u_stmt->bind_param("diss", $lim, $user_id, $cat, $target_month);
            $u_stmt->execute();
        } else {
            $i_sql = "INSERT INTO budgets (user_id, category, limit_amount, month_year) VALUES (?, ?, ?, ?)";
            $i_stmt = $conn->prepare($i_sql);
            $i_stmt->bind_param("isds", $user_id, $cat, $lim, $target_month);
            $i_stmt->execute();
        }
        $copied++;
    }

    if ($copied > 0) {
        $msg = "Successfully copied $copied category budget limit(s) from " . date('F Y', strtotime($src_month . '-01')) . "!";
        header("Location: budget.php?month=" . urlencode($target_month) . "&msg=" . urlencode($msg));
        exit();
    } else {
        $err = "No budgets found in " . date('F Y', strtotime($src_month . '-01')) . " to copy.";
        header("Location: budget.php?month=" . urlencode($target_month) . "&err=" . urlencode($err));
        exit();
    }
}

// Handle add/update budget
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_budget') {
    $category = trim($_POST['category'] ?? '');
    $limit = floatval($_POST['limit'] ?? 0);
    $month_year = trim($_POST['month_year'] ?? $active_month);
    if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month_year)) {
        $month_year = $active_month;
    }

    if (empty($category) || $limit <= 0) {
        $error = 'Please fill all fields with valid positive values.';
    } else {
        // Check if budget exists for this category in this specific month
        $check_sql = "SELECT id FROM budgets WHERE user_id = ? AND category = ? AND month_year = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("iss", $user_id, $category, $month_year);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows > 0) {
            $sql = "UPDATE budgets SET limit_amount = ? WHERE user_id = ? AND category = ? AND month_year = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("diss", $limit, $user_id, $category, $month_year);
        } else {
            $sql = "INSERT INTO budgets (user_id, category, limit_amount, month_year) VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("isds", $user_id, $category, $limit, $month_year);
        }

        if ($stmt->execute()) {
            $msg = 'Budget limit saved for ' . date('F Y', strtotime($month_year . '-01')) . '!';
            header("Location: budget.php?month=" . urlencode($month_year) . "&msg=" . urlencode($msg));
            exit();
        } else {
            $error = 'Failed to save budget limit. Please try again.';
        }
    }
}

// Handle delete budget
if (isset($_POST['delete_id'])) {
    $delete_id = intval($_POST['delete_id']);
    $sql = "DELETE FROM budgets WHERE id = ? AND user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $delete_id, $user_id);
    if ($stmt->execute()) {
        header("Location: budget.php?month=" . urlencode($active_month) . "&msg=" . urlencode('Budget limit deleted successfully!'));
        exit();
    } else {
        header("Location: budget.php?month=" . urlencode($active_month) . "&err=" . urlencode('Failed to delete budget limit.'));
        exit();
    }
}

// Fetch budgets for active month (with fallback for legacy records missing month_year)
$budgets_sql = "SELECT * FROM budgets WHERE user_id = ? AND (month_year = ? OR (month_year IS NULL AND ? = DATE_FORMAT(NOW(), '%Y-%m'))) ORDER BY category";
$budgets_stmt = $conn->prepare($budgets_sql);
$budgets_stmt->bind_param("iss", $user_id, $active_month, $active_month);
$budgets_stmt->execute();
$budgets_result = $budgets_stmt->get_result();

// Single aggregate query for category spent amounts in active month
$spent_map = [];
$spent_sql = "SELECT category, SUM(amount) as spent 
              FROM transactions 
              WHERE user_id = ? AND type = 'expense' AND DATE_FORMAT(date, '%Y-%m') = ? 
              GROUP BY category";
$spent_stmt = $conn->prepare($spent_sql);
$spent_stmt->bind_param("is", $user_id, $active_month);
$spent_stmt->execute();
$spent_res = $spent_stmt->get_result();
while ($srow = $spent_res->fetch_assoc()) {
    $spent_map[$srow['category']] = (float)$srow['spent'];
}

$budgets = [];
while ($row = $budgets_result->fetch_assoc()) {
    $cat = $row['category'];
    $spent = $spent_map[$cat] ?? 0.0;
    $row['spent'] = $spent;
    $row['percentage'] = $row['limit_amount'] > 0 ? min(($spent / (float)$row['limit_amount']) * 100, 100) : 0;
    $budgets[] = $row;
}

// Summary metrics
$total_budget = array_sum(array_column($budgets, 'limit_amount'));
$total_spent = array_sum(array_column($budgets, 'spent'));
$total_percentage = $total_budget > 0 ? min(($total_spent / $total_budget) * 100, 100) : 0;

$expense_categories = ['Food', 'Transportation', 'Entertainment', 'Utilities', 'Shopping', 'Healthcare', 'Education', 'Other Expense'];

$page_title = 'Budget Management - FinCart';

include "includes/header.php";
include "includes/navbar.php";
?>

<div class="main-content">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 15px;">
        <div>
            <h1 style="font-size: 28px; margin-bottom: 6px;">Budget Tracking & Limits</h1>
            <p style="color: var(--text-muted);">Set monthly category ceilings and monitor your real-time expenses for <strong><?php echo htmlspecialchars($month_display); ?></strong>.</p>
        </div>
        <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
            <a href="transactions.php" class="btn btn-secondary">Transactions &rarr;</a>
            <a href="analytics.php" class="btn btn-primary">Spending Analytics &rarr;</a>
        </div>
    </div>

    <!-- Month Navigation Bar -->
    <div style="background: #fff; border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 14px 20px; margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px; box-shadow: 0 2px 10px rgba(0,0,0,0.02);">
        <div style="display: flex; align-items: center; gap: 10px;">
            <a href="budget.php?month=<?php echo urlencode($prev_month); ?>" class="btn btn-sm btn-secondary" title="Previous Month">&larr; <?php echo date('M Y', strtotime($prev_month . '-01')); ?></a>
            
            <form method="GET" action="budget.php" style="margin: 0; display: inline-flex; align-items: center; gap: 8px;">
                <input type="month" name="month" value="<?php echo htmlspecialchars($active_month); ?>" onchange="this.form.submit()" style="padding: 6px 12px; border-radius: 6px; border: 1px solid #ccc; font-size: 13.5px; font-weight: 600; color: var(--primary);">
            </form>

            <a href="budget.php?month=<?php echo urlencode($next_month); ?>" class="btn btn-sm btn-secondary" title="Next Month"><?php echo date('M Y', strtotime($next_month . '-01')); ?> &rarr;</a>

            <?php if (!$is_current_month): ?>
                <a href="budget.php" class="btn btn-sm btn-outline" style="border: 1px dashed var(--accent); color: var(--primary);">Current Month</a>
            <?php endif; ?>
        </div>

        <div style="display: flex; align-items: center; gap: 10px;">
            <!-- Copy previous month budgets action -->
            <form method="POST" style="margin: 0;" onsubmit="return confirm('Copy all category budget limits from <?php echo date('F Y', strtotime($prev_month . '-01')); ?> to <?php echo htmlspecialchars($month_display); ?>?');">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="copy_previous">
                <input type="hidden" name="month_year" value="<?php echo htmlspecialchars($active_month); ?>">
                <button type="submit" class="btn btn-sm btn-secondary" style="font-size: 12.5px;">
                    📋 Copy Limits from <?php echo date('M Y', strtotime($prev_month . '-01')); ?>
                </button>
            </form>
        </div>
    </div>

    <?php if (!empty($message)): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <!-- Summary Stats -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-label">Budget Limit (<?php echo htmlspecialchars($month_display); ?>)</div>
            <div class="stat-value">₹<?php echo number_format($total_budget, 2); ?></div>
            <div class="stat-description">Allocated across <?php echo count($budgets); ?> categories</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Actual Spent (<?php echo htmlspecialchars($month_display); ?>)</div>
            <div class="stat-value" style="color: <?php echo $total_spent > $total_budget && $total_budget > 0 ? '#e74c3c' : 'inherit'; ?>;">
                ₹<?php echo number_format($total_spent, 2); ?>
            </div>
            <div class="stat-description">Total tracked expenses in <?php echo htmlspecialchars($month_display); ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Remaining Budget</div>
            <div class="stat-value" style="color: <?php echo ($total_budget - $total_spent) < 0 ? '#e74c3c' : '#27ae60'; ?>;">
                ₹<?php echo number_format(max(0, $total_budget - $total_spent), 2); ?>
            </div>
            <div class="stat-description"><?php echo ($total_budget - $total_spent) < 0 ? 'Over budget!' : 'Available to spend'; ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Overall Consumption</div>
            <div class="stat-value"><?php echo number_format($total_percentage, 1); ?>%</div>
            <div class="stat-description">
                <div style="width: 100%; height: 6px; background: #eee; border-radius: 4px; overflow: hidden; margin-top: 6px;">
                    <div style="height: 100%; width: <?php echo $total_percentage; ?>%; background: <?php echo $total_percentage > 90 ? '#e74c3c' : ($total_percentage > 70 ? '#f39c12' : '#27ae60'); ?>;"></div>
                </div>
            </div>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(360px, 1fr)); gap: 24px; margin-bottom: 30px;">
        <!-- Add/Update Budget Card -->
        <div class="card">
            <h2 style="font-size: 20px; margin-bottom: 8px;">Set Budget Limit</h2>
            <p style="font-size: 13px; color: var(--text-muted); margin-bottom: 18px;">Targeting: <strong><?php echo htmlspecialchars($month_display); ?></strong></p>
            <form method="POST">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="save_budget">
                <input type="hidden" name="month_year" value="<?php echo htmlspecialchars($active_month); ?>">
                
                <div class="form-group">
                    <label>Expense Category</label>
                    <select name="category" class="form-control" required>
                        <option value="">Select Category</option>
                        <?php foreach ($expense_categories as $cat): ?>
                            <option value="<?php echo htmlspecialchars($cat); ?>"><?php echo htmlspecialchars($cat); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Monthly Limit Amount (₹)</label>
                    <input type="number" step="100" min="1" name="limit" class="form-control" placeholder="e.g. 5000" required>
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%;">Save Budget for <?php echo htmlspecialchars($month_display); ?></button>
            </form>
        </div>

        <!-- Budget Visual Pie Chart -->
        <div class="card">
            <h2 style="font-size: 20px; margin-bottom: 20px;">Category Allocation Chart</h2>
            <?php if (!empty($budgets)): ?>
                <div style="height: 280px; position: relative;">
                    <canvas id="budgetChart"></canvas>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 60px 20px; color: #888;">
                    No category budgets defined yet. Create your first budget limit above.
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Category Progress Cards -->
    <div class="card">
        <h2 style="font-size: 20px; margin-bottom: 20px;">Category Limits Status</h2>
        <?php if (!empty($budgets)): ?>
            <div style="display: grid; gap: 16px;">
                <?php foreach ($budgets as $b): ?>
                    <div style="background: var(--off-white); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 18px; border-left: 5px solid <?php echo $b['percentage'] >= 100 ? '#e74c3c' : ($b['percentage'] >= 80 ? '#f39c12' : '#27ae60'); ?>;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; flex-wrap: wrap; gap: 10px;">
                            <div>
                                <h3 style="font-size: 17px; margin-bottom: 4px;"><?php echo htmlspecialchars($b['category']); ?></h3>
                                <span style="font-size: 13px; color: var(--text-muted);">
                                    Spent: <strong>₹<?php echo number_format($b['spent'], 2); ?></strong> of ₹<?php echo number_format((float)$b['limit_amount'], 2); ?>
                                </span>
                            </div>
                            <div style="display: flex; align-items: center; gap: 12px;">
                                <span style="font-weight: 700; font-size: 15px; color: <?php echo $b['percentage'] >= 100 ? '#e74c3c' : 'inherit'; ?>;">
                                    <?php echo number_format($b['percentage'], 1); ?>%
                                </span>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Remove budget for <?php echo htmlspecialchars($b['category']); ?>?');">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="delete_id" value="<?php echo (int)$b['id']; ?>">
                                    <button type="submit" class="btn btn-danger btn-sm">Remove</button>
                                </form>
                            </div>
                        </div>
                        <div style="width: 100%; height: 8px; background: #e0e0e0; border-radius: 6px; overflow: hidden;">
                            <div style="height: 100%; width: <?php echo $b['percentage']; ?>%; background: <?php echo $b['percentage'] >= 100 ? '#e74c3c' : ($b['percentage'] >= 80 ? '#f39c12' : '#27ae60'); ?>;"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div style="text-align: center; padding: 40px 20px; color: var(--text-muted);">
                <p style="font-size: 15px; margin-bottom: 14px;">No category budgets configured for <strong><?php echo htmlspecialchars($month_display); ?></strong>.</p>
                <form method="POST" style="display: inline-block;">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="copy_previous">
                    <input type="hidden" name="month_year" value="<?php echo htmlspecialchars($active_month); ?>">
                    <button type="submit" class="btn btn-secondary" style="font-size: 13.5px;">
                        📋 Copy Limits from <?php echo date('M Y', strtotime($prev_month . '-01')); ?> &rarr;
                    </button>
                </form>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($budgets)): ?>
<script>
(function() {
    function initBudgetChart() {
        const elem = document.getElementById('budgetChart');
        if (!elem) return;
        const categories = <?php echo json_encode(array_column($budgets, 'category')); ?>;
        const limits = <?php echo json_encode(array_column($budgets, 'limit_amount')); ?>;

        const chartConfig = {
            type: 'pie',
            data: {
                labels: categories,
                datasets: [{
                    data: limits,
                    backgroundColor: [
                        '#d4af37', '#1a1a1a', '#27ae60', '#3498db',
                        '#e74c3c', '#9b59b6', '#f39c12', '#1abc9c'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { boxWidth: 12, font: { size: 11 } }
                    }
                }
            }
        };

        if (window.renderChartSafe) {
            window.renderChartSafe(elem, chartConfig);
        } else if (typeof Chart !== 'undefined') {
            new Chart(elem, chartConfig);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initBudgetChart);
    } else {
        initBudgetChart();
    }
})();
</script>
<?php endif; ?>

<?php include "includes/footer.php"; ?>
