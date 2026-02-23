<?php
session_start();
include "db.php";

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.html');
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
    header('Location: login.html');
    exit();
}
// Store role in session (if not already)
$_SESSION['role'] = $user['role'];

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
$last_month_end = date('Y-m-t', strtotime('-1 month')); // Last day of last month
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
    $balance_change = 100; // New balance from zero
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

// Format balance change
$balance_change_formatted = number_format(abs($balance_change), 1);
$balance_change_sign = $balance_change >= 0 ? '+' : '-';

// Get expense percentage of income
$expense_percentage = 0;
if ($monthly_income > 0) {
    $expense_percentage = ($monthly_expense / $monthly_income) * 100;
}
$expense_percentage_formatted = number_format($expense_percentage, 1);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - FinCart</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/admin.css">
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
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, var(--off-white) 0%, var(--secondary) 100%);
            color: var(--text-dark);
            min-height: 100vh;
        }

        /* Header/Navbar */
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
            max-width: 1200px;
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

        .header-right {
            display: flex;
            align-items: center;
            gap: 30px;
        }

        .user-info {
            text-align: right;
        }

        .user-info p {
            margin: 0;
            font-size: 14px;
            color: var(--text-light);
        }

        .user-info .name {
            font-weight: 700;
            color: var(--text-dark);
            font-size: 16px;
        }

        .logout-btn {
            padding: 10px 24px;
            background: var(--primary);
            color: var(--white);
            border: none;
            border-radius: 50px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }

        .logout-btn:hover {
            background: var(--accent);
            color: var(--primary);
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(212, 175, 55, 0.3);
        }

        /* Main Container */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 20px;
        }

        /* Welcome Section */
        .welcome-section {
            background: var(--white);
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            margin-bottom: 40px;
            position: relative;
            overflow: hidden;
        }

        .welcome-section::before {
            content: '';
            position: absolute;
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, rgba(212, 175, 55, 0.1) 0%, transparent 70%);
            top: -100px;
            right: -100px;
        }

        .welcome-content {
            position: relative;
            z-index: 1;
        }

        .welcome-content h2 {
            font-family: 'Playfair Display', serif;
            font-size: 42px;
            color: var(--primary);
            margin-bottom: 10px;
            letter-spacing: -1px;
        }

        .welcome-content p {
            font-size: 18px;
            color: var(--text-light);
            margin-bottom: 30px;
            line-height: 1.6;
        }

        .welcome-buttons {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }

        .btn {
            padding: 14px 32px;
            border: none;
            border-radius: 50px;
            cursor: pointer;
            font-weight: 600;
            font-size: 15px;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }

        .btn-primary {
            background: var(--primary);
            color: var(--white);
        }

        .btn-primary:hover {
            background: var(--accent);
            color: var(--primary);
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(212, 175, 55, 0.3);
        }

        .btn-secondary {
            background: var(--off-white);
            color: var(--primary);
            border: 2px solid var(--primary);
        }

        .btn-secondary:hover {
            background: var(--primary);
            color: var(--white);
            transform: translateY(-3px);
        }

        /* Dashboard Grid */
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 24px;
            margin-bottom: 40px;
        }

        /* Card Styles */
        .card {
            background: var(--white);
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            transition: all 0.4s ease;
            border: 1px solid rgba(212, 175, 55, 0.1);
            position: relative;
            overflow: hidden;
        }

        .card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, var(--accent) 0%, transparent 100%);
            transform: scaleX(0);
            transition: transform 0.4s ease;
            transform-origin: left;
        }

        .card:hover::before {
            transform: scaleX(1);
        }

        .card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.12);
        }

        .card-icon {
            font-size: 3em;
            margin-bottom: 15px;
        }

        .card h3 {
            font-size: 20px;
            color: var(--primary);
            margin-bottom: 10px;
            font-weight: 700;
        }

        .card p {
            color: var(--text-light);
            font-size: 15px;
            line-height: 1.6;
            margin-bottom: 20px;
        }

        .card-link {
            color: var(--accent);
            text-decoration: none;
            font-weight: 600;
            display: inline-block;
            transition: all 0.3s ease;
        }

        .card-link:hover {
            color: var(--primary);
            transform: translateX(5px);
        }

        /* Stats Section */
        .stats-section {
            background: var(--white);
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            margin-bottom: 40px;
        }

        .stats-section h2 {
            font-family: 'Playfair Display', serif;
            font-size: 32px;
            color: var(--primary);
            margin-bottom: 30px;
            letter-spacing: -1px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 24px;
        }

        .stat-card {
            background: linear-gradient(135deg, var(--off-white) 0%, var(--secondary) 100%);
            padding: 24px;
            border-radius: 15px;
            text-align: center;
            border: 1px solid rgba(212, 175, 55, 0.1);
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
            color: var(--primary);
            font-weight: 700;
            margin-bottom: 5px;
        }

        .stat-description {
            color: var(--accent);
            font-size: 13px;
        }

        /* Recent Activity removed */

        /* Responsive */
        @media (max-width: 768px) {
            .header-right {
                flex-direction: column;
                gap: 15px;
                align-items: flex-end;
            }

            .welcome-content h2 {
                font-size: 32px;
            }

            .welcome-buttons {
                flex-direction: column;
            }

            .btn {
                width: 100%;
                text-align: center;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--text-light);
        }

        .empty-state-icon {
            font-size: 4em;
            margin-bottom: 15px;
            opacity: 0.5;
        }

        .empty-state p {
            font-size: 16px;
            margin-bottom: 20px;
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
            <div class="header-right">
                <div class="user-info">
                    <p class="name"><?php echo htmlspecialchars($user['full_name']); ?></p>
                    <p><?php echo htmlspecialchars($user['email']); ?></p>
                </div>
                <?php $role = $_SESSION['role'] ?? 'user'; ?>
                <?php if ($role === 'admin'): ?>
                    <a href="admin/dashboard.php" class="btn btn-secondary" style="padding:10px 18px;">Admin</a>
                <?php endif; ?>
                <a href="logout.php" class="logout-btn">Logout</a>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <div class="container">
        <!-- Welcome Section -->
        <div class="welcome-section">
            <div class="welcome-content">
                <h2>Welcome back, <?php echo htmlspecialchars(explode(' ', $user['full_name'])[0]); ?>! 👋</h2>
                <p>Manage your finances, track investments, and achieve your financial goals with Fincart's comprehensive tools.</p>
                <div class="welcome-buttons">
                    <a href="service.html" class="btn btn-primary">Explore Services</a>
                    <a href="profile.php" class="btn btn-secondary">View Profile</a>
                </div>
            </div>
        </div>

        <!-- Dashboard Features Grid -->
        <div class="dashboard-grid">
            <div class="card">
                <div class="card-icon">💳</div>
                <h3>Transactions</h3>
                <p>Track your income and expenses. Get detailed insights into your spending patterns.</p>
                <a href="transactions.php" class="card-link">View Transactions →</a>
            </div>

            <div class="card">
                <div class="card-icon">💼</div>
                <h3>Budget Tracker</h3>
                <p>Set spending limits by category and monitor your monthly budget vs actual spending.</p>
                <a href="budget.php" class="card-link">Manage Budgets →</a>
            </div>

            <div class="card">
                <div class="card-icon">💰</div>
                <h3>Investments</h3>
                <p>Monitor your investment portfolio and track returns on your investments.</p>
                <a href="investments.php" class="card-link">View Portfolio →</a>
            </div>

            <div class="card">
                <div class="card-icon">📊</div>
                <h3>Reports</h3>
                <p>Generate detailed financial reports and export data for analysis and planning.</p>
                <a href="analytics.php" class="card-link">Generate Reports →</a>
            </div>

            <div class="card">
                <div class="card-icon">🎯</div>
                <h3>Goals</h3>
                <p>Set and track financial goals. Monitor your progress towards achieving them.</p>
                <a href="goals.php" class="card-link">Set Goals →</a>
            </div>

            <div class="card">
                <div class="card-icon">⚙️</div>
                <h3>Settings</h3>
                <p>Manage your account preferences, security settings, and notification options.</p>
                <a href="profile.php" class="card-link">Go to Settings →</a>
            </div>

            <div class="card">
                <div class="card-icon">📈</div>
                <h3>Tax Planning</h3>
                <p>Calculate taxes, track deductions, plan your tax strategy, and prepare ITR with guided assistance.</p>
                <a href="tax_calculator.php" class="card-link">Start Tax Planning →</a>
            </div>

            

            <div class="card">
                <div class="card-icon">🏢</div>
                <h3>Professional Network</h3>
                <p>Connect with certified financial professionals - CAs, advisors, and tax consultants for expert guidance.</p>
                <a href="professionals.php" class="card-link">Find Professionals →</a>
                <a href="my_appointments.php" class="card-link">My Appointments →</a>
            </div>
        </div>

        <!-- Stats Section -->
        <div class="stats-section">
            <h2>Account Summary</h2>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-label">Total Balance</div>
                    <div class="stat-value">₹<?php echo $total_balance_formatted; ?></div>
                    <div class="stat-description">
                        <?php 
                        if ($balance_change != 0) {
                            echo $balance_change_sign . $balance_change_formatted . '% from last month';
                        } else {
                            echo 'No change from last month';
                        }
                        ?>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Monthly Income</div>
                    <div class="stat-value">₹<?php echo $monthly_income_formatted; ?></div>
                    <div class="stat-description">From all sources</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Monthly Expenses</div>
                    <div class="stat-value">₹<?php echo $monthly_expense_formatted; ?></div>
                    <div class="stat-description">
                        <?php 
                        if ($monthly_income > 0) {
                            echo $expense_percentage_formatted . '% of income';
                        } else {
                            echo 'No income recorded';
                        }
                        ?>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Savings Rate</div>
                    <div class="stat-value"><?php echo $savings_rate_formatted; ?>%</div>
                    <div class="stat-description">
                        <?php
                        if ($savings_rate >= 50) {
                            echo 'Excellent savings habit';
                        } elseif ($savings_rate >= 30) {
                            echo 'Good savings habit';
                        } elseif ($savings_rate >= 20) {
                            echo 'Moderate savings';
                        } elseif ($savings_rate > 0) {
                            echo 'Low savings rate';
                        } else {
                            echo 'No savings this month';
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activity removed -->
    </div>

    <script>
        // Active dashboard
        console.log('User Dashboard Loaded for:', '<?php echo htmlspecialchars($user['full_name']); ?>');
    </script>
</body>
</html>
