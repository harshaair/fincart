<?php
require_once "includes/security.php";
init_secure_session();
include "db.php";
require_once "includes/tax_helper.php";

$active_fy = getIndianFinancialYear();

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

// Get current month and comparison periods
$current_month = date('Y-m');
$last_month = date('Y-m', strtotime('-1 month'));
$last_month_end = date('Y-m-t', strtotime('-1 month'));

// Consolidated single-trip executive metrics query (eliminates 4 sequential network round-trips)
$metrics_sql = "SELECT 
    COALESCE(SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END), 0) AS total_income,
    COALESCE(SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END), 0) AS total_expense,
    COALESCE(SUM(CASE WHEN type = 'income' AND date <= ? THEN amount ELSE 0 END), 0) AS last_month_income,
    COALESCE(SUM(CASE WHEN type = 'expense' AND date <= ? THEN amount ELSE 0 END), 0) AS last_month_expense,
    COALESCE(SUM(CASE WHEN type = 'income' AND DATE_FORMAT(date, '%Y-%m') = ? THEN amount ELSE 0 END), 0) AS monthly_income,
    COALESCE(SUM(CASE WHEN type = 'expense' AND DATE_FORMAT(date, '%Y-%m') = ? THEN amount ELSE 0 END), 0) AS monthly_expense
    FROM transactions WHERE user_id = ?";
$metrics_stmt = $conn->prepare($metrics_sql);
$metrics_stmt->bind_param("ssssi", $last_month_end, $last_month_end, $current_month, $current_month, $user_id);
$metrics_stmt->execute();
$m_data = $metrics_stmt->get_result()->fetch_assoc();

$total_balance = (float)($m_data['total_income'] ?? 0) - (float)($m_data['total_expense'] ?? 0);
$last_month_balance = (float)($m_data['last_month_income'] ?? 0) - (float)($m_data['last_month_expense'] ?? 0);
$monthly_income = (float)($m_data['monthly_income'] ?? 0);
$monthly_expense = (float)($m_data['monthly_expense'] ?? 0);

// Calculate balance change percentage
$balance_change = 0;
if ($last_month_balance != 0) {
    $balance_change = (($total_balance - $last_month_balance) / abs($last_month_balance)) * 100;
} elseif ($last_month_balance == 0 && $total_balance != 0) {
    $balance_change = 100;
}

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
            ['label' => 'Tax Optimizer', 'href' => 'tax_planning.php', 'class' => 'btn-outline'],
            ['label' => 'Find CA / Expert', 'href' => 'professionals.php', 'class' => 'btn-outline']
        ]
    ]);
    ?>

    <!-- 1-Tap Quick Expense Shortcuts -->
    <div style="background: #ffffff; border: 1px solid var(--border-color); border-radius: var(--radius-sm); padding: 12px 18px; margin-bottom: 24px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
        <div style="display: flex; align-items: center; gap: 8px;">
            <span style="font-size: 16px;">⚡</span>
            <span style="font-size: 13px; font-weight: 700; color: var(--primary); text-transform: uppercase; letter-spacing: 0.5px;">Quick Log:</span>
        </div>
        <div style="display: flex; flex-wrap: wrap; gap: 8px;">
            <a href="add_transaction.php?type=expense&category=Food&amount=50&desc=Chai%20/%20Coffee" class="btn btn-sm" style="background: #fafaf9; border: 1px solid var(--border-color); color: var(--text-dark); border-radius: 20px; font-size: 12px; font-weight: 600; text-decoration: none;">☕ ₹50 Tea/Coffee</a>
            <a href="add_transaction.php?type=expense&category=Transportation&amount=200&desc=Uber%20/%20Auto" class="btn btn-sm" style="background: #fafaf9; border: 1px solid var(--border-color); color: var(--text-dark); border-radius: 20px; font-size: 12px; font-weight: 600; text-decoration: none;">🚕 ₹200 Cab/Auto</a>
            <a href="add_transaction.php?type=expense&category=Food&amount=500&desc=Daily%20Groceries" class="btn btn-sm" style="background: #fafaf9; border: 1px solid var(--border-color); color: var(--text-dark); border-radius: 20px; font-size: 12px; font-weight: 600; text-decoration: none;">🛒 ₹500 Groceries</a>
            <a href="add_transaction.php?type=expense&category=Transportation&amount=1000&desc=Petrol%20/%20Fuel" class="btn btn-sm" style="background: #fafaf9; border: 1px solid var(--border-color); color: var(--text-dark); border-radius: 20px; font-size: 12px; font-weight: 600; text-decoration: none;">⛽ ₹1,000 Fuel</a>
            <a href="add_transaction.php?type=expense&category=Food&amount=350&desc=Dining%20/%20Swiggy" class="btn btn-sm" style="background: #fafaf9; border: 1px solid var(--border-color); color: var(--text-dark); border-radius: 20px; font-size: 12px; font-weight: 600; text-decoration: none;">🍕 ₹350 Dining</a>
            <a href="add_transaction.php?type=income&category=Salary&amount=50000&desc=Monthly%20Salary" class="btn btn-sm" style="background: #f0fdf4; border: 1px solid #86efac; color: #15803d; border-radius: 20px; font-size: 12px; font-weight: 600; text-decoration: none;">💼 +₹50k Salary</a>
        </div>
    </div>

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
                        <strong style="display: block; font-size: 15px; color: var(--primary); margin-bottom: 4px;">Tax Optimization (<?php echo htmlspecialchars($active_fy['fy_label']); ?>)</strong>
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
