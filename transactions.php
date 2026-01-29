<?php
session_start();
include "db.php";

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.html');
    exit();
}

$user_id = $_SESSION['user_id'];

// Handle delete transaction
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $transaction_id = intval($_POST['transaction_id']);
    
    // Verify transaction belongs to user
    $check_sql = "SELECT id FROM transactions WHERE id = ? AND user_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("ii", $transaction_id, $user_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $delete_sql = "DELETE FROM transactions WHERE id = ? AND user_id = ?";
        $delete_stmt = $conn->prepare($delete_sql);
        $delete_stmt->bind_param("ii", $transaction_id, $user_id);
        $delete_stmt->execute();
        $message = "Transaction deleted successfully!";
    }
}

// Get filter values
$filter_type = isset($_GET['type']) ? $_GET['type'] : 'all';
$filter_category = isset($_GET['category']) ? $_GET['category'] : 'all';
$filter_month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');

// Build query
$where_clause = "WHERE user_id = ?";
$params = [$user_id];
$param_types = "i";

if ($filter_type !== 'all') {
    $where_clause .= " AND type = ?";
    $params[] = $filter_type;
    $param_types .= "s";
}

if ($filter_category !== 'all') {
    $where_clause .= " AND category = ?";
    $params[] = $filter_category;
    $param_types .= "s";
}

if ($filter_month !== '') {
    $where_clause .= " AND DATE_FORMAT(date, '%Y-%m') = ?";
    $params[] = $filter_month;
    $param_types .= "s";
}

// Get transactions
$sql = "SELECT * FROM transactions $where_clause ORDER BY date DESC, created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param($param_types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$transactions = $result->fetch_all(MYSQLI_ASSOC);

// Get summary
$summary_sql = "SELECT 
    SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) as total_income,
    SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) as total_expense
    FROM transactions WHERE user_id = ? AND DATE_FORMAT(date, '%Y-%m') = ?";
$summary_stmt = $conn->prepare($summary_sql);
$summary_stmt->bind_param("is", $user_id, $filter_month);
$summary_stmt->execute();
$summary_result = $summary_stmt->get_result();
$summary = $summary_result->fetch_assoc();

// Get categories
$categories_sql = "SELECT DISTINCT category FROM transactions WHERE user_id = ? AND category IS NOT NULL ORDER BY category";
$categories_stmt = $conn->prepare($categories_sql);
$categories_stmt->bind_param("i", $user_id);
$categories_stmt->execute();
$categories_result = $categories_stmt->get_result();
$categories = $categories_result->fetch_all(MYSQLI_ASSOC);

