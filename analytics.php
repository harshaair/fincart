<?php
session_start();
include "db.php";

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.html');
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

// Income breakdown by category for selected month
$income_breakdown_sql = "SELECT 
    category,
    SUM(amount) as total
FROM transactions 
WHERE user_id = ? AND type = 'income' AND DATE_FORMAT(date, '%Y-%m') = ?
GROUP BY category
ORDER BY total DESC";

$income_stmt = $conn->prepare($income_breakdown_sql);
$income_stmt->bind_param("is", $user_id, $filter_month);
$income_stmt->execute();
$income_result = $income_stmt->get_result();
$income_data = [];

while ($row = $income_result->fetch_assoc()) {
    $income_data[] = $row;
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

$total_income = $stats['total_income'] ?? 0;
$total_expense = $stats['total_expense'] ?? 0;
$net_balance = $total_income - $total_expense;
$total_transactions = $stats['total_transactions'] ?? 0;

// Yearly comparison
$yearly_stats_sql = "SELECT 
    DATE_FORMAT(date, '%Y') as year,
    SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) as income,
    SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) as expense
FROM transactions 
WHERE user_id = ?
GROUP BY YEAR(date)
ORDER BY year DESC
LIMIT 2";

$yearly_stmt = $conn->prepare($yearly_stats_sql);
$yearly_stmt->bind_param("i", $user_id);
$yearly_stmt->execute();
$yearly_result = $yearly_stmt->get_result();
$yearly_data = [];

while ($row = $yearly_result->fetch_assoc()) {
    $yearly_data[] = $row;
}

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
WHERE b.user_id = ?
GROUP BY b.category, b.limit_amount
ORDER BY b.category";

$budget_stmt = $conn->prepare($budget_vs_spending_sql);
$budget_stmt->bind_param("si", $filter_month, $user_id);
$budget_stmt->execute();
$budget_result = $budget_stmt->get_result();
$budget_data = [];

