<?php
session_start();
include "db.php";

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.html');
    exit();
}

$user_id = $_SESSION['user_id'];
$current_year = date('Y');

// Get current year income and deductions
$income_sql = "SELECT SUM(amount) as total_income FROM transactions WHERE user_id = ? AND type = 'income' AND YEAR(date) = ?";
$income_stmt = $conn->prepare($income_sql);
$income_stmt->bind_param("ii", $user_id, $current_year);
$income_stmt->execute();
$income_result = $income_stmt->get_result();
$income_row = $income_result->fetch_assoc();
$current_income = $income_row['total_income'] ?? 0;

$deduction_sql = "SELECT SUM(amount) as total_deductions FROM tax_deductions WHERE user_id = ? AND YEAR(deduction_date) = ?";
$deduction_stmt = $conn->prepare($deduction_sql);
$deduction_stmt->bind_param("ii", $user_id, $current_year);
$deduction_stmt->execute();
$deduction_result = $deduction_stmt->get_result();
$deduction_row = $deduction_result->fetch_assoc();
$current_deductions = $deduction_row['total_deductions'] ?? 0;

// Get deductions by category
$category_sql = "SELECT category, SUM(amount) as total FROM tax_deductions WHERE user_id = ? AND YEAR(deduction_date) = ? GROUP BY category ORDER BY total DESC";
$category_stmt = $conn->prepare($category_sql);
$category_stmt->bind_param("ii", $user_id, $current_year);
$category_stmt->execute();
$category_result = $category_stmt->get_result();
$deductions_by_category = [];

while ($row = $category_result->fetch_assoc()) {
    $deductions_by_category[] = $row;
}

// Tax brackets for 2024-25 (New Regime)
$new_regime_brackets = [
    ['min' => 0, 'max' => 300000, 'rate' => 0],
    ['min' => 300000, 'max' => 600000, 'rate' => 5],
    ['min' => 600000, 'max' => 900000, 'rate' => 10],
    ['min' => 900000, 'max' => 1200000, 'rate' => 15],
    ['min' => 1200000, 'max' => 1500000, 'rate' => 20],
    ['min' => 1500000, 'max' => 1800000, 'rate' => 25],
    ['min' => 1800000, 'max' => PHP_INT_MAX, 'rate' => 30],
];

// Old Tax Regime Slabs
$old_regime_brackets = [
    ['min' => 0, 'max' => 250000, 'rate' => 0],
    ['min' => 250000, 'max' => 500000, 'rate' => 5],
    ['min' => 500000, 'max' => 1000000, 'rate' => 20],
    ['min' => 1000000, 'max' => PHP_INT_MAX, 'rate' => 30],
];

// Calculate estimated tax
function calculateEstimatedTax($income) {
    global $new_regime_brackets;
    $tax = 0;
    $prev_max = 0;
    
    foreach ($new_regime_brackets as $bracket) {
        if ($income > $bracket['min']) {
            $taxable_in_bracket = min($income, $bracket['max']) - $bracket['min'];
            $tax += $taxable_in_bracket * ($bracket['rate'] / 100);
        }
    }
    
    $cess = $tax * 0.04;
    return $tax + $cess;
}

// Helper for detailed tax calculation for a given regime
function calculateRegimeTax($taxable_income, $slabs) {
    $calculated_tax = 0;

    foreach ($slabs as $slab) {
        if ($taxable_income > $slab['min']) {
            $taxable_in_slab = min($taxable_income, $slab['max']) - $slab['min'];
            $calculated_tax += $taxable_in_slab * ($slab['rate'] / 100);
        }
    }

    $surcharge = 0;
    if ($taxable_income > 5000000) {
        $surcharge = $calculated_tax * 0.25;
    } elseif ($taxable_income > 2000000) {
        $surcharge = $calculated_tax * 0.15;
    }

    $health_cess = $calculated_tax * 0.04;
    $total_tax = $calculated_tax + $surcharge + $health_cess;

    return [
        'calculated_tax' => $calculated_tax,
        'surcharge'      => $surcharge,
        'health_cess'    => $health_cess,
        'total_tax'      => $total_tax,
        'effective_rate' => $taxable_income > 0 ? ($total_tax / $taxable_income) * 100 : 0,
    ];
}

$taxable_income = max(0, $current_income - $current_deductions);
$estimated_tax = calculateEstimatedTax($taxable_income);

