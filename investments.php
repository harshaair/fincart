<?php
require_once "includes/security.php";
init_secure_session();
include "db.php";

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.html');
    exit();
}

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    enforce_csrf();
}

// Handle add investment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_investment') {
    $name = $_POST['investment_name'];
    $amount = floatval($_POST['investment_amount']);
    $returns = floatval($_POST['current_value']);
    $type = $_POST['investment_type'] ?? 'Other';

    if (empty($name) || $amount <= 0) {
        $error = 'Please fill all fields with valid values';
    } else {
        $sql = "INSERT INTO investments (user_id, name, amount, returns, type, created_at) VALUES (?, ?, ?, ?, ?, NOW())";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("isdds", $user_id, $name, $amount, $returns, $type);
        
        if ($stmt->execute()) {
            $message = 'Investment added successfully!';
            $_POST = array();
        } else {
            $error = 'Failed to add investment. Please try again.';
        }
    }
}

// Handle update investment value
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_value') {
    $investment_id = intval($_POST['investment_id']);
    $new_returns = floatval($_POST['new_value']);

    // Verify investment belongs to user
    $verify_sql = "SELECT id FROM investments WHERE id = ? AND user_id = ?";
    $verify_stmt = $conn->prepare($verify_sql);
    $verify_stmt->bind_param("ii", $investment_id, $user_id);
    $verify_stmt->execute();
    $verify_result = $verify_stmt->get_result();

    if ($verify_result->num_rows > 0) {
        $sql = "UPDATE investments SET returns = ? WHERE id = ? AND user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("dii", $new_returns, $investment_id, $user_id);

        if ($stmt->execute()) {
            $message = 'Investment value updated successfully!';
        } else {
            $error = 'Failed to update investment. Please try again.';
        }
    } else {
        $error = 'Investment not found.';
    }
}

// Handle delete investment
if (isset($_POST['delete_id'])) {
    $delete_id = intval($_POST['delete_id']);
    $sql = "DELETE FROM investments WHERE id = ? AND user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $delete_id, $user_id);
    if ($stmt->execute()) {
        $message = 'Investment deleted successfully!';
    }
}

// Fetch all investments for the user
$investments_sql = "SELECT * FROM investments WHERE user_id = ? ORDER BY created_at DESC";
$investments_stmt = $conn->prepare($investments_sql);
$investments_stmt->bind_param("i", $user_id);
$investments_stmt->execute();
$investments_result = $investments_stmt->get_result();
$investments = [];

while ($row = $investments_result->fetch_assoc()) {
    $row['profit_loss'] = $row['returns'];
    $row['return_percentage'] = ($row['returns'] / $row['amount']) * 100;
    $investments[] = $row;
}

// Calculate portfolio summary
$total_invested = array_sum(array_column($investments, 'amount'));
$total_returns = array_sum(array_column($investments, 'returns'));
$total_profit_loss = $total_returns - $total_invested;
$total_return = $total_invested > 0 ? ($total_profit_loss / $total_invested) * 100 : 0;

