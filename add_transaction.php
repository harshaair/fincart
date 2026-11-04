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

// Handle add transaction
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    enforce_csrf();
    $type = $_POST['type'] ?? '';
    $category = $_POST['category'];
    $amount = floatval($_POST['amount']);
    $description = $_POST['description'];
    $date = $_POST['date'];

    // Validation
    if (empty($type) || empty($category) || $amount <= 0 || empty($date)) {
        $error = 'Please fill all required fields with valid values';
    } else {
        $sql = "INSERT INTO transactions (user_id, type, category, amount, description, date) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("issdss", $user_id, $type, $category, $amount, $description, $date);
        
        if ($stmt->execute()) {
            $message = 'Transaction added successfully!';
            // Clear form
            $_POST = array();
        } else {
            $error = 'Failed to add transaction. Please try again.';
        }
    }
}

$income_categories = ['Salary', 'Freelance', 'Investment Returns', 'Bonus', 'Gifts', 'Other Income'];
$expense_categories = ['Food', 'Transportation', 'Entertainment', 'Utilities', 'Shopping', 'Healthcare', 'Education', 'Other Expense'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Transaction - FinCart</title>
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

        .back-link {
            color: var(--accent);
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .back-link:hover {
            color: var(--primary);
        }

        .container {
            max-width: 600px;
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

        .form-card {
            background: var(--white);
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
        }

        .form-group {
            margin-bottom: 24px;
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

        input[type="text"]:focus,
        input[type="number"]:focus,
        input[type="date"]:focus,
        select:focus,
        textarea:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.1);
        }

        textarea {
            resize: vertical;
            min-height: 80px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        .type-selector {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-bottom: 24px;
        }

        .type-btn {
            padding: 16px;
            border: 2px solid rgba(0, 0, 0, 0.1);
            background: var(--white);
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            font-size: 14px;
            color: var(--text-dark);
        }

        .type-btn:hover {
            border-color: var(--accent);
        }

        .type-btn.active {
            background: var(--primary);
            color: var(--white);
            border-color: var(--primary);
        }

        .type-btn.income.active {
            background: var(--success);
            border-color: var(--success);
        }

        .type-btn.expense.active {
            background: var(--danger);
            border-color: var(--danger);
        }

        .button-group {
            display: flex;
            gap: 12px;
            margin-top: 30px;
        }

        .btn {
            flex: 1;
            padding: 14px 32px;
            border: none;
            border-radius: 50px;
            cursor: pointer;
            font-weight: 600;
            font-size: 15px;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }

        .btn-primary {
            background: var(--primary);
            color: var(--white);
        }

        .btn-primary:hover {
            background: var(--accent);
            color: var(--primary);
            transform: translateY(-2px);
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
            transform: translateY(-2px);
        }

        .hidden {
            display: none;
        }

        @media (max-width: 768px) {
            .form-row,
            .type-selector {
                grid-template-columns: 1fr;
            }

            .button-group {
                flex-direction: column;
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
            <a href="transactions.php" class="back-link">←← Back to Transactions</a>
        </div>
    </header>

    <!-- Main Content -->
    <div class="container">
        <div class="page-header">
            <h1>Add Transaction</h1>
            <p>Record a new income or expense</p>
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

        <div class="form-card">
            <form method="POST">
                <?php echo csrf_field(); ?>
                <!-- Type Selector -->
                <label style="margin-bottom: 12px;">Transaction Type</label>
                <div class="type-selector">
                    <button type="button" class="type-btn income active" onclick="selectType('income')">
                        📥 Income
                    </button>
                    <button type="button" class="type-btn expense" onclick="selectType('expense')">
                        📤 Expense
                    </button>
                </div>
                <input type="hidden" id="type" name="type" value="income" required>

                <!-- Category -->
                <div class="form-group">
                    <label for="category">Category</label>
                    <select id="category" name="category" required onchange="updateCategories()">
                        <option value="">Select a category</option>
                        <option value="Salary">Salary</option>
                        <option value="Freelance">Freelance</option>
                        <option value="Investment Returns">Investment Returns</option>
                        <option value="Bonus">Bonus</option>
                        <option value="Gifts">Gifts</option>
                        <option value="Other Income">Other Income</option>
                    </select>
                </div>

                <!-- Amount -->
                <div class="form-group">
                    <label for="amount">Amount (₹)</label>
                    <input type="number" id="amount" name="amount" step="0.01" min="0" placeholder="0.00" required>
                </div>

                <!-- Date -->
                <div class="form-group">
                    <label for="date">Date</label>
                    <input type="date" id="date" name="date" value="<?php echo date('Y-m-d'); ?>" required>
                </div>

                <!-- Description -->
                <div class="form-group">
                    <label for="description">Description (Optional)</label>
                    <textarea id="description" name="description" placeholder="Add notes about this transaction..."></textarea>
                </div>

                <!-- Buttons -->
                <div class="button-group">
                    <button type="submit" class="btn btn-primary">Add Transaction</button>
                    <a href="transactions.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>

    <script>
        function selectType(type) {
            document.getElementById('type').value = type;
            
            // Update button styles
            document.querySelectorAll('.type-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            event.target.closest('.type-btn').classList.add('active');
            
            // Update category options
            updateCategoryOptions(type);
        }

        function updateCategoryOptions(type) {
            const categorySelect = document.getElementById('category');
            categorySelect.innerHTML = '<option value="">Select a category</option>';
            
            if (type === 'income') {
                const incomeCategories = ['Salary', 'Freelance', 'Investment Returns', 'Bonus', 'Gifts', 'Other Income'];
                incomeCategories.forEach(cat => {
                    const option = document.createElement('option');
                    option.value = cat;
                    option.textContent = cat;
                    categorySelect.appendChild(option);
                });
            } else {
                const expenseCategories = ['Food', 'Transportation', 'Entertainment', 'Utilities', 'Shopping', 'Healthcare', 'Education', 'Other Expense'];
                expenseCategories.forEach(cat => {
                    const option = document.createElement('option');
                    option.value = cat;
                    option.textContent = cat;
                    categorySelect.appendChild(option);
                });
            }
        }
    </script>
</body>
</html>
