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

// Get current month and year
$current_month = date('Y-m');
$current_year = date('Y');

// Filter parameters
$filter_month = isset($_GET['month']) ? $_GET['month'] : $current_month;
$filter_year = isset($_GET['year']) ? $_GET['year'] : $current_year;

// Monthly Income/Expense Trend (Last 12 months)
$trend_sql = "SELECT 
    DATE_FORMAT(date, '%Y-%m') as month,
    SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) as income,
    SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) as expense
FROM transactions 
WHERE user_id = ? AND date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
GROUP BY DATE_FORMAT(date, '%Y-%m')
ORDER BY month ASC";

$trend_stmt = $conn->prepare($trend_sql);
$trend_stmt->bind_param("i", $user_id);
$trend_stmt->execute();
$trend_result = $trend_stmt->get_result();
$trend_data = [];

while ($row = $trend_result->fetch_assoc()) {
    $trend_data[] = $row;
}

// Expense breakdown by category for selected month
$expense_breakdown_sql = "SELECT 
    category,
    SUM(amount) as total
FROM transactions 
WHERE user_id = ? AND type = 'expense' AND DATE_FORMAT(date, '%Y-%m') = ?
GROUP BY category
ORDER BY total DESC";

$expense_stmt = $conn->prepare($expense_breakdown_sql);
$expense_stmt->bind_param("is", $user_id, $filter_month);
$expense_stmt->execute();
$expense_result = $expense_stmt->get_result();
$expense_data = [];

while ($row = $expense_result->fetch_assoc()) {
    $expense_data[] = $row;
}

// Overall stats for selected month
$stats_sql = "SELECT 
    SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) as total_income,
    SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) as total_expense,
    COUNT(*) as total_transactions
FROM transactions 
WHERE user_id = ? AND DATE_FORMAT(date, '%Y-%m') = ?";

$stats_stmt = $conn->prepare($stats_sql);
$stats_stmt->bind_param("is", $user_id, $filter_month);
$stats_stmt->execute();
$stats_result = $stats_stmt->get_result();
$stats = $stats_result->fetch_assoc();

$total_income = floatval($stats['total_income'] ?? 0);
$total_expense = floatval($stats['total_expense'] ?? 0);
$net_balance = $total_income - $total_expense;
$total_transactions = intval($stats['total_transactions'] ?? 0);

// Budget vs Spending comparison
$budget_vs_spending_sql = "SELECT 
    b.category,
    b.limit_amount as budget,
    COALESCE(SUM(t.amount), 0) as spent
FROM budgets b
LEFT JOIN transactions t ON b.user_id = t.user_id 
    AND b.category = t.category 
    AND t.type = 'expense'
    AND DATE_FORMAT(t.date, '%Y-%m') = ?
WHERE b.user_id = ? AND (b.month_year = ? OR b.month_year IS NULL OR b.month_year = '')
GROUP BY b.category, b.limit_amount
ORDER BY b.category";

$budget_stmt = $conn->prepare($budget_vs_spending_sql);
$budget_stmt->bind_param("sis", $filter_month, $user_id, $filter_month);
$budget_stmt->execute();
$budget_result = $budget_stmt->get_result();
$budget_data = [];

while ($row = $budget_result->fetch_assoc()) {
    $budget_data[] = $row;
}

require_once "includes/components/stat_card.php";
require_once "includes/components/page_header.php";

$page_title = 'Financial Analytics & Insights - FinCart';

include "includes/header.php";
include "includes/navbar.php";
?>