while ($row = $budget_result->fetch_assoc()) {
    $budget_data[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financial Analytics - FinCart</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: #1a1a1a;
            --secondary: #f8f6f3;
            --accent: #d4af37;
            --text-dark: #2c2c2c;
            --text-light: #666;
            --white: #ffffff;
            --off-white: #fafaf9;
            --success: #27ae60;
            --danger: #e74c3c;
            --warning: #f39c12;
            --info: #3498db;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, var(--off-white) 0%, var(--secondary) 100%);
            color: var(--text-dark);
            min-height: 100vh;
        }

        header {
            position: sticky;
            top: 0;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            box-shadow: 0 2px 20px rgba(0, 0, 0, 0.05);
            z-index: 1000;
            padding: 20px 0;
        }

        .header-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo h1 {
            font-family: 'Playfair Display', serif;
            font-size: 28px;
            font-weight: 700;
            background: linear-gradient(135deg, var(--primary) 0%, var(--accent) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            letter-spacing: -1px;
        }

        .nav-links {
            display: flex;
            gap: 30px;
        }

        .nav-link {
            color: var(--text-dark);
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .nav-link:hover {
            color: var(--accent);
        }

        .logout-btn {
            padding: 8px 20px;
            background: var(--danger);
            color: var(--white);
            border: none;
            border-radius: 20px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .logout-btn:hover {
            opacity: 0.9;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 40px 20px;
        }

        .page-header {
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }

        .page-header h1 {
            font-family: 'Playfair Display', serif;
            font-size: 42px;
            color: var(--primary);
            letter-spacing: -1px;
        }

        .filter-controls {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }

        input[type="month"],
        input[type="number"] {
            padding: 10px 16px;
            border: 1px solid rgba(0, 0, 0, 0.1);
            border-radius: 10px;
            font-size: 14px;
            font-family: 'Inter', sans-serif;
            transition: all 0.3s ease;
        }

        input:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.1);
        }

        .card {
            background: var(--white);
            padding: 30px;
            border-radius: 20px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            margin-bottom: 30px;
        }

        .card h2 {
            font-family: 'Playfair Display', serif;
            font-size: 24px;
            color: var(--primary);
            margin-bottom: 25px;
            letter-spacing: -0.5px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: var(--off-white);
            padding: 25px;
            border-radius: 15px;
            border-left: 5px solid var(--accent);
        }

        .stat-card.positive {
            border-left-color: var(--success);
        }

        .stat-card.negative {
            border-left-color: var(--danger);
        }

        .stat-label {
            color: var(--text-light);
            font-size: 14px;
            margin-bottom: 10px;
            font-weight: 500;
        }

        .stat-value {
            font-family: 'Playfair Display', serif;
            font-size: 32px;
            font-weight: 700;
            color: var(--primary);
        }

        .stat-change {
            font-size: 12px;
            color: var(--text-light);
            margin-top: 8px;
        }

        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 30px;
            margin-bottom: 30px;
        }

        .chart-container {
            position: relative;
            height: 350px;
        }

        .full-width-chart {
            grid-column: 1 / -1;
            height: 350px;
        }

        .breakdown-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
        }

        .breakdown-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        }

        .breakdown-item:last-child {
            border-bottom: none;
        }

        .breakdown-category {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .breakdown-category-name {
            color: var(--primary);
            font-weight: 600;
        }

        .breakdown-amount {
            font-family: 'Playfair Display', serif;
            font-size: 18px;
            font-weight: 700;
            color: var(--primary);
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--text-light);
        }

        .comparison-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .comparison-table th,
        .comparison-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        }

        .comparison-table th {
            background: var(--off-white);
            font-weight: 700;
            color: var(--primary);
        }

        .comparison-table tr:hover {
            background: rgba(212, 175, 55, 0.05);
        }

        @media (max-width: 768px) {
            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .charts-grid {
                grid-template-columns: 1fr;
            }

            .breakdown-grid {
                grid-template-columns: 1fr;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header>
        <div class="header-container">
            <div class="logo">
                <h1>Fincart</h1>
            </div>
            <div class="nav-links">
                <a href="dashboard.php" class="nav-link">Dashboard</a>
                <a href="transactions.php" class="nav-link">Transactions</a>
                <a href="profile.php" class="nav-link">Settings</a>
                <form method="POST" action="logout.php" style="display: inline;">
                    <button type="submit" class="logout-btn">Logout</button>
                </form>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <div class="container">
        <div class="page-header">
            <div>
                <h1>Financial Analytics</h1>
                <p style="color: var(--text-light); margin-top: 5px;">Comprehensive overview of your financial performance</p>
            </div>
            <div class="filter-controls">
                <form method="GET" style="display: flex; gap: 15px;">
                    <input type="month" name="month" value="<?php echo $filter_month; ?>" onchange="this.form.submit();">
                    <button type="submit" style="padding: 10px 20px; background: var(--primary); color: var(--white); border: none; border-radius: 10px; cursor: pointer; font-weight: 600;">Apply</button>
                </form>
            </div>
        </div>

        <!-- Key Metrics -->
        <div class="card">
            <h2>Monthly Summary - <?php echo date('F Y', strtotime($filter_month . '-01')); ?></h2>
            <div class="stats-grid">
                <div class="stat-card positive">
                    <div class="stat-label">Total Income</div>
                    <div class="stat-value">₹<?php echo number_format($total_income, 2); ?></div>
                </div>
                <div class="stat-card negative">
                    <div class="stat-label">Total Expense</div>
                    <div class="stat-value">₹<?php echo number_format($total_expense, 2); ?></div>
                </div>
                <div class="stat-card <?php echo $net_balance >= 0 ? 'positive' : 'negative'; ?>">
                    <div class="stat-label">Net Balance</div>
                    <div class="stat-value"><?php echo ($net_balance >= 0 ? '+' : ''); ?>₹<?php echo number_format($net_balance, 2); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Transactions</div>
                    <div class="stat-value"><?php echo $total_transactions; ?></div>
                </div>
            </div>
        </div>

        <!-- Charts Section -->
        <div class="charts-grid">
            <!-- Trend Chart -->
            <div class="card full-width-chart">
                <h2>12-Month Income vs Expense Trend</h2>
                <div class="chart-container">
                    <canvas id="trendChart"></canvas>
                </div>
            </div>

            <!-- Expense Breakdown -->
            <div class="card">
                <h2>Expense Distribution</h2>
                <div class="chart-container">
                    <canvas id="expenseChart"></canvas>
                </div>
            </div>

            <!-- Income Breakdown -->
            <div class="card">
                <h2>Income Sources</h2>
                <div class="chart-container">
                    <canvas id="incomeChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Detailed Breakdown -->
        <div class="breakdown-grid">
            <!-- Expense Breakdown Details -->
            <div class="card">
                <h2>Top Expenses</h2>
                <?php if (count($expense_data) > 0): ?>
                    <?php foreach ($expense_data as $expense): ?>
                        <div class="breakdown-item">
                            <div class="breakdown-category">
                                <span class="breakdown-category-name"><?php echo htmlspecialchars($expense['category']); ?></span>
                            </div>
                            <div class="breakdown-amount">₹<?php echo number_format($expense['total'], 2); ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state" style="padding: 20px;">
                        <p>No expenses for this month</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Income Breakdown Details -->
            <div class="card">
                <h2>Income Sources</h2>
                <?php if (count($income_data) > 0): ?>
                    <?php foreach ($income_data as $income): ?>
                        <div class="breakdown-item">
                            <div class="breakdown-category">
                                <span class="breakdown-category-name"><?php echo htmlspecialchars($income['category']); ?></span>
                            </div>
                            <div class="breakdown-amount">₹<?php echo number_format($income['total'], 2); ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state" style="padding: 20px;">
                        <p>No income for this month</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Budget vs Spending Comparison -->
        <?php if (count($budget_data) > 0): ?>
            <div class="card">
                <h2>Budget vs Actual Spending</h2>
                <table class="comparison-table">
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th>Budget</th>
                            <th>Spent</th>
                            <th>Remaining</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($budget_data as $item): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($item['category']); ?></td>
                                <td>₹<?php echo number_format($item['budget'], 2); ?></td>
                                <td>₹<?php echo number_format($item['spent'], 2); ?></td>
                                <td>₹<?php echo number_format($item['budget'] - $item['spent'], 2); ?></td>
                                <td>
                                    <?php 
                                    if ($item['spent'] <= $item['budget']) {
                                        echo '<span style="color: var(--success); font-weight: 600;">✓ Within Budget</span>';
                                    } else {
                                        echo '<span style="color: var(--danger); font-weight: 600;">⚠ Over Budget</span>';
                                    }
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <!-- Yearly Comparison -->
        <?php if (count($yearly_data) > 1): ?>
            <div class="card">
                <h2>Year-over-Year Comparison</h2>
                <table class="comparison-table">
                    <thead>
                        <tr>
                            <th>Year</th>
                            <th>Total Income</th>
                            <th>Total Expense</th>
                            <th>Net Savings</th>
                            <th>Savings Rate</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($yearly_data as $year): ?>
                            <tr>
                                <td><strong><?php echo $year['year']; ?></strong></td>
                                <td>₹<?php echo number_format($year['income'], 2); ?></td>
                                <td>₹<?php echo number_format($year['expense'], 2); ?></td>
                                <td>₹<?php echo number_format($year['income'] - $year['expense'], 2); ?></td>
                                <td><?php echo round(((($year['income'] - $year['expense']) / $year['income']) * 100), 1); ?>%</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Trend Chart - 12 months
        const trendData = <?php echo json_encode($trend_data); ?>;
        
        if (trendData.length > 0) {
            const trendCtx = document.getElementById('trendChart');
            new Chart(trendCtx, {
                type: 'line',
                data: {
                    labels: trendData.map(d => {
                        const date = new Date(d.month + '-01');
                        return date.toLocaleDateString('en-US', { month: 'short', year: '2-digit' });
                    }),
                    datasets: [
                        {
                            label: 'Income',
                            data: trendData.map(d => parseFloat(d.income) || 0),
                            borderColor: '#27ae60',
                            backgroundColor: 'rgba(39, 174, 96, 0.1)',
                            borderWidth: 3,
                            fill: true,
                            tension: 0.4,
                            pointRadius: 5,
                            pointBackgroundColor: '#27ae60'
                        },
                        {
                            label: 'Expense',
                            data: trendData.map(d => parseFloat(d.expense) || 0),
                            borderColor: '#e74c3c',
                            backgroundColor: 'rgba(231, 76, 60, 0.1)',
                            borderWidth: 3,
                            fill: true,
                            tension: 0.4,
                            pointRadius: 5,
                            pointBackgroundColor: '#e74c3c'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: { font: { size: 14 }, padding: 20 }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { callback: (v) => '₹' + v.toLocaleString() }
                        }
                    }
                }
            });
        }

        // Expense Chart
        const expenseData = <?php echo json_encode($expense_data); ?>;
        
        if (expenseData.length > 0) {
            const expenseCtx = document.getElementById('expenseChart');
            new Chart(expenseCtx, {
                type: 'doughnut',
                data: {
                    labels: expenseData.map(e => e.category),
                    datasets: [{
                        data: expenseData.map(e => parseFloat(e.total)),
                        backgroundColor: [
                            '#e74c3c', '#f39c12', '#3498db', '#9b59b6',
                            '#1abc9c', '#34495e', '#d4af37', '#c0392b'
                        ],
                        borderColor: '#ffffff',
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'bottom', labels: { font: { size: 12 }, padding: 15 } } }
                }
            });
        }

        // Income Chart
        const incomeData = <?php echo json_encode($income_data); ?>;
        
        if (incomeData.length > 0) {
            const incomeCtx = document.getElementById('incomeChart');
            new Chart(incomeCtx, {
                type: 'doughnut',
                data: {
                    labels: incomeData.map(i => i.category),
                    datasets: [{
                        data: incomeData.map(i => parseFloat(i.total)),
                        backgroundColor: [
                            '#27ae60', '#2ecc71', '#16a085', '#1abc9c',
                            '#3498db', '#2980b9', '#8e44ad', '#d4af37'
                        ],
                        borderColor: '#ffffff',
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'bottom', labels: { font: { size: 12 }, padding: 15 } } }
                }
            });
        }
    </script>
</body>
</html>