// Group by investment type
$portfolio_by_type = [];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Investment Portfolio - FinCart</title>
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

        input[type="text"],
        input[type="number"],
        input[type="date"],
        select {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid rgba(0, 0, 0, 0.1);
            border-radius: 10px;
            font-size: 15px;
            font-family: 'Inter', sans-serif;
            transition: all 0.3s ease;
        }

        input:focus,
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

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }

        .summary-stat {
            background: var(--off-white);
            padding: 15px;
            border-radius: 10px;
            border-left: 4px solid var(--accent);
        }

        .summary-stat.positive {
            border-left-color: var(--success);
        }

        .summary-stat.negative {
            border-left-color: var(--danger);
        }

        .summary-stat-value {
            font-family: 'Playfair Display', serif;
            font-size: 24px;
            font-weight: 700;
            color: var(--primary);
        }

        .summary-stat-label {
            font-size: 13px;
            color: var(--text-light);
            margin-top: 5px;
        }

        .investments-list {
            display: grid;
            gap: 20px;
        }

        .investment-item {
            background: var(--off-white);
            padding: 25px;
            border-radius: 15px;
            border-left: 5px solid var(--accent);
            display: flex;
            justify-content: space-between;
            align-items: start;
        }

        .investment-item.positive {
            border-left-color: var(--success);
            background: rgba(39, 174, 96, 0.05);
        }

        .investment-item.negative {
            border-left-color: var(--danger);
            background: rgba(231, 76, 60, 0.05);
        }

        .investment-info {
            flex: 1;
        }

        .investment-name {
            color: var(--primary);
            margin-bottom: 8px;
        }

        .investment-type {
            display: inline-block;
            padding: 4px 12px;
            background: var(--info);
            color: var(--white);
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .investment-details {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            font-size: 14px;
            margin-bottom: 15px;
        }

        .detail-item {
            color: var(--text-light);
        }

        .detail-value {
            font-weight: 700;
            color: var(--primary);
            display: block;
        }

        .investment-return {
            font-family: 'Playfair Display', serif;
            font-size: 20px;
            font-weight: 700;
            text-align: right;
            color: var(--success);
            margin-bottom: 15px;
        }

        .investment-return.negative {
            color: var(--danger);
        }

        .investment-actions {
            display: flex;
            gap: 8px;
            flex-direction: column;
            width: 120px;
        }

        .btn-action {
            padding: 8px 12px;
            border: none;
            border-radius: 20px;
            cursor: pointer;
            font-weight: 600;
            font-size: 12px;
            transition: all 0.3s ease;
        }

        .btn-update {
            background: var(--info);
            color: var(--white);
        }

        .btn-update:hover {
            opacity: 0.9;
            transform: translateY(-2px);
        }

        .btn-delete {
            background: var(--danger);
            color: var(--white);
        }

        .btn-delete:hover {
            opacity: 0.9;
            transform: translateY(-2px);
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

        .empty-state p {
            font-size: 16px;
            margin-bottom: 20px;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 2000;
            align-items: center;
            justify-content: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: var(--white);
            padding: 30px;
            border-radius: 20px;
            max-width: 400px;
            width: 90%;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .modal-header h2 {
            font-family: 'Playfair Display', serif;
            color: var(--primary);
        }

        .close-btn {
            background: none;
            border: none;
            font-size: 28px;
            cursor: pointer;
            color: var(--text-light);
        }

        @media (max-width: 768px) {
            .content-grid {
                grid-template-columns: 1fr;
            }

            .investment-item {
                flex-direction: column;
            }

            .investment-actions {
                width: 100%;
                flex-direction: row;
                margin-top: 15px;
            }

            .btn-action {
                flex: 1;
            }

            .summary-grid {
                grid-template-columns: 1fr;
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
            <h1>Investment Portfolio</h1>
            <p>Track and manage your investments across different asset classes</p>
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
            <!-- Add Investment Form -->
            <div class="card">
                <h2>Add Investment</h2>
                <form method="POST">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="add_investment">

                    <div class="form-group">
                        <label for="investment_name">Investment Name</label>
                        <input type="text" id="investment_name" name="investment_name" placeholder="e.g., Apple Stocks" required>
                    </div>

                    <div class="form-group">
                        <label for="investment_amount">Amount Invested (₹)</label>
                        <input type="number" id="investment_amount" name="investment_amount" step="0.01" min="0" placeholder="10000" required>
                    </div>

                    <div class="form-group">
                        <label for="current_value">Current Value (₹)</label>
                        <input type="number" id="current_value" name="current_value" step="0.01" min="0" placeholder="12000" required>
                    </div>

                    <div class="form-group">
                        <label for="buy_date">Purchase Date</label>
                        <input type="date" id="buy_date" name="buy_date" required>
                    </div>

                    <button type="submit" class="btn btn-primary">Add Investment</button>
                </form>
            </div>

            <!-- Portfolio Summary -->
            <div class="card">
                <h2>Portfolio Summary</h2>
                <div class="summary-grid">
                    <div class="summary-stat">
                        <div class="summary-stat-value">₹<?php echo number_format($total_invested, 2); ?></div>
                        <div class="summary-stat-label">Total Invested</div>
                    </div>
                    <div class="summary-stat">
                        <div class="summary-stat-value">₹<?php echo number_format($total_returns, 2); ?></div>
                        <div class="summary-stat-label">Total Returns</div>
                    </div>
                    <div class="summary-stat <?php echo $total_profit_loss >= 0 ? 'positive' : 'negative'; ?>">
                        <div class="summary-stat-value"><?php echo ($total_profit_loss >= 0 ? '+' : ''); ?>₹<?php echo number_format($total_profit_loss, 2); ?></div>
                        <div class="summary-stat-label">Profit/Loss</div>
                    </div>
                    <div class="summary-stat <?php echo $total_return >= 0 ? 'positive' : 'negative'; ?>">
                        <div class="summary-stat-value"><?php echo ($total_return >= 0 ? '+' : ''); ?><?php echo number_format($total_return, 2); ?>%</div>
                        <div class="summary-stat-label">Total Return</div>
                    </div>
                </div>

                <?php if (count($portfolio_by_type) > 0): ?>
                    <div class="chart-container">
                        <canvas id="portfolioChart"></canvas>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Investments List -->
        <?php if (count($investments) > 0): ?>
            <div class="card">
                <h2>Your Investments</h2>
                <div class="investments-list">
                    <?php foreach ($investments as $investment): ?>
                        <div class="investment-item <?php echo $investment['profit_loss'] >= 0 ? 'positive' : 'negative'; ?>">
                            <div class="investment-inf⭐>
                                <h3 class="investment-name"><?php echo htmlspecialchars($investment['name']); ?></h3>
                                
                                <div class="investment-details">
                                    <div class="detail-item">
                                        Amount: <span class="detail-value">₹<?php echo number_format($investment['amount'], 2); ?></span>
                                    </div>
                                    <div class="detail-item">
                                        Returns: <span class="detail-value">₹<?php echo number_format($investment['returns'], 2); ?></span>
                                    </div>
                                    <div class="detail-item">
                                        Return %: <span class="detail-value <?php echo $investment['return_percentage'] >= 0 ? 'style="color: var(--success);"' : 'style="color: var(--danger);"'; ?>">
                                            <?php echo ($investment['return_percentage'] >= 0 ? '+' : ''); ?><?php echo number_format($investment['return_percentage'], 2); ?>%
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <div class="investment-actions">
                                <button class="btn-action btn-update" onclick="openUpdateModal(<?php echo $investment['id']; ?>, <?php echo $investment['returns']; ?>)">Update Returns</button>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this investment?');">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="delete_id" value="<?php echo $investment['id']; ?>">
                                    <button type="submit" class="btn-action btn-delete">Delete</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="card">
                <div class="empty-state">
                    <p>📈 No investments yet. Start building your investment portfolio!</p>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Update Value Modal -->
    <div id="updateModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Update Investment Value</h2>
                <button class="close-btn" onclick="closeUpdateModal()">×</button>
            </div>
            <form method="POST">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="update_value">
                <input type="hidden" id="modal_investment_id" name="investment_id" value="">

                <div class="form-group">
                    <label for="new_value">New Current Value (₹)</label>
                    <input type="number" id="new_value" name="new_value" step="0.01" min="0" placeholder="0" required>
                </div>

                <button type="submit" class="btn btn-primary">Update Value</button>
            </form>
        </div>
    </div>

    <script>
        function openUpdateModal(investmentId, currentValue) {
            document.getElementById('modal_investment_id').value = investmentId;
            document.getElementById('new_value').value = currentValue;
            document.getElementById('updateModal').classList.add('active');
            document.getElementById('new_value').focus();
        }

        function closeUpdateModal() {
            document.getElementById('updateModal').classList.remove('active');
        }

        // Close modal when clicking outside
        document.getElementById('updateModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeUpdateModal();
            }
        });

        // Portfolio pie chart
        const portfolioData = <?php echo json_encode($portfolio_by_type); ?>;
        const types = Object.keys(portfolioData);
        const values = Object.values(portfolioData);

        if (types.length > 0) {
            const ctx = document.getElementById('portfolioChart');
            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: types,
                    datasets: [{
                        data: values,
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
