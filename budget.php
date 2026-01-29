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
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $category = $_POST['category'];
    $limit = floatval($_POST['limit']);

    if (empty($category) || $limit <= 0) {
        $error = 'Please fill all fields with valid values';
    } else {
        // Check if budget exists for this category
        $check_sql = "SELECT id FROM budgets WHERE user_id = ? AND category = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("is", $user_id, $category);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows > 0) {
            // Update existing budget
            $sql = "UPDATE budgets SET limit_amount = ? WHERE user_id = ? AND category = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("dis", $limit, $user_id, $category);
        } else {
            // Insert new budget
            $sql = "INSERT INTO budgets (user_id, category, limit_amount, month_year) VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $month_year = date('Y-m');
            $stmt->bind_param("isds", $user_id, $category, $limit, $month_year);
        }

        if ($stmt->execute()) {
            $message = 'Budget saved successfully!';
        } else {
            $error = 'Failed to save budget. Please try again.';
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
        $message = 'Budget deleted successfully!';
    }
}

// Fetch all budgets for the user
$budgets_sql = "SELECT * FROM budgets WHERE user_id = ? ORDER BY category";
$budgets_stmt = $conn->prepare($budgets_sql);
$budgets_stmt->bind_param("i", $user_id);
$budgets_stmt->execute();
$budgets_result = $budgets_stmt->get_result();
$budgets = [];

while ($row = $budgets_result->fetch_assoc()) {
    // Calculate spent amount for this category from current month
    $current_month = date('Y-m');
    $spent_sql = "SELECT SUM(amount) as spent FROM transactions WHERE user_id = ? AND category = ? AND type = 'expense' AND DATE_FORMAT(date, '%Y-%m') = ?";
    $spent_stmt = $conn->prepare($spent_sql);
    $spent_stmt->bind_param("iss", $user_id, $row['category'], $current_month);
    $spent_stmt->execute();
    $spent_result = $spent_stmt->get_result();
    $spent_data = $spent_result->fetch_assoc();
    $row['spent'] = $spent_data['spent'] ?? 0;
    $row['percentage'] = min(($row['spent'] / $row['limit_amount']) * 100, 100);
    $budgets[] = $row;
}

// Calculate total budget and spending
$total_budget = array_sum(array_column($budgets, 'limit_amount'));
$total_spent = array_sum(array_column($budgets, 'spent'));
$total_percentage = $total_budget > 0 ? ($total_spent / $total_budget) * 100 : 0;

