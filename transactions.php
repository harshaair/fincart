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

// Handle delete transaction
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $transaction_id = intval($_POST['transaction_id']);
    
    $check_sql = "SELECT id FROM transactions WHERE id = ? AND user_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("ii", $transaction_id, $user_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $delete_sql = "DELETE FROM transactions WHERE id = ? AND user_id = ?";
        $delete_stmt = $conn->prepare($delete_sql);
        $delete_stmt->bind_param("ii", $transaction_id, $user_id);
        if ($delete_stmt->execute()) {
            $message = "Transaction deleted successfully!";
        } else {
            $error = "Failed to delete transaction.";
        }
    }
}

// Handle edit transaction
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    $transaction_id = intval($_POST['transaction_id']);
    $type = $_POST['type'] ?? 'expense';
    $category = trim($_POST['category'] ?? '');
    $amount = floatval($_POST['amount'] ?? 0);
    $date = $_POST['date'] ?? date('Y-m-d');
    $description = trim($_POST['description'] ?? '');

    if ($amount <= 0 || empty($category) || empty($date)) {
        $error = "Please provide valid transaction values.";
    } else {
        $update_sql = "UPDATE transactions SET type = ?, category = ?, amount = ?, date = ?, description = ? WHERE id = ? AND user_id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("ssdssii", $type, $category, $amount, $date, $description, $transaction_id, $user_id);
        if ($update_stmt->execute()) {
            $message = "Transaction updated successfully!";
        } else {
            $error = "Failed to update transaction.";
        }
    }
}

// Get filter values
$filter_type = isset($_GET['type']) ? $_GET['type'] : 'all';
$filter_category = isset($_GET['category']) ? $_GET['category'] : 'all';
$filter_month = isset($_GET['month']) ? $_GET['month'] : '';

// Pagination variables
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 15;
$offset = ($page - 1) * $limit;

// Build query
$where_clause = "WHERE user_id = ?";
$params = [$user_id];
$param_types = "i";

if ($filter_type !== 'all' && in_array($filter_type, ['income', 'expense'])) {
    $where_clause .= " AND type = ?";
    $params[] = $filter_type;
    $param_types .= "s";
}

if ($filter_category !== 'all' && !empty($filter_category)) {
    $where_clause .= " AND category = ?";
    $params[] = $filter_category;
    $param_types .= "s";
}

if ($filter_month !== '') {
    $where_clause .= " AND DATE_FORMAT(date, '%Y-%m') = ?";
    $params[] = $filter_month;
    $param_types .= "s";
}

// Get total count for pagination
$count_sql = "SELECT COUNT(*) as total FROM transactions $where_clause";
$count_stmt = $conn->prepare($count_sql);
$count_stmt->bind_param($param_types, ...$params);
$count_stmt->execute();
$total_rows = (int)$count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_rows / $limit);

// Get paginated transactions
$sql = "SELECT * FROM transactions $where_clause ORDER BY date DESC, created_at DESC LIMIT ? OFFSET ?";
$p_params = array_merge($params, [$limit, $offset]);
$p_types = $param_types . "ii";

$stmt = $conn->prepare($sql);
$stmt->bind_param($p_types, ...$p_params);
$stmt->execute();
$result = $stmt->get_result();
$transactions = $result->fetch_all(MYSQLI_ASSOC);

// Summary across current filter or all time
$sum_sql = "SELECT 
    COALESCE(SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END), 0) as total_income,
    COALESCE(SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END), 0) as total_expense
    FROM transactions $where_clause";
$sum_stmt = $conn->prepare($sum_sql);
$sum_stmt->bind_param($param_types, ...$params);
$sum_stmt->execute();
$summary = $sum_stmt->get_result()->fetch_assoc();

// Distinct categories for filter
$categories_sql = "SELECT DISTINCT category FROM transactions WHERE user_id = ? AND category IS NOT NULL ORDER BY category";
$categories_stmt = $conn->prepare($categories_sql);
$categories_stmt->bind_param("i", $user_id);
$categories_stmt->execute();
$categories = $categories_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

require_once "includes/components/stat_card.php";
require_once "includes/components/page_header.php";
require_once "includes/components/alert.php";

$page_title = 'Transaction Ledger - FinCart';
include "includes/header.php";
include "includes/navbar.php";
?>