// Calculator & regime comparison state
$calc_result = null;
$comparison_result = null;
$calc_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $gross_income_input = isset($_POST['gross_income']) ? floatval($_POST['gross_income']) : null;
    $deductions_input   = isset($_POST['deductions']) ? floatval($_POST['deductions']) : $current_deductions;

    if ($gross_income_input !== null) {
        if ($gross_income_input < 0 || $deductions_input < 0) {
            $calc_error = 'Income and deductions must be positive values.';
        } else {
            $calc_taxable_income = max(0, $gross_income_input - $deductions_input);

            // Calculate for both regimes
            $new_res = calculateRegimeTax($calc_taxable_income, $new_regime_brackets);
            $old_res = calculateRegimeTax($calc_taxable_income, $old_regime_brackets);

            $better_regime = $new_res['total_tax'] <= $old_res['total_tax'] ? 'new' : 'old';
            $tax_difference = abs($new_res['total_tax'] - $old_res['total_tax']);

            $calc_result = [
                'gross_income'    => $gross_income_input,
                'deductions'      => $deductions_input,
                'taxable_income'  => $calc_taxable_income,
            ];

            $comparison_result = [
                'new'            => $new_res,
                'old'            => $old_res,
                'better_regime'  => $better_regime,
                'tax_difference' => $tax_difference,
            ];
        }
    }
}

// Recommendation suggestions & Tax Saving Optimizer inputs
$recommendations = [];

// Section 80C recommendations (max 1.5 lakhs)
$max_80c = 150000;
$section_80c_deductions = 0;
foreach ($deductions_by_category as $cat) {
    if (strpos($cat['category'], '80C') !== false) {
        $section_80c_deductions = $cat['total'];
        break;
    }
}
$remaining_80c = max(0, $max_80c - $section_80c_deductions);
$potential_tax_saving_80c = round($remaining_80c * 0.30, 0); // assume 30% bracket for planning

if ($remaining_80c > 0) {
    $recommendations[] = [
        'title' => 'Maximize Section 80C Deductions',
        'description' => "You can invest up to ₹{$remaining_80c} more under Section 80C (Life Insurance, PPF, ELSS, etc.).",
        'impact' => $potential_tax_saving_80c,
        'type' => 'high'
    ];
}

// Section 80D recommendations
$max_80d = 100000;
$section_80d_deductions = 0;
foreach ($deductions_by_category as $cat) {
    if (strpos($cat['category'], '80D') !== false) {
        $section_80d_deductions = $cat['total'];
        break;
    }
}
$remaining_80d = max(0, $max_80d - $section_80d_deductions);
$potential_tax_saving_80d = round($remaining_80d * 0.30, 0);

if ($remaining_80d > 0 && $current_income > 500000) {
    $recommendations[] = [
        'title' => 'Review Health Insurance Premium (80D)',
        'description' => "You can still claim up to ₹{$remaining_80d} more under Section 80D for health insurance premiums.",
        'impact' => $potential_tax_saving_80d,
        'type' => 'medium'
    ];
}

// Additional recommendations
if ($current_deductions < 500000 && $current_income > 1000000) {
    $recommendations[] = [
        'title' => 'Explore Additional Deduction Options',
        'description' => 'Consider education loan interest (80E), charitable donations (80G), or NPS contributions (80CCD)',
        'impact' => 30000,
        'type' => 'medium'
    ];
}

if ($estimated_tax > 500000) {
    $recommendations[] = [
        'title' => 'Plan Quarterly Advance Tax',
        'description' => 'With estimated tax above ₹5 lakhs, ensure you pay advance tax in required installments to avoid penalties',
        'impact' => 0,
        'type' => 'high'
    ];
}

