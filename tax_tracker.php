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

// Handle add deduction
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $category = $_POST['category'];
    $description = $_POST['description'];
    $amount = floatval($_POST['amount']);
    $deduction_date = $_POST['deduction_date'];

    if (empty($category) || $amount <= 0) {
        $error = 'Please fill all required fields with valid values';
    } else {
        $sql = "INSERT INTO tax_deductions (user_id, category, description, amount, deduction_date) VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("issds", $user_id, $category, $description, $amount, $deduction_date);
        
        if ($stmt->execute()) {
            $message = 'Deduction added successfully!';
        } else {
            $error = 'Failed to add deduction. Please try again.';
        }
    }
}

// Handle delete deduction
if (isset($_POST['delete_id'])) {
    $delete_id = intval($_POST['delete_id']);
    $sql = "DELETE FROM tax_deductions WHERE id = ? AND user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $delete_id, $user_id);
    if ($stmt->execute()) {
        $message = 'Deduction deleted successfully!';
    }
}

// Fetch all deductions
$deductions_sql = "SELECT * FROM tax_deductions WHERE user_id = ? ORDER BY deduction_date DESC";
$deductions_stmt = $conn->prepare($deductions_sql);
$deductions_stmt->bind_param("i", $user_id);
$deductions_stmt->execute();
$deductions_result = $deductions_stmt->get_result();
$deductions = [];

while ($row = $deductions_result->fetch_assoc()) {
    $deductions[] = $row;
}

// Calculate deductions by category
$category_totals = [];
foreach ($deductions as $ded) {
    if (!isset($category_totals[$ded['category']])) {
        $category_totals[$ded['category']] = 0;
    }
    $category_totals[$ded['category']] += $ded['amount'];
}

$total_deductions = array_sum(array_column($deductions, 'amount'));