<main class="page-container" style="max-width: 1240px; margin: 0 auto; padding: 20px 24px;">
    <?php
    render_page_header([
        'title'    => 'Financial Ledger',
        'subtitle' => 'Track, filter, audit, and analyze all income and expenditure records',
        'badge'    => number_format($total_rows) . ' Entries',
        'actions'  => [
            ['label' => '+ Add Transaction', 'href' => 'add_transaction.php', 'class' => 'btn-gold'],
            ['label' => 'Analytics & Trends →', 'href' => 'analytics.php', 'class' => 'btn-outline']
        ]
    ]);

    if (!empty($message)) {
        render_alert($message, 'success');
    }
    if (!empty($error)) {
        render_alert($error, 'danger');
    }
    ?>

    <!-- Summary Stats (Standardized Reusable Components) -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 18px; margin-bottom: 24px;">
        <?php
        $net_balance = (float)($summary['total_income'] - $summary['total_expense']);
        render_stat_card([
            'title'      => 'Period Inflow',
            'value'      => '₹' . number_format((float)$summary['total_income'], 2),
            'icon'       => '↗️',
            'trend'      => 'Credits',
            'trend_type' => 'positive',
            'subtitle'   => 'Selected timeframe'
        ]);

        render_stat_card([
            'title'      => 'Period Outflow',
            'value'      => '₹' . number_format((float)$summary['total_expense'], 2),
            'icon'       => '↘️',
            'trend'      => 'Debits',
            'trend_type' => 'negative',
            'subtitle'   => 'Selected timeframe'
        ]);

        render_stat_card([
            'title'      => 'Net Variance',
            'value'      => '₹' . number_format($net_balance, 2),
            'icon'       => '⚖️',
            'trend'      => $net_balance >= 0 ? '+ Surplus' : '- Deficit',
            'trend_type' => $net_balance >= 0 ? 'positive' : 'negative',
            'subtitle'   => 'Inflow minus outflow'
        ]);

        render_stat_card([
            'title'      => 'Audited Records',
            'value'      => number_format($total_rows),
            'icon'       => '📑',
            'trend'      => 'Page ' . $page . ' of ' . max(1, $total_pages),
            'trend_type' => 'neutral',
            'subtitle'   => 'Database items'
        ]);
        ?>
    </div>

    <!-- Filters Bar -->
    <div class="card-luxury" style="margin-bottom: 24px;">
        <div class="card-luxury-body" style="padding: 16px 20px;">
            <form method="GET" style="display: flex; gap: 15px; flex-wrap: wrap; align-items: flex-end;">
                <div style="flex: 1; min-width: 140px;">
                    <label style="font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em; color: var(--text-muted); display: block; margin-bottom: 6px;">Type</label>
                    <select name="type" class="form-control" style="width: 100%; padding: 8px 12px; border-radius: var(--radius-sm); border: 1px solid var(--border-color);">
                        <option value="all" <?php echo $filter_type === 'all' ? 'selected' : ''; ?>>All Transactions</option>
                        <option value="income" <?php echo $filter_type === 'income' ? 'selected' : ''; ?>>Income Only</option>
                        <option value="expense" <?php echo $filter_type === 'expense' ? 'selected' : ''; ?>>Expense Only</option>
                    </select>
                </div>
                <div style="flex: 1; min-width: 160px;">
                    <label style="font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em; color: var(--text-muted); display: block; margin-bottom: 6px;">Category</label>
                    <select name="category" class="form-control" style="width: 100%; padding: 8px 12px; border-radius: var(--radius-sm); border: 1px solid var(--border-color);">
                        <option value="all">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo htmlspecialchars($cat['category']); ?>" <?php echo $filter_category === $cat['category'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['category']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="flex: 1; min-width: 160px;">
                    <label style="font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em; color: var(--text-muted); display: block; margin-bottom: 6px;">Month</label>
                    <input type="month" name="month" class="form-control" style="width: 100%; padding: 8px 12px; border-radius: var(--radius-sm); border: 1px solid var(--border-color);" value="<?php echo htmlspecialchars($filter_month); ?>">
                </div>
                <div style="display: flex; gap: 8px;">
                    <button type="submit" class="btn-luxury btn-gold" style="padding: 8px 18px;">Filter</button>
                    <a href="transactions.php" class="btn-luxury btn-outline" style="padding: 8px 16px;">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Transactions Table -->
    <div class="card-luxury">
        <div class="table-luxury-wrap">
            <table class="table-luxury">
                <thead>
                    <tr>
                        <th style="width: 50px;">#</th>
                        <th>Type</th>
                        <th>Category</th>
                        <th>Description</th>
                        <th>Date</th>
                        <th>Amount</th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($transactions)): ?>
                        <?php foreach ($transactions as $t): ?>
                            <tr>
                                <td style="color: var(--text-muted);"><?php echo (int)$t['id']; ?></td>
                                <td>
                                    <?php if ($t['type'] === 'income'): ?>
                                        <span class="badge-luxury badge-luxury-success" style="background: var(--success-bg); color: var(--success);">↑ Income</span>
                                    <?php else: ?>
                                        <span class="badge-luxury badge-luxury-danger" style="background: var(--danger-bg); color: var(--danger);">↓ Expense</span>
                                    <?php endif; ?>
                                </td>
                                <td><strong><?php echo htmlspecialchars($t['category'] ?? 'General'); ?></strong></td>
                                <td style="color: var(--text-muted);"><?php echo htmlspecialchars($t['description'] ?? '—'); ?></td>
                                <td><?php echo date('d-M-Y', strtotime($t['date'])); ?></td>
                                <td>
                                    <strong style="color: <?php echo $t['type'] === 'income' ? 'var(--success)' : 'var(--danger)'; ?>; font-size: 14.5px;">
                                        <?php echo $t['type'] === 'income' ? '+' : '-'; ?>₹<?php echo number_format((float)$t['amount'], 2); ?>
                                    </strong>
                                </td>
                                <td style="text-align: right;">
                                    <button 
                                        type="button" 
                                        class="btn-luxury btn-outline"
                                        style="padding: 5px 12px; font-size: 12px;"
                                        onclick="openEditModal(<?php echo htmlspecialchars(json_encode($t)); ?>)">
                                        Edit
                                    </button>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this transaction?');">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="transaction_id" value="<?php echo (int)$t['id']; ?>">
                                        <button type="submit" class="btn-luxury" style="padding: 5px 12px; font-size: 12px; background: #fee2e2; color: #dc2626;">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 48px; color: var(--text-muted);">
                                No transactions found matching your criteria. Click <strong>+ Add Transaction</strong> above to record your first entry.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination Controls -->
        <?php if ($total_pages > 1): ?>
            <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 25px; padding-top: 15px; border-top: 1px solid var(--border-light); flex-wrap: wrap; gap: 10px;">
                <span style="font-size: 13px; color: var(--text-muted);">
                    Showing <?php echo $offset + 1; ?> &ndash; <?php echo min($offset + $limit, $total_rows); ?> of <?php echo $total_rows; ?> transactions
                </span>
                <div style="display: flex; gap: 6px;">
                    <?php
                    $qs = $_GET;
                    if ($page > 1):
                        $qs['page'] = $page - 1;
                        echo '<a href="?' . http_build_query($qs) . '" class="btn btn-secondary btn-sm">&larr; Prev</a>';
                    endif;

                    for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++):
                        $qs['page'] = $i;
                        $active_class = ($i === $page) ? 'btn-primary' : 'btn-secondary';
                        echo '<a href="?' . http_build_query($qs) . '" class="btn ' . $active_class . ' btn-sm">' . $i . '</a>';
                    endfor;

                    if ($page < $total_pages):
                        $qs['page'] = $page + 1;
                        echo '<a href="?' . http_build_query($qs) . '" class="btn btn-secondary btn-sm">Next &rarr;</a>';
                    endif;
                    ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</main>