// Investment opportunities for tax saving
$tax_saving_investments = [
    ['name' => 'ELSS Mutual Funds', 'limit' => 150000, 'return' => '12-15%', 'section' => '80C'],
    ['name' => 'Public Provident Fund (PPF)', 'limit' => 150000, 'return' => '7.1%', 'section' => '80C'],
    ['name' => 'National Saving Certificate (NSC)', 'limit' => 150000, 'return' => '6.8%', 'section' => '80C'],
    ['name' => 'Pension Plans', 'limit' => 150000, 'return' => 'Varies', 'section' => '80C'],
    ['name' => 'Senior Citizens Saving Scheme', 'limit' => 1500000, 'return' => '7.4%', 'section' => 'Not applicable'],
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tax Planning Dashboard - FinCart</title>
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
            margin: 20px 0 15px 0;
        }

        .recommendation-item {
            background: var(--off-white);
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 15px;
            border-left: 5px solid var(--accent);
        }

        .recommendation-item.high {
            border-left-color: var(--danger);
        }

        .recommendation-item.medium {
            border-left-color: var(--warning);
        }

        .rec-title {
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 8px;
        }

        .rec-description {
            color: var(--text-light);
            font-size: 14px;
            margin-bottom: 10px;
        }

        .rec-impact {
            display: inline-block;
            background: var(--success);
            color: var(--white);
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
        }

        .investment-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        .investment-table th {
            background: var(--off-white);
            padding: 12px;
            text-align: left;
            font-weight: 600;
            border-bottom: 2px solid rgba(0, 0, 0, 0.1);
        }

        .investment-table td {
            padding: 12px;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        }

        .investment-table tr:hover {
            background: var(--off-white);
        }

        .chart-container {
            position: relative;
            height: 300px;
            margin-top: 20px;
        }

        .tax-impact-bar {
            height: 300px;
            margin: 20px 0;
        }

        .highlight-box {
            background: linear-gradient(135deg, rgba(212, 175, 55, 0.1) 0%, rgba(39, 174, 96, 0.1) 100%);
            padding: 20px;
            border-radius: 12px;
            margin: 15px 0;
            border-left: 5px solid var(--accent);
        }

        .comparison-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        .comparison-table th,
        .comparison-table td {
            padding: 10px 12px;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            font-size: 14px;
            text-align: left;
        }

        .comparison-table th {
            background: var(--off-white);
            font-weight: 600;
        }

        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-success {
            background: rgba(39, 174, 96, 0.1);
            color: var(--success);
        }

        .badge-muted {
            background: rgba(0, 0, 0, 0.03);
            color: var(--text-light);
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
            display: inline-block;
            margin-top: 15px;
            text-decoration: none;
        }

        .btn:hover {
            background: var(--accent);
            color: var(--primary);
        }

        .optimizer-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
            margin-top: 10px;
        }

        .optimizer-item {
            background: var(--off-white);
            padding: 16px;
            border-radius: 12px;
            border-left: 4px solid var(--accent);
        }

        .optimizer-label {
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 6px;
            color: var(--text-dark);
        }

        .optimizer-value {
            font-family: 'Playfair Display', serif;
            font-size: 18px;
            font-weight: 700;
        }

        .checklist-group {
            margin: 12px 0;
        }

        .checklist-item {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 8px;
            font-size: 14px;
            color: var(--text-light);
        }

        .checklist-item input[type="checkbox"] {
            width: 16px;
            height: 16px;
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .investment-table {
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
                <h1>📈 Tax Planning & Tax Calculator</h1>
                <p>Compare Old vs New regime, optimize deductions, and plan with professionals.</p>
            </div>

        <!-- Disclaimer -->
        <div class="disclaimer">
            ⚠️ <strong>Tax Planning Disclaimer:</strong> These recommendations are based on general Indian tax laws for FY <?php echo $current_year; ?>. Tax laws change frequently and individual circumstances vary. Please consult a qualified tax professional before implementing any strategy.
        </div>

        <!-- Simple Tax Calculator & Regime Comparison -->
        <div class="card" style="margin-bottom: 30px;">
            <h2>💰 Quick Tax Calculator (Old vs New)</h2>
            <p style="color: var(--text-light); margin-bottom: 16px;">
                Enter your income and deductions once. We will estimate tax under both regimes and highlight which one is better for you.
            </p>

            <form method="POST" style="margin-bottom: 15px;">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px;">
                    <div>
                        <label for="gross_income" style="display:block; font-weight:600; margin-bottom:6px;">Gross Annual Income (₹)</label>
                        <input
                            type="number"
                            id="gross_income"
                            name="gross_income"
                            step="0.01"
                            min="0"
                            style="width:100%; padding:10px 12px; border-radius:10px; border:1px solid rgba(0,0,0,0.1);"
                            placeholder="500000"
                            required
                        >
                    </div>
                    <div>
                        <label for="deductions" style="display:block; font-weight:600; margin-bottom:6px;">Total Deductions (₹)</label>
                        <input
                            type="number"
                            id="deductions"
                            name="deductions"
                            step="0.01"
                            min="0"
                            style="width:100%; padding:10px 12px; border-radius:10px; border:1px solid rgba(0,0,0,0.1);"
                            placeholder="<?php echo number_format($current_deductions, 0); ?>"
                            value="<?php echo $current_deductions; ?>"
                        >
                        <small style="color: var(--text-light); font-size: 12px; display:block; margin-top:4px;">
                            Note: This can be auto-filled from your deductions tracker.
                        </small>
                    </div>
                </div>
                <button type="submit" class="btn" style="margin-top: 16px;">Calculate & Compare Regimes</button>
            </form>

            <?php if ($calc_error): ?>
                <div class="highlight-box" style="border-left-color: var(--danger); background: rgba(231, 76, 60, 0.05); color: var(--danger);">
                    <?php echo htmlspecialchars($calc_error); ?>
                </div>
            <?php endif; ?>

            <?php if ($calc_result && $comparison_result): ?>
                <div style="margin-top: 10px;">
                    <p style="color: var(--text-light); margin-bottom: 8px;">
                        For a gross income of <strong>₹<?php echo number_format($calc_result['gross_income'], 0); ?></strong> and deductions of
                        <strong>₹<?php echo number_format($calc_result['deductions'], 0); ?></strong> (taxable: <strong>₹<?php echo number_format($calc_result['taxable_income'], 0); ?></strong>):
                    </p>

                    <table class="comparison-table">
                        <thead>
                            <tr>
                                <th>Regime</th>
                                <th>Total Tax (₹)</th>
                                <th>Effective Rate</th>
                                <th>Recommendation</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>New Regime</td>
                                <td>₹<?php echo number_format($comparison_result['new']['total_tax'], 0); ?></td>
                                <td><?php echo number_format($comparison_result['new']['effective_rate'], 2); ?>%</td>
                                <td>
                                    <?php if ($comparison_result['better_regime'] === 'new'): ?>
                                        <span class="badge badge-success">Suggested</span>
                                    <?php else: ?>
                                        <span class="badge badge-muted">Higher Tax</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td>Old Regime</td>
                                <td>₹<?php echo number_format($comparison_result['old']['total_tax'], 0); ?></td>
                                <td><?php echo number_format($comparison_result['old']['effective_rate'], 2); ?>%</td>
                                <td>
                                    <?php if ($comparison_result['better_regime'] === 'old'): ?>
                                        <span class="badge badge-success">Suggested</span>
                                    <?php else: ?>
                                        <span class="badge badge-muted">Higher Tax</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <?php if ($comparison_result['tax_difference'] > 0): ?>
                        <p style="margin-top: 10px; color: var(--text-dark);">
                            ✅ Best for you right now:
                            <strong>
                                <?php echo $comparison_result['better_regime'] === 'new' ? 'New Tax Regime' : 'Old Tax Regime'; ?>
                            </strong>
                            (saves approx
                            <strong>₹<?php echo number_format($comparison_result['tax_difference'], 0); ?></strong>
                            compared to the other).
                        </p>
                    <?php endif; ?>

                    <p style="margin-top: 8px; color: var(--text-light); font-size: 13px;">
                        Still not sure which regime to finally choose or how to structure your investments?
                        <a href="professionals.php" style="color: var(--accent); text-decoration: none; font-weight: 600;">
                            Talk to a tax professional →
                        </a>
                    </p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Current Status Overview -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value">₹<?php echo number_format($current_income, 0); ?></div>
                <div class="stat-label">Current Income</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">₹<?php echo number_format($current_deductions, 0); ?></div>
                <div class="stat-label">Total Deductions</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">₹<?php echo number_format($estimated_tax, 0); ?></div>
                <div class="stat-label">Est. Tax Liability</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $current_income > 0 ? round(($current_deductions / $current_income) * 100, 1) : 0; ?>%</div>
                <div class="stat-label">Deduction Coverage</div>
            </div>
        </div>

        <!-- Tax Saving Optimizer + Recommendations -->
        <div class="card">
            <h2>🧠 Tax Saving Optimizer (80C / 80D)</h2>
            <p style="color: var(--text-light); margin-bottom: 16px;">
                Based on your recorded deductions, here is how much more you can invest in key tax-saving sections and the
                <strong>approximate tax you can still save</strong> (assuming the 30% slab).
            </p>

            <div class="optimizer-grid">
                <div class="optimizer-item">
                    <div class="optimizer-label">Section 80C Remaining Limit</div>
                    <div class="optimizer-value">
                        ₹<?php echo number_format($remaining_80c, 0); ?>
                    </div>
                    <p style="color: var(--text-light); font-size: 13px; margin-top: 6px;">
                        Potential tax saving: <strong>₹<?php echo number_format($potential_tax_saving_80c, 0); ?></strong><br>
                        (ELSS, PPF, Life Insurance, etc.)
                    </p>
                </div>
                <div class="optimizer-item">
                    <div class="optimizer-label">Section 80D Remaining Limit</div>
                    <div class="optimizer-value">
                        ₹<?php echo number_format($remaining_80d, 0); ?>
                    </div>
                    <p style="color: var(--text-light); font-size: 13px; margin-top: 6px;">
                        Potential tax saving: <strong>₹<?php echo number_format($potential_tax_saving_80d, 0); ?></strong><br>
                        (Health insurance for self/family/parents)
                    </p>
                </div>
            </div>

            <?php if (count($recommendations) > 0): ?>
                <h3 style="margin-top: 24px;">🎯 Smart Suggestions</h3>
                <p style="color: var(--text-light); margin-bottom: 16px;">
                    Personalized ideas to reduce your tax liability:
                </p>

                <?php foreach ($recommendations as $rec): ?>
                    <div class="recommendation-item <?php echo $rec['type']; ?>">
                        <div class="rec-title"><?php echo htmlspecialchars($rec['title']); ?></div>
                        <div class="rec-description"><?php echo htmlspecialchars($rec['description']); ?></div>
                        <?php if ($rec['impact'] > 0): ?>
                            <span class="rec-impact">💰 Potential Tax Saving: ₹<?php echo number_format($rec['impact'], 0); ?></span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p style="color: var(--text-light); margin-top: 10px;">
                    You are already utilizing the major tax-saving sections well for this year.
                </p>
            <?php endif; ?>
        </div>

        <!-- Deductions Breakdown -->
        <?php if (count($deductions_by_category) > 0): ?>
            <div class="card">
                <h2>Current Deductions by Category</h2>
                <div class="chart-container">
                    <canvas id="deductionsChart"></canvas>
                </div>
            </div>
        <?php endif; ?>

        <!-- Investment Opportunities -->
        <div class="card">
            <h2>💼 Tax-Saving Investment Options</h2>
            <p style="color: var(--text-light); margin-bottom: 20px;">
                These investments offer dual benefits: tax deduction under Section 80C and wealth creation:
            </p>

            <table class="investment-table">
                <thead>
                    <tr>
                        <th>Investment Option</th>
                        <th>Annual Limit</th>
                        <th>Expected Return</th>
                        <th>Tax Section</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tax_saving_investments as $inv): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($inv['name']); ?></td>
                            <td>₹<?php echo number_format($inv['limit'], 0); ?></td>
                            <td><?php echo htmlspecialchars($inv['return']); ?></td>
                            <td><?php echo htmlspecialchars($inv['section']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="highlight-box">
                <strong>💡 Smart Strategy:</strong> Combining multiple investment options across different sections can help you maximize deductions while building wealth. For example, invest ₹1,50,000 in ELSS funds (80C), ₹50,000 in health insurance (80D), and ₹1,50,000 in NPS (80CCD).
            </div>
        </div>

        <!-- Interactive Tax Planning Checklist -->
        <div class="card">
            <h2>📋 Interactive Tax Saving Checklist</h2>
            <p style="color: var(--text-light); margin-bottom: 10px;">
                Tick the items you have already utilized this year. The tool will estimate the
                <strong>total deductions covered by this checklist</strong>.
            </p>

            <div class="checklist-group">
                <h3>Standard & Salary Related</h3>
                <div class="checklist-item">
                    <input type="checkbox" class="tax-check" data-amount="50000">
                    <span>Standard Deduction (approx. ₹50,000)</span>
                </div>
                <div class="checklist-item">
                    <input type="checkbox" class="tax-check" data-amount="2400">
                    <span>Professional Tax (approx. ₹2,400)</span>
                </div>
            </div>

            <div class="checklist-group">
                <h3>Investment & Insurance</h3>
                <div class="checklist-item">
                    <input type="checkbox" class="tax-check" data-amount="150000">
                    <span>Section 80C fully utilized (₹1,50,000)</span>
                </div>
                <div class="checklist-item">
                    <input type="checkbox" class="tax-check" data-amount="50000">
                    <span>Section 80D health insurance (approx. ₹50,000)</span>
                </div>
            </div>

            <div class="checklist-group">
                <h3>Other Deductions</h3>
                <div class="checklist-item">
                    <input type="checkbox" class="tax-check" data-amount="25000">
                    <span>Education loan interest / 80E (approx. ₹25,000)</span>
                </div>
                <div class="checklist-item">
                    <input type="checkbox" class="tax-check" data-amount="20000">
                    <span>Charitable donations / 80G (approx. ₹20,000)</span>
                </div>
            </div>

            <div class="highlight-box" id="checklist-summary">
                <strong>Total Deductions from Checklist:</strong>
                <span id="checklist-total" style="font-family: 'Playfair Display', serif; font-size: 20px; margin-left: 6px;">
                    ₹0
                </span>
            </div>
        </div>

        <!-- Professional Help CTA -->
        <div class="card" style="background: linear-gradient(135deg, rgba(212, 175, 55, 0.1) 0%, rgba(39, 174, 96, 0.1) 100%);">
            <h2>🤝 Get Expert Tax Planning Assistance</h2>
            <p style="color: var(--text-dark); margin-bottom: 20px;">
                While this tool provides general guidance, professional tax advisors can help you with:
            </p>
            <ul style="margin: 15px 0; margin-left: 20px; color: var(--text-dark);">
                <li><strong>Personalized Strategy:</strong> Tailored tax planning based on your specific financial situation</li>
                <li><strong>Compliance Check:</strong> Ensure all deductions are valid and claimed correctly</li>
                <li><strong>ITR Filing:</strong> Professional assistance with Income Tax Return preparation and submission</li>
                <li><strong>Documentation:</strong> Proper organization and maintenance of tax documents</li>
                <li><strong>Ongoing Guidance:</strong> Regular advice on tax law changes and optimization opportunities</li>
            </ul>

            <a href="professionals.php" class="btn" style="background: var(--accent); color: var(--primary);">
                Browse Tax Professionals
            </a>
        </div>

        <!-- Quick Links -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-top: 30px;">
            <a href="tax_tracker.php" class="btn">📝 Deductions Tracker</a>
            <a href="tax_calculator.php" class="btn">💻 Tax Calculator</a>
            <a href="tax_summary.php" class="btn">📊 Tax Summary</a>
            <a href="itr_preparation.php" class="btn">📄 ITR Preparation</a>
        </div>
    </div>

    <script>
        const deductionData = <?php echo json_encode(array_column($deductions_by_category, 'category')); ?>;
        const deductionValues = <?php echo json_encode(array_column($deductions_by_category, 'total')); ?>;
        
        if (deductionData.length > 0) {
            const ctx = document.getElementById('deductionsChart');
            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: deductionData,
                    datasets: [{
                        data: deductionValues,
                        backgroundColor: [
                            '#d4af37', '#27ae60', '#e74c3c', '#3498db',
                            '#f39c12', '#9b59b6', '#1abc9c', '#34495e'
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
                            labels: {
                                font: { family: "'Inter', sans-serif", size: 13 },
                                padding: 15
                            }
                        }
                    }
                }
            });
        }

        // Interactive checklist total updater
        function formatINR(amount) {
            return amount.toLocaleString('en-IN', {
                maximumFractionDigits: 0
            });
        }

        function updateChecklistTotal() {
            const checks = document.querySelectorAll('.tax-check');
            let total = 0;
            checks.forEach(chk => {
                if (chk.checked) {
                    const amt = parseFloat(chk.getAttribute('data-amount')) || 0;
                    total += amt;
                }
            });
            const totalEl = document.getElementById('checklist-total');
            if (totalEl) {
                totalEl.textContent = '₹' + formatINR(total);
            }
        }

        document.querySelectorAll('.tax-check').forEach(chk => {
            chk.addEventListener('change', updateChecklistTotal);
        });
    </script>
</body>
</html>
