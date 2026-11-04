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

// Handle add goal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_goal') {
    $goal_name = $_POST['goal_name'];
    $target_amount = floatval($_POST['target_amount']);
    $current_amount = floatval($_POST['current_amount']);
    $deadline = $_POST['deadline'];

    if (empty($goal_name) || $target_amount <= 0 || $current_amount < 0) {
        $error = 'Please fill all fields with valid values';
    } else {
        $sql = "INSERT INTO goals (user_id, goal_name, target_amount, current_amount, deadline, created_at) VALUES (?, ?, ?, ?, ?, NOW())";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("isdds", $user_id, $goal_name, $target_amount, $current_amount, $deadline);
        
        if ($stmt->execute()) {
            $message = 'Goal created successfully!';
            $_POST = array();
        } else {
            $error = 'Failed to create goal. Please try again.';
        }
    }
}

// Handle add deposit to goal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_deposit') {
    $goal_id = intval($_POST['goal_id']);
    $deposit_amount = floatval($_POST['deposit_amount']);

    if ($deposit_amount <= 0) {
        $error = 'Deposit amount must be greater than zero.';
    } else {
        $conn->begin_transaction();
        try {
            // Verify goal belongs to user with row lock
            $verify_sql = "SELECT current_amount, target_amount FROM goals WHERE id = ? AND user_id = ? FOR UPDATE";
            $verify_stmt = $conn->prepare($verify_sql);
            $verify_stmt->bind_param("ii", $goal_id, $user_id);
            $verify_stmt->execute();
            $verify_result = $verify_stmt->get_result();

            if ($verify_result->num_rows > 0) {
                $goal_data = $verify_result->fetch_assoc();
                $new_amount = min($goal_data['current_amount'] + $deposit_amount, $goal_data['target_amount']);

                $sql = "UPDATE goals SET current_amount = ? WHERE id = ? AND user_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("dii", $new_amount, $goal_id, $user_id);
                $stmt->execute();

                $conn->commit();
                $message = 'Deposit added successfully!';
            } else {
                $conn->rollback();
                $error = 'Goal not found.';
            }
        } catch (Exception $e) {
            $conn->rollback();
            $error = 'Failed to process deposit: ' . $e->getMessage();
        }
    }
}

// Handle delete goal
if (isset($_POST['delete_id'])) {
    $delete_id = intval($_POST['delete_id']);
    $sql = "DELETE FROM goals WHERE id = ? AND user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $delete_id, $user_id);
    if ($stmt->execute()) {
        $message = 'Goal deleted successfully!';
    }
}

// Fetch all goals for the user
$goals_sql = "SELECT * FROM goals WHERE user_id = ? ORDER BY deadline ASC";
$goals_stmt = $conn->prepare($goals_sql);
$goals_stmt->bind_param("i", $user_id);
$goals_stmt->execute();
$goals_result = $goals_stmt->get_result();
$goals = [];

while ($row = $goals_result->fetch_assoc()) {
    $row['percentage'] = ($row['current_amount'] / $row['target_amount']) * 100;
    $row['remaining'] = $row['target_amount'] - $row['current_amount'];
    $row['days_left'] = max(0, (strtotime($row['deadline']) - time()) / (60 * 60 * 24));
    $row['status'] = $row['current_amount'] >= $row['target_amount'] ? 'Achieved' : (time() > strtotime($row['deadline']) ? 'Overdue' : 'In Progress');
    $goals[] = $row;
}