// Predefined expense categories
$expense_categories = ['Food', 'Transportation', 'Entertainment', 'Utilities', 'Shopping', 'Healthcare', 'Education', 'Other Expense'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Budget Tracker - FinCart</title>
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
            margin-bottom: 40px;
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
            margin-bottom: 20px;
        }

        label {
            display: block;
            font-weight: 600;
            color: var(--primary);
            margin-bottom: 8px;
            font-size: 14px;
        }

        input[type="number"],
        select {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid rgba(0, 0, 0, 0.1);
            border-radius: 10px;
            font-size: 15px;
            font-family: 'Inter', sans-serif;
            transition: all 0.3s ease;
        }

        input[type="number"]:focus,
        select:focus {
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

        .summary-section {
            margin-bottom: 30px;
        }

        .overall-progress {
            margin-bottom: 30px;
        }

        .progress-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }

        .progress-header span {
            font-weight: 600;
        }

        .progress-bar {
            width: 100%;
            height: 12px;
            background: rgba(0, 0, 0, 0.05);
            border-radius: 10px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--success) 0%, var(--accent) 50%, var(--danger) 100%);
            width: 0%;
            transition: width 0.3s ease;
        }

        .budgets-list {
            margin-top: 30px;
        }

        .budget-item {
            background: var(--off-white);
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .budget-info h3 {
            color: var(--primary);
            margin-bottom: 8px;
        }

        .budget-stats {
            display: flex;
            gap: 20px;
            margin-top: 10px;
        }

        .stat {
            font-size: 14px;
            color: var(--text-light);
        }

        .stat-value {
            font-weight: 700;
            color: var(--primary);
        }

        .budget-actions {
            display: flex;
            gap: 10px;
        }

        .btn-delete {
            padding: 8px 16px;
            background: var(--danger);
            color: var(--white);
            border: none;
            border-radius: 20px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .btn-delete:hover {
            opacity: 0.9;
            transform: translateY(-2px);
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--text-light);
        }

        .empty-state p {
            font-size: 16px;
            margin-bottom: 20px;
        }

        .chart-container {
            position: relative;
            height: 300px;
            margin-top: 30px;
        }

        @media (max-width: 768px) {
            .content-grid {
                grid-template-columns: 1fr;
            }

            .budget-item {
                flex-direction: column;
                align-items: flex-start;
            }

            .budget-actions {
                width: 100%;
                margin-top: 15px;
            }

            .btn-delete {
                flex: 1;
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
            <h1>Budget Tracker</h1>
            <p>Set and monitor your spending limits by category</p>
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
            <!-- Add Budget Form -->
            <div class="card">
                <h2>Set Budget</h2>
                <form method="POST">
                    <div class="form-group">
                        <label for="category">Category</label>
                        <select id="category" name="category" required>
                            <option value="">Select a category</option>
                            <?php foreach ($expense_categories as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat); ?>"><?php echo htmlspecialchars($cat); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="limit">Monthly Limit (₹)</label>
                        <input type="number" id="limit" name="limit" step="0.01" min="0" placeholder="0.00" required>
                    </div>

                    <button type="submit" class="btn btn-primary">Save Budget</button>
                </form>
            </div>

            <!-- Budget Summary -->
            <div class="card">
                <h2>Budget Overview</h2>
                <div class="summary-section">
                    <div class="overall-progress">
                        <div class="progress-header">
                            <span>Overall Budget</span>
                            <span><?php echo round($total_percentage, 1); ?>%</span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?php echo min($total_percentage, 100); ?>%;"></div>
                        </div>
                        <div class="progress-header" style="margin-top: 10px;">
                            <span>₹<?php echo number_format($total_spent, 2); ?> / ₹<?php echo number_format($total_budget, 2); ?></span>
                        </div>
                    </div>
                </div>

                <?php if (count($budgets) > 0): ?>
                    <div class="chart-container">
                        <canvas id="budgetChart"></canvas>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <p>No budgets set yet. Create your first budget to get started!</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Budget List -->
        <?php if (count($budgets) > 0): ?>
            <div class="card">
                <h2>Category Budgets</h2>
                <div class="budgets-list">
                    <?php foreach ($budgets as $budget): ?>
                        <div class="budget-item">
                            <div class="budget-info" style="flex: 1;">
                                <h3><?php echo htmlspecialchars($budget['category']); ?></h3>
                                <div style="margin-bottom: 10px;">
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="width: <?php echo $budget['percentage']; ?>%;"></div>
                                    </div>
                                </div>
                                <div class="budget-stats">
                                    <div class="stat">
                                        Spent: <span class="stat-value">₹<?php echo number_format($budget['spent'], 2); ?></span>
                                    </div>
                                    <div class="stat">
                                        Limit: <span class="stat-value">₹<?php echo number_format($budget['limit_amount'], 2); ?></span>
                                    </div>
                                    <div class="stat">
                                        Remaining: <span class="stat-value" style="color: <?php echo ($budget['limit_amount'] - $budget['spent']) >= 0 ? 'var(--success)' : 'var(--danger)'; ?>;">₹<?php echo number_format($budget['limit_amount'] - $budget['spent'], 2); ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="budget-actions">
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this budget?');">
                                    <input type="hidden" name="delete_id" value="<?php echo $budget['id']; ?>">
                                    <button type="submit" class="btn-delete">Delete</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Chart.js pie chart for budget distribution
        const budgetData = <?php echo json_encode(array_map(function($b) { return ['category' => $b['category'], 'amount' => (float)$b['limit_amount']]; }, $budgets)); ?>;
        
        if (budgetData.length > 0) {
            const ctx = document.getElementById('budgetChart');
            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: budgetData.map(b => b.category),
                    datasets: [{
                        data: budgetData.map(b => b.amount),
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
                                font: { family: "'Inter', sans-serif", size: 14 },
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