<main class="page-container" style="max-width: 1240px; margin: 0 auto; padding: 20px 24px;">
    <?php
    render_page_header([
        'title'    => 'Financial Analytics & Trends',
        'subtitle' => '12-Month cash flow trajectories, category spending distribution, and budget discipline',
        'badge'    => date('F Y', strtotime($filter_month . '-01')),
        'actions'  => [
            ['label' => 'Ledger Transactions →', 'href' => 'transactions.php', 'class' => 'btn-outline'],
            ['label' => 'Tax Projections →', 'href' => 'tax_planning.php', 'class' => 'btn-outline']
        ]
    ]);
    ?>

    <!-- Month Filter Bar -->
    <div class="card-luxury" style="margin-bottom: 24px;">
        <div class="card-luxury-body" style="padding: 16px 20px;">
            <form method="GET" style="display: flex; gap: 14px; flex-wrap: wrap; align-items: flex-end;">
                <div style="flex: 1; min-width: 200px;">
                    <label style="font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em; color: var(--text-muted); display: block; margin-bottom: 6px;">Select Analysis Month</label>
                    <input type="month" name="month" class="input-luxury input-luxury-no-icon" value="<?php echo htmlspecialchars($filter_month); ?>">
                </div>
                <div style="display: flex; gap: 8px;">
                    <button type="submit" class="btn-luxury btn-gold" style="padding: 10px 20px;">Apply Filter</button>
                    <a href="analytics.php" class="btn-luxury btn-outline" style="padding: 10px 18px;">Reset to Current</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Monthly Summary Metric Cards -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 18px; margin-bottom: 28px;">
        <?php
        render_stat_card([
            'title'      => 'Period Inflow',
            'value'      => '₹' . number_format($total_income, 2),
            'icon'       => '↗️',
            'trend'      => 'Gross Income',
            'trend_type' => 'positive',
            'subtitle'   => 'Selected month'
        ]);

        render_stat_card([
            'title'      => 'Period Outflow',
            'value'      => '₹' . number_format($total_expense, 2),
            'icon'       => '↘️',
            'trend'      => ($total_income > 0 ? number_format(($total_expense / $total_income) * 100, 1) . '% of Income' : 'Expenses'),
            'trend_type' => ($total_income > 0 && ($total_expense / $total_income) > 0.75) ? 'negative' : 'positive',
            'subtitle'   => 'Discretionary & fixed'
        ]);

        render_stat_card([
            'title'      => 'Net Retained',
            'value'      => ($net_balance >= 0 ? '+' : '') . '₹' . number_format($net_balance, 2),
            'icon'       => '⚖️',
            'trend'      => $net_balance >= 0 ? 'Surplus Saved' : 'Deficit',
            'trend_type' => $net_balance >= 0 ? 'positive' : 'negative',
            'subtitle'   => 'Net monthly liquidity'
        ]);

        render_stat_card([
            'title'      => 'Audited Volume',
            'value'      => (string)$total_transactions,
            'icon'       => '📑',
            'trend'      => 'Line Entries',
            'trend_type' => 'neutral',
            'subtitle'   => 'Processed transactions'
        ]);
        ?>
    </div>

    <!-- 12-Month Cash Flow Trend Card -->
    <div class="card-luxury" style="margin-bottom: 28px;">
        <div class="card-luxury-header">
            <h2 class="card-luxury-title">12-Month Inflow vs Outflow Trajectory</h2>
            <span class="badge-luxury badge-luxury-gold">Macro Trend</span>
        </div>
        <div class="card-luxury-body">
            <?php if (!empty($trend_data)): ?>
                <div style="position: relative; width: 100%; height: 320px;">
                    <canvas id="trendChart"></canvas>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 48px; color: var(--text-muted);">
                    <span style="font-size: 36px; display: block; margin-bottom: 8px;">📊</span>
                    <p>No historical trend data found. Record recurring income and expense transactions to unlock trajectory analytics.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Category Breakdown + Budget Discipline Layout -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(340px, 1fr)); gap: 24px; margin-bottom: 28px;">
        <!-- Category Breakdown -->
        <div class="card-luxury">
            <div class="card-luxury-header">
                <h2 class="card-luxury-title">Expense Breakdown by Category</h2>
                <span class="badge-luxury badge-luxury-gold"><?php echo htmlspecialchars($filter_month); ?></span>
            </div>
            <div class="card-luxury-body" style="display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 280px;">
                <?php if (!empty($expense_data)): ?>
                    <div style="position: relative; width: 100%; height: 260px;">
                        <canvas id="categoryChart"></canvas>
                    </div>
                <?php else: ?>
                    <div style="text-align: center; color: var(--text-muted); padding: 40px 10px;">
                        <span style="font-size: 36px; display: block; margin-bottom: 8px;">🍩</span>
                        <p style="font-size: 14px;">No expense records found for this period.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Budget Discipline Status -->
        <div class="card-luxury">
            <div class="card-luxury-header">
                <h2 class="card-luxury-title">Budget Ceiling vs Actual Outflow</h2>
                <span class="badge-luxury badge-luxury-gold">Discipline Track</span>
            </div>
            <div class="card-luxury-body">
                <?php if (!empty($budget_data)): ?>
                    <div style="display: flex; flex-direction: column; gap: 16px;">
                        <?php foreach ($budget_data as $b): 
                            $bLimit = floatval($b['budget']);
                            $bSpent = floatval($b['spent']);
                            $bPct = $bLimit > 0 ? min(100, ($bSpent / $bLimit) * 100) : 0;
                            $isOver = $bSpent > $bLimit;
                            $barColor = $isOver ? '#ef4444' : ($bPct > 80 ? '#f59e0b' : '#10b981');
                        ?>
                            <div>
                                <div style="display: flex; justify-content: space-between; font-size: 13.5px; margin-bottom: 6px;">
                                    <strong><?php echo htmlspecialchars($b['category']); ?></strong>
                                    <span>₹<?php echo number_format($bSpent, 0); ?> / ₹<?php echo number_format($bLimit, 0); ?></span>
                                </div>
                                <div style="width: 100%; height: 8px; background: #e5e5e0; border-radius: 999px; overflow: hidden;">
                                    <div style="width: <?php echo min(100, $bPct); ?>%; height: 100%; background: <?php echo $barColor; ?>; border-radius: 999px;"></div>
                                </div>
                                <div style="display: flex; justify-content: space-between; font-size: 11.5px; color: var(--text-muted); margin-top: 4px;">
                                    <span><?php echo number_format($bPct, 1); ?>% allocated</span>
                                    <span style="color: <?php echo $barColor; ?>; font-weight: 600;"><?php echo $isOver ? 'Exceeded Budget!' : 'Within Safe Range'; ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div style="text-align: center; color: var(--text-muted); padding: 40px 10px;">
                        <span style="font-size: 36px; display: block; margin-bottom: 8px;">🛡️</span>
                        <p style="font-size: 14px; margin-bottom: 12px;">No budget envelopes created for this period.</p>
                        <a href="budget.php" class="btn-luxury btn-outline" style="font-size: 12.5px;">+ Define Monthly Budgets</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<script>