// Calculate goals summary
$total_target = array_sum(array_column($goals, 'target_amount'));
$total_saved = array_sum(array_column($goals, 'current_amount'));
$goals_achieved = count(array_filter($goals, fn($g) => $g['status'] === 'Achieved'));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Savings Goals - FinCart</title>
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

        .btn-small {
            padding: 8px 16px;
            font-size: 14px;
            width: auto;
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

        .goals-list {
            display: grid;
            gap: 20px;
        }

        .goal-item {
            background: var(--off-white);
            padding: 25px;
            border-radius: 15px;
            border-left: 5px solid var(--accent);
        }

        .goal-item.achieved {
            border-left-color: var(--success);
            background: rgba(39, 174, 96, 0.05);
        }

        .goal-item.overdue {
            border-left-color: var(--danger);
            background: rgba(231, 76, 60, 0.05);
        }

        .goal-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
        }

        .goal-title h3 {
            color: var(--primary);
            margin-bottom: 5px;
        }

        .goal-status {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .goal-status.in-progress {
            background: rgba(52, 152, 219, 0.1);
            color: var(--info);
        }

        .goal-status.achieved {
            background: rgba(39, 174, 96, 0.1);
            color: var(--success);
        }

        .goal-status.overdue {
            background: rgba(231, 76, 60, 0.1);
            color: var(--danger);
        }

        .progress-bar {
            width: 100%;
            height: 12px;
            background: rgba(0, 0, 0, 0.08);
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 10px;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--info) 0%, var(--accent) 50%, var(--success) 100%);
            width: 0%;
            transition: width 0.3s ease;
        }

        .goal-details {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-bottom: 15px;
            font-size: 14px;
        }

        .detail-item {
            color: var(--text-light);
        }

        .detail-value {
            font-weight: 700;
            color: var(--primary);
            display: block;
        }

        .goal-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .btn-deposit {
            flex: 1;
            min-width: 120px;
            padding: 8px 16px;
            background: var(--info);
            color: var(--white);
            border: none;
            border-radius: 20px;
            cursor: pointer;
            font-weight: 600;
            font-size: 13px;
            transition: all 0.3s ease;
        }

        .btn-deposit:hover {
            opacity: 0.9;
            transform: translateY(-2px);
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
            transform: translateY(-2px);
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

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--text-light);
        }

        .empty-state p {
            font-size: 16px;
            margin-bottom: 20px;
        }

        @media (max-width: 768px) {
            .content-grid {
                grid-template-columns: 1fr;
            }

            .goal-details {
                grid-template-columns: 1fr;
            }

            .goal-actions {
                flex-direction: column;
            }

            .btn-deposit,
            .btn-delete {
                width: 100%;
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
            <h1>Savings Goals</h1>
            <p>Set financial targets and track your progress toward achieving them</p>
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
            <!-- Create Goal Form -->
            <div class="card">
                <h2>Create New Goal</h2>
                <form method="POST">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="add_goal">
                    
                    <div class="form-group">
                        <label for="goal_name">Goal Name</label>
                        <input type="text" id="goal_name" name="goal_name" placeholder="e.g., Emergency Fund" required>
                    </div>

                    <div class="form-group">
                        <label for="target_amount">Target Amount (₹)</label>
                        <input type="number" id="target_amount" name="target_amount" step="0.01" min="0" placeholder="50000" required>
                    </div>

                    <div class="form-group">
                        <label for="current_amount">Current Amount (₹)</label>
                        <input type="number" id="current_amount" name="current_amount" step="0.01" min="0" placeholder="0" value="0">
                    </div>

                    <div class="form-group">
                        <label for="deadline">Target Deadline</label>
                        <input type="date" id="deadline" name="deadline" required>
                    </div>

                    <button type="submit" class="btn btn-primary">Create Goal</button>
                </form>
            </div>

            <!-- Goals Summary -->
            <div class="card">
                <h2>Progress Summary</h2>
                <div class="summary-grid">
                    <div class="summary-stat">
                        <div class="summary-stat-value"><?php echo count($goals); ?></div>
                        <div class="summary-stat-label">Total Goals</div>
                    </div>
                    <div class="summary-stat">
                        <div class="summary-stat-value"><?php echo $goals_achieved; ?></div>
                        <div class="summary-stat-label">Achieved</div>
                    </div>
                    <div class="summary-stat">
                        <div class="summary-stat-value"><?php echo round(($total_saved / max($total_target, 1)) * 100, 1); ?>%</div>
                        <div class="summary-stat-label">Overall Progress</div>
                    </div>
                </div>
                <div style="background: var(--off-white); padding: 15px; border-radius: 10px; text-align: center;">
                    <p style="color: var(--text-light); font-size: 14px; margin-bottom: 5px;">Total Saved</p>
                    <p style="font-family: 'Playfair Display', serif; font-size: 32px; font-weight: 700; color: var(--primary);">₹<?php echo number_format($total_saved, 2); ?></p>
                    <p style="color: var(--text-light); font-size: 13px; margin-top: 5px;">of ₹<?php echo number_format($total_target, 2); ?></p>
                </div>
            </div>
        </div>

        <!-- Goals List -->
        <?php if (count($goals) > 0): ?>
            <div class="card">
                <h2>Your Goals</h2>
                <div class="goals-list">
                    <?php foreach ($goals as $goal): ?>
                        <div class="goal-item <?php echo strtolower($goal['status']); ?>">
                            <div class="goal-header">
                                <div class="goal-title">
                                    <h3><?php echo htmlspecialchars($goal['goal_name']); ?></h3>
                                </div>
                                <span class="goal-status <?php echo strtolower(str_replace(' ', '-', $goal['status'])); ?>">
                                    <?php echo $goal['status']; ?>
                                </span>
                            </div>

                            <div style="margin-bottom: 15px;">
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: <?php echo min($goal['percentage'], 100); ?>%;"></div>
                                </div>
                            </div>

                            <div class="goal-details">
                                <div class="detail-item">
                                    Saved: <span class="detail-value">₹<?php echo number_format($goal['current_amount'], 2); ?></span>
                                </div>
                                <div class="detail-item">
                                    Target: <span class="detail-value">₹<?php echo number_format($goal['target_amount'], 2); ?></span>
                                </div>
                                <div class="detail-item">
                                    Remaining: <span class="detail-value">₹<?php echo number_format($goal['remaining'], 2); ?></span>
                                </div>
                            </div>

                            <div class="goal-details" style="margin-bottom: 20px;">
                                <div class="detail-item">
                                    Progress: <span class="detail-value"><?php echo round($goal['percentage'], 1); ?>%</span>
                                </div>
                                <div class="detail-item">
                                    Days Left: <span class="detail-value"><?php echo ceil($goal['days_left']); ?></span>
                                </div>
                                <div class="detail-item">
                                    Deadline: <span class="detail-value"><?php echo date('M d, Y', strtotime($goal['deadline'])); ?></span>
                                </div>
                            </div>

                            <div class="goal-actions">
                                <button class="btn-deposit" onclick="openDepositModal(<?php echo $goal['id']; ?>)">💰 Add Deposit</button>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this goal?');">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="delete_id" value="<?php echo $goal['id']; ?>">
                                    <button type="submit" class="btn-delete">Delete</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="card">
                <div class="empty-state">
                    <p>🎯 No goals yet. Create your first savings goal to get started!</p>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Deposit Modal -->
    <div id="depositModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Add Deposit</h2>
                <button class="close-btn" onclick="closeDepositModal()">×</button>
            </div>
            <form method="POST">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="add_deposit">
                <input type="hidden" id="modal_goal_id" name="goal_id" value="">

                <div class="form-group">
                    <label for="deposit_amount">Deposit Amount (₹)</label>
                    <input type="number" id="deposit_amount" name="deposit_amount" step="0.01" min="0" placeholder="1000" required>
                </div>

                <button type="submit" class="btn btn-primary">Add Deposit</button>
            </form>
        </div>
    </div>

    <script>
        function openDepositModal(goalId) {
            document.getElementById('modal_goal_id').value = goalId;
            document.getElementById('depositModal').classList.add('active');
            document.getElementById('deposit_amount').focus();
        }

        function closeDepositModal() {
            document.getElementById('depositModal').classList.remove('active');
            document.getElementById('deposit_amount').value = '';
        }

        // Close modal when clicking outside
        document.getElementById('depositModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeDepositModal();
            }
        });
    </script>
</body>
</html>
