<?php
session_start();
include "db.php";

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Get user information
$user_id = $_SESSION['user_id'];
$sql = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    session_destroy();
    header('Location: login.php');
    exit();
}

$_SESSION['role'] = $user['role'];
$_SESSION['user_name'] = $user['full_name'] ?? 'User';

// Role-based redirection
if ($user['role'] === 'professional') {
    header("Location: professional/dashboard.php");
    exit();
}

if ($user['role'] === 'admin') {
    header("Location: admin/dashboard.php");
    exit();
}

// Get current month and year
$current_month = date('Y-m');
$last_month = date('Y-m', strtotime('-1 month'));

// Calculate Total Balance (all time: income - expenses)
$balance_sql = "SELECT 
    COALESCE(SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END), 0) as total_income,
    COALESCE(SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END), 0) as total_expense
    FROM transactions WHERE user_id = ?";
$balance_stmt = $conn->prepare($balance_sql);
$balance_stmt->bind_param("i", $user_id);
$balance_stmt->execute();
$balance_result = $balance_stmt->get_result();
$balance_data = $balance_result->fetch_assoc();
$total_balance = $balance_data['total_income'] - $balance_data['total_expense'];

// Get balance up to end of last month for comparison
$last_month_end = date('Y-m-t', strtotime('-1 month'));
$last_month_balance_sql = "SELECT 
    COALESCE(SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END), 0) as total_income,
    COALESCE(SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END), 0) as total_expense
    FROM transactions WHERE user_id = ? AND date <= ?";
$last_month_balance_stmt = $conn->prepare($last_month_balance_sql);
$last_month_balance_stmt->bind_param("is", $user_id, $last_month_end);
$last_month_balance_stmt->execute();
$last_month_balance_result = $last_month_balance_stmt->get_result();
$last_month_balance_data = $last_month_balance_result->fetch_assoc();
$last_month_balance = $last_month_balance_data['total_income'] - $last_month_balance_data['total_expense'];

// Calculate balance change percentage
$balance_change = 0;
if ($last_month_balance != 0) {
    $balance_change = (($total_balance - $last_month_balance) / abs($last_month_balance)) * 100;
} elseif ($last_month_balance == 0 && $total_balance != 0) {
    $balance_change = 100;
}

// Get Monthly Income (current month)
$monthly_income_sql = "SELECT COALESCE(SUM(amount), 0) as total FROM transactions 
    WHERE user_id = ? AND type = 'income' AND DATE_FORMAT(date, '%Y-%m') = ?";
$monthly_income_stmt = $conn->prepare($monthly_income_sql);
$monthly_income_stmt->bind_param("is", $user_id, $current_month);
$monthly_income_stmt->execute();
$monthly_income_result = $monthly_income_stmt->get_result();
$monthly_income_data = $monthly_income_result->fetch_assoc();
$monthly_income = $monthly_income_data['total'];

// Get Monthly Expenses (current month)
$monthly_expense_sql = "SELECT COALESCE(SUM(amount), 0) as total FROM transactions 
    WHERE user_id = ? AND type = 'expense' AND DATE_FORMAT(date, '%Y-%m') = ?";
$monthly_expense_stmt = $conn->prepare($monthly_expense_sql);
$monthly_expense_stmt->bind_param("is", $user_id, $current_month);
$monthly_expense_stmt->execute();
$monthly_expense_result = $monthly_expense_stmt->get_result();
$monthly_expense_data = $monthly_expense_result->fetch_assoc();
$monthly_expense = $monthly_expense_data['total'];

// Calculate Savings Rate
$savings_rate = 0;
if ($monthly_income > 0) {
    $savings_rate = (($monthly_income - $monthly_expense) / $monthly_income) * 100;
}

// Format values
$total_balance_formatted = number_format($total_balance, 0);
$monthly_income_formatted = number_format($monthly_income, 0);
$monthly_expense_formatted = number_format($monthly_expense, 0);
$savings_rate_formatted = number_format($savings_rate, 1);

$balance_change_formatted = number_format(abs($balance_change), 1);
$balance_change_sign = $balance_change >= 0 ? '+' : '-';

