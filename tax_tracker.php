<?php
require_once "includes/security.php";
init_secure_session();
include "db.php";
require_once "includes/tax_helper.php";

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    enforce_csrf();
}

// Handle add deduction
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $category = trim($_POST['category'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $amount = floatval($_POST['amount'] ?? 0);
    $deduction_date = $_POST['deduction_date'] ?? date('Y-m-d');

    if (empty($category) || $amount <= 0) {
        $error = 'Please fill all required fields with valid positive values.';
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

// Resolve Statutory Indian Financial Year (April 1 to March 31)
$available_fys = getAvailableFinancialYears(5);
$selected_fy_code = trim($_GET['fy'] ?? '');
$current_fy = getIndianFinancialYear();

$is_all_years = ($selected_fy_code === 'all');
$active_fy = null;

if (!$is_all_years) {
    foreach ($available_fys as $fy_item) {
        if ($fy_item['fy_code'] === $selected_fy_code) {
            $active_fy = $fy_item;
            break;
        }
    }
    if (!$active_fy) {
        $active_fy = $current_fy;
    }
}

// Fetch deductions for the user (scoped to FY or all)
if ($is_all_years) {
    $deductions_sql = "SELECT * FROM tax_deductions WHERE user_id = ? ORDER BY deduction_date DESC";
    $deductions_stmt = $conn->prepare($deductions_sql);
    $deductions_stmt->bind_param("i", $user_id);
} else {
    $deductions_sql = "SELECT * FROM tax_deductions WHERE user_id = ? AND deduction_date BETWEEN ? AND ? ORDER BY deduction_date DESC";
    $deductions_stmt = $conn->prepare($deductions_sql);
    $deductions_stmt->bind_param("iss", $user_id, $active_fy['start_date'], $active_fy['end_date']);
}

$deductions_stmt->execute();
$deductions_result = $deductions_stmt->get_result();
$deductions = [];

while ($row = $deductions_result->fetch_assoc()) {
    $deductions[] = $row;
}

// Apply statutory caps
$caps_data = applyStatutoryCaps($deductions);

// Category grouping for breakdown chart
$category_totals = [];
foreach ($deductions as $ded) {
    $c = $ded['category'];
    if (!isset($category_totals[$c])) {
        $category_totals[$c] = 0;
    }
    $category_totals[$c] += (float)$ded['amount'];
}

$deduction_categories = [
    'Section 80C - Insurance & Investment',
    'Section 80CCD(1B) - National Pension System',
    'Section 80D - Health Insurance',
    'Section 80E - Education Loan Interest',
    'Section 80G - Charitable Donations',
    'Section 80TTA - Savings Bank Interest',
    'Medical Expenses',
    'Home Loan Interest (Section 24)',
    'Other Deductions'
];

$page_title = 'Tax Deductions Tracker - FinCart';
$extra_css = '<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>';

include "includes/header.php";
include "includes/navbar.php";
?>

<div class="main-content">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 15px;">
        <div>
            <h1 style="font-size: 28px; margin-bottom: 6px;">Tax Deductions Tracker</h1>
            <p style="color: var(--text-muted);">Track your tax-saving investments and enforce statutory limits under the Indian Income Tax Act.</p>
        </div>
        <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
            <!-- FY Selector -->
            <form method="GET" action="tax_tracker.php" style="margin: 0; display: inline-flex; align-items: center; gap: 8px;">
                <label for="fy" style="font-size: 13px; font-weight: 600; color: var(--text-dark); margin: 0;">FY Scoping:</label>
                <select name="fy" id="fy" onchange="this.form.submit()" style="padding: 7px 12px; border-radius: 6px; border: 1px solid var(--border-light, #ddd); font-size: 13px; background: #fff; font-weight: 600; color: var(--primary);">
                    <?php foreach ($available_fys as $f): ?>
                        <option value="<?php echo htmlspecialchars($f['fy_code']); ?>" <?php echo (!$is_all_years && $f['fy_code'] === $active_fy['fy_code']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($f['fy_label'] . ' (' . $f['ay_label'] . ')'); ?><?php echo $f['is_current'] ? ' • Current' : ''; ?>
                        </option>
                    <?php endforeach; ?>
                    <option value="all" <?php echo $is_all_years ? 'selected' : ''; ?>>All Historical Records</option>
                </select>
            </form>
            <a href="tax_planning.php?fy=<?php echo urlencode($active_fy['fy_code'] ?? ''); ?>" class="btn btn-secondary">&larr; Tax Planning</a>
            <a href="tax_summary.php?fy=<?php echo urlencode($active_fy['fy_code'] ?? ''); ?>" class="btn btn-primary">Summary Report &rarr;</a>
        </div>
    </div>

    <!-- Active Scoping Banner -->
    <div style="background: linear-gradient(135deg, rgba(212, 175, 55, 0.08), rgba(26, 26, 26, 0.03)); border-left: 4px solid #d4af37; padding: 12px 18px; border-radius: 6px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
        <div>
            <strong style="color: var(--primary); font-size: 14px;">
                <?php if ($is_all_years): ?>
                    Displaying All Recorded Tax Deductions (All Historical Years)
                <?php else: ?>
                    Scoped to Statutory Financial Year: <?php echo htmlspecialchars($active_fy['fy_label']); ?> (<?php echo htmlspecialchars($active_fy['ay_label']); ?>)
                <?php endif; ?>
            </strong>
            <div style="font-size: 12px; color: var(--text-muted); margin-top: 2px;">
                <?php if (!$is_all_years): ?>
                    Statutory Window: <?php echo date('d M Y', strtotime($active_fy['start_date'])); ?> to <?php echo date('d M Y', strtotime($active_fy['end_date'])); ?> (Section 80C &amp; 80D caps computed for this FY)
                <?php else: ?>
                    Select a specific Indian FY above to apply statutory Section 80C/80D caps for that fiscal cycle.
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if (!empty($message)): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <!-- Statutory Summary Grid -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-label">Total Claimed</div>
            <div class="stat-value">₹<?php echo number_format($caps_data['total_claimed'], 2); ?></div>
            <div class="stat-description">Sum of all tracked receipts</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Statutory Eligible</div>
            <div class="stat-value" style="color: var(--success);">₹<?php echo number_format($caps_data['total_eligible'], 2); ?></div>
            <div class="stat-description">Allowable deduction in Old Regime</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Section 80C Claimed</div>
            <div class="stat-value">₹<?php echo number_format($caps_data['claimed_80c'], 2); ?></div>
            <div class="stat-description">
                Cap: ₹1,50,000 | 
                <?php if ($caps_data['claimed_80c'] >= LIMIT_SECTION_80C): ?>
                    <span style="color: var(--success); font-weight:600;">Max Reached</span>
                <?php else: ?>
                    ₹<?php echo number_format(LIMIT_SECTION_80C - $caps_data['claimed_80c'], 2); ?> room left
                <?php endif; ?>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Excess / Disallowed</div>
            <div class="stat-value" style="color: <?php echo $caps_data['disallowed_amount'] > 0 ? '#e74c3c' : 'inherit'; ?>;">
                ₹<?php echo number_format($caps_data['disallowed_amount'], 2); ?>
            </div>
            <div class="stat-description">Amounts exceeding section limits</div>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(360px, 1fr)); gap: 24px; margin-bottom: 30px;">
        <!-- Add Deduction Card -->
        <div class="card">
            <h2 style="font-size: 20px; margin-bottom: 20px;">+ Add Tax Deduction Record</h2>
            <form method="POST">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="add">
                
                <div class="form-group">
                    <label>Deduction Category</label>
                    <select name="category" class="form-control" required>
                        <option value="">Select Category</option>
                        <?php foreach ($deduction_categories as $cat): ?>
                            <option value="<?php echo htmlspecialchars($cat); ?>"><?php echo htmlspecialchars($cat); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Description / Investment Details</label>
                    <input type="text" name="description" class="form-control" placeholder="e.g. PPF Deposit, HDFC Life Premium, ELSS" required>
                </div>

                <div class="form-group">
                    <label>Amount (₹)</label>
                    <input type="number" step="0.01" min="1" name="amount" class="form-control" placeholder="e.g. 50000" required>
                </div>

                <div class="form-group">
                    <label>Date of Expense / Deposit</label>
                    <input type="date" name="deduction_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%;">Save Deduction</button>
            </form>
        </div>

        <!-- Breakdown Chart Card -->
        <div class="card">
            <h2 style="font-size: 20px; margin-bottom: 20px;">Deductions by Category</h2>
            <?php if (!empty($category_totals)): ?>
                <div style="height: 280px; position: relative;">
                    <canvas id="deductionsChart"></canvas>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 60px 20px; color: #888;">
                    No deductions recorded yet. Add your 80C, 80D, or health insurance investments to view breakdown.
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Deductions List Card -->
    <div class="card">
        <h2 style="font-size: 20px; margin-bottom: 20px;">Your Claimed Deductions (<?php echo count($deductions); ?>)</h2>
        <?php if (!empty($deductions)): ?>
            <div class="table-responsive">
                <table class="custom-table">
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th>Description</th>
                            <th>Date</th>
                            <th>Amount</th>
                            <th style="text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($deductions as $d): ?>
                            <tr>
                                <td><span class="badge badge-info"><?php echo htmlspecialchars($d['category']); ?></span></td>
                                <td><?php echo htmlspecialchars($d['description']); ?></td>
                                <td><?php echo date('d-M-Y', strtotime($d['deduction_date'])); ?></td>
                                <td><strong>₹<?php echo number_format((float)$d['amount'], 2); ?></strong></td>
                                <td style="text-align: right;">
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this deduction record?');">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="delete_id" value="<?php echo (int)$d['id']; ?>">
                                        <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p style="color: #888; text-align: center; padding: 30px;">No deduction records found. Add your first record above.</p>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($category_totals)): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('deductionsChart').getContext('2d');
    const categories = <?php echo json_encode(array_keys($category_totals)); ?>;
    const amounts = <?php echo json_encode(array_values($category_totals)); ?>;

    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: categories,
            datasets: [{
                data: amounts,
                backgroundColor: [
                    '#d4af37', '#1a1a1a', '#27ae60', '#3498db',
                    '#e74c3c', '#9b59b6', '#f39c12', '#1abc9c', '#34495e'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { boxWidth: 12, font: { size: 11 } }
                }
            }
        }
    });
});
</script>
<?php endif; ?>

<?php include "includes/footer.php"; ?>