<?php if (!empty($trend_data)): ?>
(function() {
    const ctx = document.getElementById('trendChart');
    if (!ctx) return;
    const labels = <?php echo json_encode(array_column($trend_data, 'month')); ?>;
    const income = <?php echo json_encode(array_map('floatval', array_column($trend_data, 'income'))); ?>;
    const expense = <?php echo json_encode(array_map('floatval', array_column($trend_data, 'expense'))); ?>;

    const chartConfig = {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Inflow (₹)',
                    data: income,
                    backgroundColor: '#10b981',
                    borderRadius: 4
                },
                {
                    label: 'Outflow (₹)',
                    data: expense,
                    backgroundColor: '#ef4444',
                    borderRadius: 4
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                    labels: { font: { family: "'Inter', sans-serif" } }
                }
            },
            scales: {
                x: { grid: { display: false } },
                y: { grid: { color: 'rgba(0,0,0,0.05)' } }
            }
        }
    };

    if (window.renderChartSafe) {
        window.renderChartSafe(ctx, chartConfig);
    } else if (typeof Chart !== 'undefined') {
        new Chart(ctx, chartConfig);
    }
})();
<?php endif; ?>

<?php if (!empty($expense_data)): ?>
(function() {
    const ctx = document.getElementById('categoryChart');
    if (!ctx) return;
    const labels = <?php echo json_encode(array_column($expense_data, 'category')); ?>;
    const values = <?php echo json_encode(array_map('floatval', array_column($expense_data, 'total'))); ?>;

    const chartConfig = {
        type: 'doughnut',
        data: {
            labels: labels,
            datasets: [{
                data: values,
                backgroundColor: [
                    '#d4af37', '#ef4444', '#3b82f6', '#f59e0b',
                    '#10b981', '#8b5cf6', '#ec4899', '#06b6d4'
                ],
                borderColor: '#ffffff',
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { font: { family: "'Inter', sans-serif", size: 12 }, padding: 12 }
                }
            }
        }
    };

    if (window.renderChartSafe) {
        window.renderChartSafe(ctx, chartConfig);
    } else if (typeof Chart !== 'undefined') {
        new Chart(ctx, chartConfig);
    }
})();
<?php endif; ?>
</script>

<?php include "includes/footer.php"; ?>