<!-- Edit Transaction Modal -->
<div id="editModal" class="modal-overlay">
    <div class="modal-content">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h2 style="font-size: 20px;">Edit Transaction</h2>
            <button type="button" onclick="closeEditModal()" style="background: none; border: none; font-size: 22px; cursor: pointer;">&times;</button>
        </div>
        <form method="POST">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="transaction_id" id="edit_id">

            <div class="form-group">
                <label>Transaction Type</label>
                <select name="type" id="edit_type" class="form-control" required>
                    <option value="expense">Expense</option>
                    <option value="income">Income</option>
                </select>
            </div>

            <div class="form-group">
                <label>Category</label>
                <input type="text" name="category" id="edit_category" class="form-control" required>
            </div>

            <div class="form-group">
                <label>Amount (₹)</label>
                <input type="number" step="0.01" min="0.01" name="amount" id="edit_amount" class="form-control" required>
            </div>

            <div class="form-group">
                <label>Date</label>
                <input type="date" name="date" id="edit_date" class="form-control" required>
            </div>

            <div class="form-group">
                <label>Description (Optional)</label>
                <textarea name="description" id="edit_description" class="form-control" rows="2"></textarea>
            </div>

            <div style="display: flex; gap: 10px; margin-top: 25px;">
                <button type="submit" class="btn btn-primary" style="flex: 1;">Save Changes</button>
                <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Cancel</button>
            </div>
        </form>
    </div>
</main>

<script>
function openEditModal(tx) {
    document.getElementById('edit_id').value = tx.id;
    document.getElementById('edit_type').value = tx.type;
    document.getElementById('edit_category').value = tx.category;
    document.getElementById('edit_amount').value = tx.amount;
    document.getElementById('edit_date').value = tx.date;
    document.getElementById('edit_description').value = tx.description || '';
    document.getElementById('editModal').style.display = 'flex';
}

function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
}

// Close on backdrop click
window.addEventListener('click', function(e) {
    const modal = document.getElementById('editModal');
    if (e.target === modal) {
        closeEditModal();
    }
});
</script>

<?php include "includes/footer.php"; ?>
