<?php
session_start();
include "db.php";

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.html');
    exit();
}

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Handle add/update budget
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_budget') {
    $category = trim($_POST['category'] ?? '');
    $limit = floatval($_POST['limit'] ?? 0);

    if (empty($category) || $limit <= 0) {
        $error = 'Please fill all fields with valid positive values.';
    } else {
        // Check if budget exists for this category
        $check_sql = "SELECT id FROM budgets WHERE user_id = ? AND category = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("is", $user_id, $category);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows > 0) {
            $sql = "UPDATE budgets SET limit_amount = ? WHERE user_id = ? AND category = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("dis", $limit, $user_id, $category);
        } else {
            $sql = "INSERT INTO budgets (user_id, category, limit_amount, month_year) VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $month_year = date('Y-m');
            $stmt->bind_param("isds", $user_id, $category, $limit, $month_year);
        }

        if ($stmt->execute()) {
            $message = 'Budget limit saved successfully!';
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
        $message = 'Budget limit deleted successfully!';
    }
}

// Fetch all budgets for user
$budgets_sql = "SELECT * FROM budgets WHERE user_id = ? ORDER BY category";
$budgets_stmt = $conn->prepare($budgets_sql);
$budgets_stmt->bind_param("i", $user_id);
$budgets_stmt->execute();
$budgets_result = $budgets_stmt->get_result();

// OPTIMIZATION: Single aggregate query for category spent amounts (eliminates N+1 loop)
$current_month = date('Y-m');
$spent_map = [];
$spent_sql = "SELECT category, SUM(amount) as spent 
              FROM transactions 
              WHERE user_id = ? AND type = 'expense' AND DATE_FORMAT(date, '%Y-%m') = ? 
              GROUP BY category";
$spent_stmt = $conn->prepare($spent_sql);
$spent_stmt->bind_param("is", $user_id, $current_month);
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
$extra_css = '<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>';

include "includes/header.php";
include "includes/navbar.php";
?>

<div class="main-content">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 15px;">
        <div>
            <h1 style="font-size: 28px; margin-bottom: 6px;">Budget Tracking & Limits</h1>
            <p style="color: var(--text-muted);">Set monthly category ceilings and monitor your real-time expenses for <?php echo date('F Y'); ?>.</p>
        </div>
        <div>
            <a href="transactions.php" class="btn btn-secondary">Transactions &rarr;</a>
            <a href="analytics.php" class="btn btn-primary">Spending Analytics &rarr;</a>
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
            <div class="stat-label">Total Monthly Budget</div>
            <div class="stat-value">₹<?php echo number_format($total_budget, 2); ?></div>
            <div class="stat-description">Allocated across <?php echo count($budgets); ?> categories</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Actual Spent This Month</div>
            <div class="stat-value" style="color: <?php echo $total_spent > $total_budget && $total_budget > 0 ? '#e74c3c' : 'inherit'; ?>;">
                ₹<?php echo number_format($total_spent, 2); ?>
            </div>
            <div class="stat-description">Total tracked expenses in <?php echo date('M Y'); ?></div>
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
            <h2 style="font-size: 20px; margin-bottom: 20px;">Set Monthly Budget Limit</h2>
            <form method="POST">
                <input type="hidden" name="action" value="save_budget">
                
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

                <button type="submit" class="btn btn-primary" style="width: 100%;">Save Budget Ceiling</button>
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
            <p style="color: #888; text-align: center; padding: 30px;">No category budgets configured yet.</p>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($budgets)): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('budgetChart').getContext('2d');
    const categories = <?php echo json_encode(array_column($budgets, 'category')); ?>;
    const limits = <?php echo json_encode(array_column($budgets, 'limit_amount')); ?>;

    new Chart(ctx, {
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
    });
});
</script>
<?php endif; ?>

<?php include "includes/footer.php"; ?>
