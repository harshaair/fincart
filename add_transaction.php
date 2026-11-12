<?php
require_once "includes/security.php";
init_secure_session();
include "db.php";

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Handle add transaction
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    enforce_csrf();
    $type = $_POST['type'] ?? '';
    $category = $_POST['category'] ?? '';
    $amount = floatval($_POST['amount'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    $date = $_POST['date'] ?? '';

    // Validation
    if (empty($type) || empty($category) || $amount <= 0 || $amount > 99999999.99 || empty($date)) {
        $error = 'Please fill all required fields with valid values. Amount must be between ₹0.01 and ₹99,999,999.99.';
    } else {
        $sql = "INSERT INTO transactions (user_id, type, category, amount, description, date) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("issdss", $user_id, $type, $category, $amount, $description, $date);
        
        if ($stmt->execute()) {
            $new_id = $conn->insert_id;
            // Immediate redirect with flash message and row highlight to verify database persistence without manual refresh
            header("Location: transactions.php?msg=" . urlencode("Transaction Recorded Successfully!") . "&highlight=" . $new_id);
            exit();
        } else {
            $error = 'Failed to add transaction. Please try again.';
        }
    }
}

$income_categories = ['Salary', 'Freelance', 'Investment Returns', 'Bonus', 'Gifts', 'Other Income'];
$expense_categories = ['Food', 'Transportation', 'Entertainment', 'Utilities', 'Shopping', 'Healthcare', 'Education', 'Investment', 'Savings Transfer', 'Other Expense'];

$page_title = 'Record Transaction - FinCart';
include "includes/header.php";
include "includes/navbar.php";
?>

<div class="main-content">
    <div style="max-width: 640px; margin: 24px auto; padding: 0 16px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 12px;">
            <div>
                <h1 style="font-size: 26px; margin-bottom: 4px;">Record Transaction</h1>
                <p style="color: var(--text-muted); font-size: 14px;">Log a new inflow or expense to your live financial ledger.</p>
            </div>
            <a href="transactions.php" class="btn btn-secondary btn-sm">&larr; Transactions</a>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success">&#10003; <?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger">&#9888; <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="card" style="padding: 32px 28px;">
            <form method="POST" id="transactionForm" onsubmit="var btn=document.getElementById('saveTxBtn'); if(btn.disabled) return false; btn.disabled=true; btn.innerHTML='⏳ Recording Transaction...'; return true;">
                <?php echo csrf_field(); ?>
                
                <!-- Type Selector -->
                <div class="form-group" style="margin-bottom: 20px;">
                    <label style="display: block; font-weight: 600; font-size: 13.5px; margin-bottom: 8px;">Transaction Nature</label>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                        <button type="button" id="btn-expense" class="btn" style="padding: 12px; font-weight: 700; border: 2px solid var(--danger, #e74c3c); background: #fee2e2; color: #dc2626;" onclick="selectType('expense')">
                            📤 Outflow (Expense)
                        </button>
                        <button type="button" id="btn-income" class="btn" style="padding: 12px; font-weight: 600; border: 2px solid #ddd; background: #fafafa; color: #666;" onclick="selectType('income')">
                            📥 Inflow (Income)
                        </button>
                    </div>
                    <input type="hidden" id="type" name="type" value="expense" required>
                </div>

                <!-- Amount -->
                <div class="form-group" style="margin-bottom: 20px;">
                    <label for="amount" style="display: block; font-weight: 600; font-size: 13.5px; margin-bottom: 8px;">Amount (₹)</label>
                    <input type="number" id="amount" name="amount" class="form-control" step="0.01" min="0.01" max="99999999.99" placeholder="e.g. 2500" required autofocus style="font-size: 16px; font-weight: 600;">
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 20px;">
                    <!-- Category -->
                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="category" style="display: block; font-weight: 600; font-size: 13.5px; margin-bottom: 8px;">Category</label>
                        <select id="category" name="category" class="form-control" required style="font-size: 14px;">
                            <option value="">Select category</option>
                            <?php foreach ($expense_categories as $c): ?>
                                <option value="<?php echo htmlspecialchars($c); ?>"><?php echo htmlspecialchars($c); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Date -->
                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="date" style="display: block; font-weight: 600; font-size: 13.5px; margin-bottom: 8px;">Date</label>
                        <input type="date" id="date" name="date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required style="font-size: 14px;">
                    </div>
                </div>

                <!-- Description -->
                <div class="form-group" style="margin-bottom: 24px;">
                    <label for="description" style="display: block; font-weight: 600; font-size: 13.5px; margin-bottom: 8px;">Description / Merchant (Optional)</label>
                    <input type="text" id="description" name="description" class="form-control" placeholder="e.g. Grocery Store, Swiggy, Monthly Rent" style="font-size: 14px;">
                </div>

                <!-- Submit / Cancel -->
                <div style="display: flex; gap: 12px;">
                    <button type="submit" id="saveTxBtn" class="btn btn-primary" style="flex: 1; padding: 13px; font-size: 14.5px;">Save Transaction &rarr;</button>
                    <a href="transactions.php" class="btn btn-secondary" style="padding: 13px 20px;">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function() {
    const incomeCats = <?php echo json_encode($income_categories); ?>;
    const expenseCats = <?php echo json_encode($expense_categories); ?>;

    window.populateCategories = function(cats) {
        const sel = document.getElementById('category');
        if (!sel) return;
        sel.innerHTML = '<option value="">Select category</option>';
        cats.forEach(function(c) {
            const opt = document.createElement('option');
            opt.value = c;
            opt.textContent = c;
            sel.appendChild(opt);
        });
    };

    window.selectType = function(type) {
        const typeInput = document.getElementById('type');
        const btnExp = document.getElementById('btn-expense');
        const btnInc = document.getElementById('btn-income');
        if (!typeInput || !btnExp || !btnInc) return;

        typeInput.value = type;

        if (type === 'expense') {
            btnExp.style.border = '2px solid #e74c3c';
            btnExp.style.background = '#fee2e2';
            btnExp.style.color = '#dc2626';
            btnExp.style.fontWeight = '700';

            btnInc.style.border = '2px solid #ddd';
            btnInc.style.background = '#fafafa';
            btnInc.style.color = '#666';
            btnInc.style.fontWeight = '500';

            window.populateCategories(expenseCats);
        } else {
            btnInc.style.border = '2px solid #27ae60';
            btnInc.style.background = '#dcfce7';
            btnInc.style.color = '#15803d';
            btnInc.style.fontWeight = '700';

            btnExp.style.border = '2px solid #ddd';
            btnExp.style.background = '#fafafa';
            btnExp.style.color = '#666';
            btnExp.style.fontWeight = '500';

            window.populateCategories(incomeCats);
        }
    };
})();
</script>

<?php include "includes/footer.php"; ?>
