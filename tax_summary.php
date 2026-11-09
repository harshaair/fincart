<?php
session_start();
include "db.php";

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Get user name
$user_sql = "SELECT full_name FROM users WHERE id = ?";
$user_stmt = $conn->prepare($user_sql);
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user = $user_result->fetch_assoc();

// Get all tax calculations
$tax_sql = "SELECT * FROM tax_planning WHERE user_id = ? ORDER BY financial_year DESC LIMIT 5";
$tax_stmt = $conn->prepare($tax_sql);
$tax_stmt->bind_param("i", $user_id);
$tax_stmt->execute();
$tax_result = $tax_stmt->get_result();
$tax_calculations = [];

while ($row = $tax_result->fetch_assoc()) {
    $tax_calculations[] = $row;
}

// Get current year income
$current_year = date('Y');
$income_sql = "SELECT SUM(amount) as total_income FROM transactions WHERE user_id = ? AND type = 'income' AND YEAR(date) = ?";
$income_stmt = $conn->prepare($income_sql);
$income_stmt->bind_param("ii", $user_id, $current_year);
$income_stmt->execute();
$income_result = $income_stmt->get_result();
$income_row = $income_result->fetch_assoc();
$current_year_income = $income_row['total_income'] ?? 0;

// Get current year deductions
$deduction_sql = "SELECT SUM(amount) as total_deductions FROM tax_deductions WHERE user_id = ? AND YEAR(deduction_date) = ?";
$deduction_stmt = $conn->prepare($deduction_sql);
$deduction_stmt->bind_param("ii", $user_id, $current_year);
$deduction_stmt->execute();
$deduction_result = $deduction_stmt->get_result();
$deduction_row = $deduction_result->fetch_assoc();
$current_year_deductions = $deduction_row['total_deductions'] ?? 0;

// Get all transactions for expense analysis
$all_trans_sql = "SELECT type, category, SUM(amount) as total FROM transactions WHERE user_id = ? AND YEAR(date) = ? GROUP BY type, category";
$all_trans_stmt = $conn->prepare($all_trans_sql);
$all_trans_stmt->bind_param("ii", $user_id, $current_year);
$all_trans_stmt->execute();
$all_trans_result = $all_trans_stmt->get_result();
$category_analysis = [];

while ($row = $all_trans_result->fetch_assoc()) {
    $category_analysis[] = $row;
}