$first_name = htmlspecialchars(explode(' ', $user['full_name'])[0]);

require_once "includes/components/stat_card.php";
require_once "includes/components/page_header.php";

$page_title = "Executive Dashboard - FinCart";
include "includes/header.php";
include "includes/navbar.php";
?>

<main class="page-container" style="max-width: 1240px; margin: 0 auto; padding: 20px 24px;">
    <?php
    render_page_header([
        'title'    => "Welcome back, {$first_name}",
        'subtitle' => 'Executive wealth monitoring, cash flow tracking, and automated tax analytics',
        'badge'    => 'Live Portfolio',
        'actions'  => [
            ['label' => '+ Add Transaction', 'href' => 'add_transaction.php', 'class' => 'btn-gold'],
            ['label' => 'Tax Planning', 'href' => 'tax_planning.php', 'class' => 'btn-outline'],
            ['label' => 'Find CA / Expert', 'href' => 'professionals.php', 'class' => 'btn-outline']
        ]
    ]);
    ?>

    <!-- Primary Metric Cards (Standardized Reusable Components) -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 20px; margin-bottom: 32px;">
        <?php
        render_stat_card([
            'title'      => 'Net Liquidity',
            'value'      => '₹' . $total_balance_formatted,
            'icon'       => '💎',
            'trend'      => $balance_change_sign . $balance_change_formatted . '%',
            'trend_type' => $balance_change >= 0 ? 'positive' : 'negative',
            'subtitle'   => 'vs previous month',
            'badge'      => 'Cash Flow'
        ]);

        render_stat_card([
            'title'      => 'Monthly Income',
            'value'      => '₹' . $monthly_income_formatted,
            'icon'       => '📈',
            'trend'      => date('M Y'),
            'trend_type' => 'neutral',
            'subtitle'   => 'All credited streams',
            'badge'      => 'Inflow'
        ]);

        render_stat_card([
            'title'      => 'Monthly Outflow',
            'value'      => '₹' . $monthly_expense_formatted,
            'icon'       => '💳',
            'trend'      => ($monthly_income > 0 ? number_format(($monthly_expense / $monthly_income) * 100, 1) . '% of income' : 'Active'),
            'trend_type' => ($monthly_income > 0 && ($monthly_expense / $monthly_income) > 0.7) ? 'negative' : 'positive',
            'subtitle'   => 'Discretionary & fixed',
            'badge'      => 'Expenses'
        ]);

        render_stat_card([
            'title'      => 'Savings Efficiency',
            'value'      => $savings_rate_formatted . '%',
            'icon'       => '🛡️',
            'trend'      => ($savings_rate >= 30 ? 'Target Achieved' : 'Optimization Available'),
            'trend_type' => $savings_rate >= 30 ? 'positive' : 'neutral',
            'subtitle'   => 'Retention ratio',
            'badge'      => 'Goal Track'
        ]);
        ?>
    </div>

    <!-- Quick Navigation Hub -->
    <div class="card-luxury">
        <div class="card-luxury-header">
            <h2 class="card-luxury-title">Financial Command Center</h2>
            <span class="badge-luxury badge-luxury-gold">Multi-Asset Suite</span>
        </div>
        <div class="card-luxury-body">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px;">
                <a href="transactions.php" style="display: flex; gap: 16px; padding: 20px; border: 1px solid var(--border-color); border-radius: var(--radius-sm); text-decoration: none; color: inherit; transition: all 0.2s ease;" onmouseover="this.style.borderColor='var(--accent)'; this.style.transform='translateY(-2px)';" onmouseout="this.style.borderColor='var(--border-color)'; this.style.transform='translateY(0)';">
                    <span style="font-size: 28px;">💳</span>
                    <div>
                        <strong style="display: block; font-size: 15px; color: var(--primary); margin-bottom: 4px;">Ledger & Transactions</strong>
                        <span style="font-size: 13px; color: var(--text-muted);">Manage real-time cash flow, category tags, and historical bank records.</span>
                    </div>
                </a>

                <a href="budget.php" style="display: flex; gap: 16px; padding: 20px; border: 1px solid var(--border-color); border-radius: var(--radius-sm); text-decoration: none; color: inherit; transition: all 0.2s ease;" onmouseover="this.style.borderColor='var(--accent)'; this.style.transform='translateY(-2px)';" onmouseout="this.style.borderColor='var(--border-color)'; this.style.transform='translateY(0)';">
                    <span style="font-size: 28px;">📊</span>
                    <div>
                        <strong style="display: block; font-size: 15px; color: var(--primary); margin-bottom: 4px;">Budget Envelope</strong>
                        <span style="font-size: 13px; color: var(--text-muted);">Define spending ceilings, prevent leakage, and monitor allocations.</span>
                    </div>
                </a>

                <a href="investments.php" style="display: flex; gap: 16px; padding: 20px; border: 1px solid var(--border-color); border-radius: var(--radius-sm); text-decoration: none; color: inherit; transition: all 0.2s ease;" onmouseover="this.style.borderColor='var(--accent)'; this.style.transform='translateY(-2px)';" onmouseout="this.style.borderColor='var(--border-color)'; this.style.transform='translateY(0)';">
                    <span style="font-size: 28px;">📈</span>
                    <div>
                        <strong style="display: block; font-size: 15px; color: var(--primary); margin-bottom: 4px;">Asset Portfolio</strong>
                        <span style="font-size: 13px; color: var(--text-muted);">Track mutual funds, equity holdings, gold assets, and compound yields.</span>
                    </div>
                </a>

                <a href="tax_planning.php" style="display: flex; gap: 16px; padding: 20px; border: 1px solid var(--border-color); border-radius: var(--radius-sm); text-decoration: none; color: inherit; transition: all 0.2s ease;" onmouseover="this.style.borderColor='var(--accent)'; this.style.transform='translateY(-2px)';" onmouseout="this.style.borderColor='var(--border-color)'; this.style.transform='translateY(0)';">
                    <span style="font-size: 28px;">⚖️</span>
                    <div>
                        <strong style="display: block; font-size: 15px; color: var(--primary); margin-bottom: 4px;">Tax Optimization (FY 2024-25)</strong>
                        <span style="font-size: 13px; color: var(--text-muted);">Compare Old vs New Tax Regimes, claim Section 87A rebate and 80C/80D.</span>
                    </div>
                </a>

                <a href="goals.php" style="display: flex; gap: 16px; padding: 20px; border: 1px solid var(--border-color); border-radius: var(--radius-sm); text-decoration: none; color: inherit; transition: all 0.2s ease;" onmouseover="this.style.borderColor='var(--accent)'; this.style.transform='translateY(-2px)';" onmouseout="this.style.borderColor='var(--border-color)'; this.style.transform='translateY(0)';">
                    <span style="font-size: 28px;">🎯</span>
                    <div>
                        <strong style="display: block; font-size: 15px; color: var(--primary); margin-bottom: 4px;">Milestone Goals</strong>
                        <span style="font-size: 13px; color: var(--text-muted);">Track retirement reserves, emergency funds, and major milestone savings.</span>
                    </div>
                </a>

                <a href="professionals.php" style="display: flex; gap: 16px; padding: 20px; border: 1px solid var(--border-color); border-radius: var(--radius-sm); text-decoration: none; color: inherit; transition: all 0.2s ease;" onmouseover="this.style.borderColor='var(--accent)'; this.style.transform='translateY(-2px)';" onmouseout="this.style.borderColor='var(--border-color)'; this.style.transform='translateY(0)';">
                    <span style="font-size: 28px;">🤝</span>
                    <div>
                        <strong style="display: block; font-size: 15px; color: var(--primary); margin-bottom: 4px;">Chartered Accountants Network</strong>
                        <span style="font-size: 13px; color: var(--text-muted);">Schedule direct 1-on-1 consultations with verified financial planners.</span>
                    </div>
                </a>
            </div>
        </div>
    </div>
</main>

<?php include "includes/footer.php"; ?>