$message = isset($message) ? $message : '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transactions - FinCart</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
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
            align-items: center;
        }

        .nav-links a {
            color: var(--text-dark);
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .nav-links a:hover {
            color: var(--accent);
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
            background: rgba(39, 174, 96, 0.1);
            border: 1px solid var(--success);
            color: var(--success);
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 24px;
            margin-bottom: 40px;
        }

        .summary-card {
            background: var(--white);
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            border-left: 5px solid var(--accent);
        }

        .summary-card.income {
            border-left-color: var(--success);
        }

        .summary-card.expense {
            border-left-color: var(--danger);
        }

        .summary-label {
            color: var(--text-light);
            font-size: 14px;
            margin-bottom: 10px;
        }

        .summary-value {
            font-family: 'Playfair Display', serif;
            font-size: 36px;
            font-weight: 700;
            color: var(--primary);
        }

        .summary-card.income .summary-value {
            color: var(--success);
        }

        .summary-card.expense .summary-value {
            color: var(--danger);
        }

        .controls {
            background: var(--white);
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            margin-bottom: 40px;
        }

        .controls-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .controls-header h3 {
            font-size: 20px;
            color: var(--primary);
            font-weight: 700;
        }

        .add-btn {
            padding: 12px 28px;
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

        .add-btn:hover {
            background: var(--accent);
            color: var(--primary);
            transform: translateY(-2px);
        }

        .filters {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
        }

        .filter-group label {
            font-weight: 600;
            color: var(--primary);
            margin-bottom: 8px;
            font-size: 14px;
        }

        .filter-group select,
        .filter-group input {
            padding: 10px 12px;
            border: 1px solid rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            font-family: inherit;
            font-size: 14px;
        }

        .filter-group select:focus,
        .filter-group input:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.1);
        }

        .transactions-table {
            background: var(--white);
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            overflow: hidden;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            background: var(--off-white);
            border-bottom: 2px solid rgba(0, 0, 0, 0.08);
        }

        th {
            padding: 18px 20px;
            text-align: left;
            font-weight: 700;
            color: var(--primary);
            font-size: 14px;
        }

        td {
            padding: 18px 20px;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        }

        tbody tr:hover {
            background: var(--off-white);
        }

        .badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-income {
            background: rgba(39, 174, 96, 0.1);
            color: var(--success);
        }

        .badge-expense {
            background: rgba(231, 76, 60, 0.1);
            color: var(--danger);
        }

        .amount {
            font-weight: 700;
            font-family: 'Playfair Display', serif;
            font-size: 16px;
        }

        .amount-income {
            color: var(--success);
        }

        .amount-expense {
            color: var(--danger);
        }

        .action-btn {
            padding: 6px 12px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }

        .delete-btn {
            background: rgba(231, 76, 60, 0.1);
            color: var(--danger);
        }

        .delete-btn:hover {
            background: var(--danger);
            color: var(--white);
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
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

        @media (max-width: 768px) {
            .controls-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }

            .filters {
                grid-template-columns: 1fr;
            }

            table {
                font-size: 14px;
            }

            th, td {
                padding: 12px 10px;
            }

            .summary-value {
                font-size: 24px;
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
            <nav class="nav-links">
                <a href="dashboard.php">Dashboard</a>
                <a href="transactions.php" style="color: var(--accent); font-weight: 700;">Transactions</a>
                <a href="logout.php" class="logout-btn">Logout</a>
            </nav>
        </div>
    </header>

    <!-- Main Content -->
    <div class="container">
        <div class="page-header">
            <h1>💳 Transactions</h1>
            <p>Track your income and expenses in one place</p>
        </div>

        <?php if ($message): ?>
            <div class="alert">
                <span>✓</span>
                <span><?php echo htmlspecialchars($message); ?></span>
            </div>
        <?php endif; ?>

        <!-- Summary Cards -->
        <div class="summary-grid">
            <div class="summary-card income">
                <div class="summary-label">Total Income (<?php echo $filter_month; ?>)</div>
                <div class="summary-value">₹<?php echo number_format($summary['total_income'] ?? 0, 2); ?></div>
            </div>
            <div class="summary-card expense">
                <div class="summary-label">Total Expense (<?php echo $filter_month; ?>)</div>
                <div class="summary-value">₹<?php echo number_format($summary['total_expense'] ?? 0, 2); ?></div>
            </div>
            <div class="summary-card">
                <div class="summary-label">Net Balance (<?php echo $filter_month; ?>)</div>
                <div class="summary-value" style="color: <?php echo (($summary['total_income'] ?? 0) - ($summary['total_expense'] ?? 0)) >= 0 ? 'var(--success)' : 'var(--danger)'; ?>">
                    ₹<?php echo number_format(($summary['total_income'] ?? 0) - ($summary['total_expense'] ?? 0), 2); ?>
                </div>
            </div>
        </div>

        <!-- Controls -->
        <div class="controls">
            <div class="controls-header">
                <h3>Filter & Search</h3>
                <a href="add_transaction.php" class="add-btn">+ Add Transaction</a>
            </div>
            <div class="filters">
                <div class="filter-group">
                    <label for="filter_type">Type</label>
                    <select id="filter_type" name="type" onchange="applyFilters()">
                        <option value="all">All Types</option>
                        <option value="income" <?php echo $filter_type === 'income' ? 'selected' : ''; ?>>Income</option>
                        <option value="expense" <?php echo $filter_type === 'expense' ? 'selected' : ''; ?>>Expense</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="filter_category">Category</label>
                    <select id="filter_category" name="category" onchange="applyFilters()">
                        <option value="all">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo htmlspecialchars($cat['category']); ?>" <?php echo $filter_category === $cat['category'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['category']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="filter_month">Month</label>
                    <input type="month" id="filter_month" name="month" value="<?php echo htmlspecialchars($filter_month); ?>" onchange="applyFilters()">
                </div>
            </div>
        </div>

        <!-- Transactions Table -->
        <div class="transactions-table">
            <?php if (!empty($transactions)): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Category</th>
                            <th>Description</th>
                            <th>Amount</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $trans): ?>
                            <tr>
                                <td><?php echo date('M d, Y', strtotime($trans['date'])); ?></td>
                                <td>
                                    <span class="badge badge-<?php echo $trans['type']; ?>">
                                        <?php echo ucfirst($trans['type']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($trans['category'] ?? 'Uncategorized'); ?></td>
                                <td><?php echo htmlspecialchars($trans['description'] ?? '-'); ?></td>
                                <td>
                                    <span class="amount amount-<?php echo $trans['type']; ?>">
                                        <?php echo $trans['type'] === 'income' ? '+' : '-'; ?>₹<?php echo number_format($trans['amount'], 2); ?>
                                    </span>
                                </td>
                                <td>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this transaction?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="transaction_id" value="<?php echo $trans['id']; ?>">
                                        <button type="submit" class="action-btn delete-btn">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-state-icon">📭</div>
                    <p>No transactions found. Start by adding your first transaction!</p>
                    <a href="add_transaction.php" class="add-btn">Add Transaction</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function applyFilters() {
            const type = document.getElementById('filter_type').value;
            const category = document.getElementById('filter_category').value;
            const month = document.getElementById('filter_month').value;
            
            let url = 'transactions.php?';
            if (type !== 'all') url += 'type=' + type + '&';
            if (category !== 'all') url += 'category=' + category + '&';
            if (month) url += 'month=' + month;
            
            window.location.href = url;
        }
    </script>
</body>
</html>