// Calculate tax savings for new vs old regime
$latest_tax = $tax_calculations[0] ?? null;
$tax_savings = 0;
if ($latest_tax) {
    // This would be calculated based on the difference between regimes
    // For now, we'll show a placeholder
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tax Summary Report - FinCart</title>
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
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 20px;
        }

        .page-header {
            margin-bottom: 30px;
        }

        .page-header h1 {
            font-family: 'Playfair Display', serif;
            font-size: 42px;
            color: var(--primary);
            margin-bottom: 10px;
            letter-spacing: -1px;
        }

        .page-header p {
            color: var(--text-light);
            font-size: 16px;
        }

        .disclaimer {
            background: rgba(243, 156, 18, 0.1);
            border: 2px solid var(--warning);
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 30px;
            color: var(--text-dark);
            font-size: 13px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: var(--white);
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            border-left: 5px solid var(--accent);
        }

        .stat-value {
            font-family: 'Playfair Display', serif;
            font-size: 32px;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 5px;
        }

        .stat-label {
            color: var(--text-light);
            font-size: 13px;
            font-weight: 600;
        }

        .card {
            background: var(--white);
            padding: 30px;
            border-radius: 20px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            margin-bottom: 25px;
        }

        .card h2 {
            font-family: 'Playfair Display', serif;
            font-size: 28px;
            color: var(--primary);
            margin-bottom: 20px;
        }

        .card h3 {
            font-family: 'Playfair Display', serif;
            font-size: 20px;
            color: var(--primary);
            margin-bottom: 15px;
            margin-top: 20px;
        }

        .chart-container {
            position: relative;
            height: 300px;
            margin-bottom: 30px;
        }

        .report-row {
            display: flex;
            justify-content: space-between;
            padding: 15px 0;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        }

        .report-row:last-child {
            border-bottom: none;
        }

        .report-label {
            font-weight: 600;
            color: var(--text-dark);
        }

        .report-value {
            font-family: 'Playfair Display', serif;
            font-weight: 700;
            color: var(--accent);
        }

        .total-row {
            background: var(--off-white);
            padding: 15px;
            border-radius: 8px;
            margin-top: 10px;
            display: flex;
            justify-content: space-between;
        }

        .total-label {
            font-weight: 700;
            color: var(--primary);
        }

        .total-value {
            font-family: 'Playfair Display', serif;
            font-size: 18px;
            font-weight: 700;
            color: var(--primary);
        }

        .tax-history {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: var(--off-white);
            padding: 12px;
            text-align: left;
            font-weight: 600;
            border-bottom: 2px solid rgba(0, 0, 0, 0.1);
        }

        td {
            padding: 12px;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        }

        tr:hover {
            background: var(--off-white);
        }

        .btn {
            padding: 10px 20px;
            background: var(--primary);
            color: var(--white);
            border: none;
            border-radius: 20px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s ease;
            margin-top: 15px;
        }

        .btn:hover {
            background: var(--accent);
            color: var(--primary);
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--text-light);
        }

        .highlight-box {
            background: linear-gradient(135deg, rgba(212, 175, 55, 0.1) 0%, rgba(39, 174, 96, 0.1) 100%);
            padding: 20px;
            border-radius: 12px;
            margin: 15px 0;
            border-left: 5px solid var(--accent);
        }

        @media print {
            header,
            .nav-links,
            .logout-btn,
            .btn {
                display: none !important;
            }

            body {
                background: #ffffff;
            }

            .container {
                padding: 10px;
            }
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .report-row {
                flex-direction: column;
            }

            table {
                font-size: 12px;
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
            <h1>📊 Tax Summary Report</h1>
            <p>Comprehensive overview of your tax situation for FY <?php echo $current_year; ?></p>
            <button class="btn" style="margin-top: 20px;" onclick="window.print();">
                🖨️ Generate Printable Tax Summary (PDF)
            </button>
        </div>

        <!-- Disclaimer -->
        <div class="disclaimer">
            ⚠️ <strong>Report Disclaimer:</strong> This report is generated from your transaction and deduction records. It is for informational purposes only and does not constitute professional tax advice. Please consult with a qualified Chartered Accountant or tax professional before filing your ITR.
        </div>

        <!-- Current Year Overview -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value">₹<?php echo number_format($current_year_income, 0); ?></div>
                <div class="stat-label">Gross Income (FY <?php echo $current_year; ?>)</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">₹<?php echo number_format($current_year_deductions, 0); ?></div>
                <div class="stat-label">Total Deductions</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">₹<?php echo number_format(max(0, $current_year_income - $current_year_deductions), 0); ?></div>
                <div class="stat-label">Taxable Income</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $current_year_deductions > 0 ? round(($current_year_deductions / $current_year_income) * 100, 1) : 0; ?>%</div>
                <div class="stat-label">Deduction Ratio</div>
            </div>
        </div>

        <!-- Current Year Summary -->
        <div class="card">
            <h2>FY <?php echo $current_year; ?> Income & Deduction Summary</h2>

            <div class="report-row">
                <span class="report-label">Gross Income</span>
                <span class="report-value">₹<?php echo number_format($current_year_income, 2); ?></span>
            </div>

            <div class="report-row">
                <span class="report-label">Less: Total Deductions</span>
                <span class="report-value">-₹<?php echo number_format($current_year_deductions, 2); ?></span>
            </div>

            <div class="total-row">
                <span class="total-label">Estimated Taxable Income</span>
                <span class="total-value">₹<?php echo number_format(max(0, $current_year_income - $current_year_deductions), 2); ?></span>
            </div>

            <div class="highlight-box">
                <strong>💡 Tip:</strong> You can increase your deductions by tracking more eligible expenses and investments. Consider reviewing Section 80C, 80D, and other deduction categories.
            </div>
        </div>

        <!-- Previous Calculations -->
        <?php if (count($tax_calculations) > 0): ?>
            <div class="card">
                <h2>Previous Tax Calculations</h2>
                
                <div class="tax-history">
                    <table>
                        <thead>
                            <tr>
                                <th>Financial Year</th>
                                <th>Gross Income</th>
                                <th>Deductions</th>
                                <th>Taxable Income</th>
                                <th>Estimated Tax</th>
                                <th>Tax Bracket</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tax_calculations as $calc): ?>
                                <tr>
                                    <td>FY <?php echo htmlspecialchars($calc['financial_year']); ?></td>
                                    <td>₹<?php echo number_format($calc['gross_income'], 0); ?></td>
                                    <td>₹<?php echo number_format($calc['total_deductions'], 0); ?></td>
                                    <td>₹<?php echo number_format($calc['taxable_income'], 0); ?></td>
                                    <td>₹<?php echo number_format($calc['estimated_tax'], 0); ?></td>
                                    <td><?php echo htmlspecialchars($calc['tax_bracket']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <!-- Category Analysis -->
        <?php if (count($category_analysis) > 0): ?>
            <div class="card">
                <h2>Income & Expense Category Analysis</h2>
                
                <?php 
                $income_categories = array_filter($category_analysis, function($item) { return $item['type'] === 'income'; });
                $expense_categories = array_filter($category_analysis, function($item) { return $item['type'] === 'expense'; });
                ?>

                <h3>💰 Income by Category</h3>
                <?php if (count($income_categories) > 0): ?>
                    <?php foreach ($income_categories as $cat): ?>
                        <div class="report-row">
                            <span class="report-label"><?php echo htmlspecialchars($cat['category']); ?></span>
                            <span class="report-value">₹<?php echo number_format($cat['total'], 0); ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="color: var(--text-light); margin: 10px 0;">No income entries recorded</p>
                <?php endif; ?>

                <h3>💸 Expenses by Category</h3>
                <?php if (count($expense_categories) > 0): ?>
                    <?php foreach ($expense_categories as $cat): ?>
                        <div class="report-row">
                            <span class="report-label"><?php echo htmlspecialchars($cat['category']); ?></span>
                            <span class="report-value">₹<?php echo number_format($cat['total'], 0); ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="color: var(--text-light); margin: 10px 0;">No expense entries recorded</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Action Buttons -->
        <div class="card">
            <h2>Next Steps</h2>
            <p style="color: var(--text-light); margin-bottom: 15px;">
                Use these tools to manage your tax planning effectively:
            </p>
            
            <a href="tax_calculator.php" class="btn">📋 Tax Calculator</a>
            <a href="tax_tracker.php" class="btn">📝 Deductions Tracker</a>
            <a href="tax_planning.php" class="btn">📈 Tax Planning</a>
            <a href="itr_preparation.php" class="btn">📄 ITR Preparation</a>
            
            <div class="highlight-box" style="margin-top: 20px;">
                <strong>🤝 Need Professional Help?</strong><br>
                Our experts can help you optimize your tax strategy, ensure compliance, and maximize deductions. 
                <a href="javascript:void(0);" style="color: var(--accent); text-decoration: none; font-weight: 600;">Connect with a Tax Professional →</a>
            </div>
        </div>
    </div>
</body>
</html>