$deduction_categories = [
    'Section 80C - Insurance & Investment',
    'Section 80D - Health Insurance',
    'Section 80E - Education Loan',
    'Section 80G - Charitable Donations',
    'Section 80TTA - Savings Bank Interest',
    'Standard Deduction',
    'Medical Expenses',
    'Education Expenses',
    'Home Loan Interest',
    'Business Expenses',
    'Professional Fees',
    'Other Deductions'
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tax Deductions Tracker - FinCart</title>
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

        .alert {
            padding: 16px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .alert-success {
            background: rgba(39, 174, 96, 0.1);
            border: 1px solid var(--success);
            color: var(--success);
        }

        .alert-danger {
            background: rgba(231, 76, 60, 0.1);
            border: 1px solid var(--danger);
            color: var(--danger);
        }

        .content-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 40px;
        }

        .card {
            background: var(--white);
            padding: 30px;
            border-radius: 20px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
        }

        .card h2 {
            font-family: 'Playfair Display', serif;
            font-size: 28px;
            color: var(--primary);
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 18px;
        }

        label {
            display: block;
            font-weight: 600;
            color: var(--primary);
            margin-bottom: 8px;
            font-size: 14px;
        }

        input[type="number"],
        input[type="text"],
        input[type="date"],
        select,
        textarea {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid rgba(0, 0, 0, 0.1);
            border-radius: 10px;
            font-size: 15px;
            font-family: 'Inter', sans-serif;
            transition: all 0.3s ease;
        }

        input:focus,
        select:focus,
        textarea:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.1);
        }

        .btn {
            width: 100%;
            padding: 12px 20px;
            border: none;
            border-radius: 50px;
            cursor: pointer;
            font-weight: 600;
            font-size: 15px;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: var(--primary);
            color: var(--white);
        }

        .btn-primary:hover {
            background: var(--accent);
            color: var(--primary);
            transform: translateY(-2px);
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .summary-stat {
            background: var(--off-white);
            padding: 15px;
            border-radius: 10px;
            text-align: center;
            border-left: 4px solid var(--accent);
        }

        .summary-value {
            font-family: 'Playfair Display', serif;
            font-size: 24px;
            font-weight: 700;
            color: var(--primary);
        }

        .summary-label {
            font-size: 13px;
            color: var(--text-light);
            margin-top: 5px;
        }

        .deductions-list {
            display: grid;
            gap: 15px;
        }

        .deduction-item {
            background: var(--off-white);
            padding: 20px;
            border-radius: 12px;
            border-left: 5px solid var(--accent);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .deduction-info h4 {
            color: var(--primary);
            margin-bottom: 5px;
        }

        .deduction-category {
            font-size: 13px;
            color: var(--text-light);
        }

        .deduction-amount {
            font-family: 'Playfair Display', serif;
            font-size: 20px;
            font-weight: 700;
            color: var(--primary);
        }

        .btn-delete {
            padding: 8px 16px;
            background: var(--danger);
            color: var(--white);
            border: none;
            border-radius: 20px;
            cursor: pointer;
            font-weight: 600;
            font-size: 13px;
            transition: all 0.3s ease;
        }

        .btn-delete:hover {
            opacity: 0.9;
        }

        .chart-container {
            position: relative;
            height: 300px;
            margin-top: 20px;
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--text-light);
        }

        .category-breakdown {
            margin-top: 30px;
        }

        .category-item {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        }

        .category-item:last-child {
            border-bottom: none;
        }

        .category-name {
            font-weight: 600;
        }

        .category-amount {
            font-family: 'Playfair Display', serif;
            font-weight: 700;
            color: var(--success);
        }

        @media (max-width: 768px) {
            .content-grid {
                grid-template-columns: 1fr;
            }

            .deduction-item {
                flex-direction: column;
                align-items: flex-start;
            }

            .deduction-amount {
                margin-top: 10px;
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
            <h1>📝 Tax Deductions Tracker</h1>
            <p>Track and manage your tax-deductible expenses</p>
        </div>

        <!-- Disclaimer -->
        <div class="disclaimer">
            ⚠️ <strong>Tax Deduction Information:</strong> The categories listed are based on Indian Income Tax Rules. Please verify applicability with a tax professional before claiming any deduction.
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success">
                <span>✓</span>
                <span><?php echo htmlspecialchars($message); ?></span>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger">
                <span>✕</span>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <div class="content-grid">
            <!-- Add Deduction Form -->
            <div class="card">
                <h2>Add Deduction</h2>
                <form method="POST">
                    <input type="hidden" name="action" value="add">

                    <div class="form-group">
                        <label for="category">Category</label>
                        <select id="category" name="category" required>
                            <option value="">Select a category</option>
                            <?php foreach ($deduction_categories as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat); ?>"><?php echo htmlspecialchars($cat); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" placeholder="e.g., HDFC Life Insurance Premium" rows="2"></textarea>
                    </div>

                    <div class="form-group">
                        <label for="amount">Amount (₹)</label>
                        <input type="number" id="amount" name="amount" step="0.01" min="0" placeholder="0.00" required>
                    </div>

                    <div class="form-group">
                        <label for="deduction_date">Date</label>
                        <input type="date" id="deduction_date" name="deduction_date" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>

                    <button type="submit" class="btn btn-primary">Add Deduction</button>
                </form>
            </div>

            <!-- Deductions Summary -->
            <div class="card">
                <h2>Summary</h2>
                <div class="summary-grid">
                    <div class="summary-stat">
                        <div class="summary-value"><?php echo count($deductions); ?></div>
                        <div class="summary-label">Total Deductions</div>
                    </div>
                    <div class="summary-stat">
                        <div class="summary-value">₹<?php echo number_format($total_deductions, 0); ?></div>
                        <div class="summary-label">Total Amount</div>
                    </div>
                    <div class="summary-stat">
                        <div class="summary-value"><?php echo count($category_totals); ?></div>
                        <div class="summary-label">Categories</div>
                    </div>
                </div>

                <?php if (count($category_totals) > 0): ?>
                    <div class="category-breakdown">
                        <h3 style="color: var(--primary); margin-bottom: 15px; font-size: 16px;">By Category</h3>
                        <?php foreach ($category_totals as $cat => $amount): ?>
                            <div class="category-item">
                                <span class="category-name"><?php echo htmlspecialchars($cat); ?></span>
                                <span class="category-amount">₹<?php echo number_format($amount, 2); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Deductions List -->
        <?php if (count($deductions) > 0): ?>
            <div class="card">
                <h2>All Deductions</h2>
                <div class="deductions-list">
                    <?php foreach ($deductions as $deduction): ?>
                        <div class="deduction-item">
                            <div class="deduction-info">
                                <h4><?php echo htmlspecialchars($deduction['description'] ?: $deduction['category']); ?></h4>
                                <span class="deduction-category">
                                    <?php echo htmlspecialchars($deduction['category']); ?> • 
                                    <?php echo date('M d, Y', strtotime($deduction['deduction_date'])); ?>
                                </span>
                            </div>
                            <div style="display: flex; gap: 15px; align-items: center;">
                                <div class="deduction-amount">₹<?php echo number_format($deduction['amount'], 2); ?></div>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this deduction?');">
                                    <input type="hidden" name="delete_id" value="<?php echo $deduction['id']; ?>">
                                    <button type="submit" class="btn-delete">Delete</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Chart -->
            <div class="card">
                <h2>Deductions Distribution</h2>
                <div class="chart-container">
                    <canvas id="deductionsChart"></canvas>
                </div>
            </div>
        <?php else: ?>
            <div class="card">
                <div class="empty-state">
                    <p>📝 No deductions tracked yet. Start by adding your first deduction!</p>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        const categoryData = <?php echo json_encode($category_totals); ?>;
        
        if (Object.keys(categoryData).length > 0) {
            const ctx = document.getElementById('deductionsChart');
            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: Object.keys(categoryData),
                    datasets: [{
                        data: Object.values(categoryData),
                        backgroundColor: [
                            '#d4af37', '#27ae60', '#e74c3c', '#3498db',
                            '#f39c12', '#9b59b6', '#1abc9c', '#34495e',
                            '#c0392b', '#16a085', '#2980b9', '#8e44ad'
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
    </script>
</body>
</html>
