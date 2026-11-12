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
$message = trim($_GET['msg'] ?? '');
$error = trim($_GET['err'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    enforce_csrf();
}

// Handle add investment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_investment') {
    $name = trim($_POST['investment_name'] ?? '');
    $amount = floatval($_POST['investment_amount'] ?? 0);
    $returns = floatval($_POST['current_value'] ?? 0);
    $type = trim($_POST['investment_type'] ?? 'Other');
    $deduct_from_balance = !empty($_POST['deduct_from_balance']);

    if (empty($name) || $amount <= 0 || $amount > 99999999.99) {
        $error = 'Please enter a valid investment name and amount between ₹0.01 and ₹99,999,999.99.';
    } else {
        // If current valuation is omitted or zero, default to invested amount (BUG-019)
        if ($returns <= 0 || $returns > 99999999.99) {
            $returns = $amount;
        }
        $conn->begin_transaction();
        try {
            $sql = "INSERT INTO investments (user_id, name, amount, returns, type, created_at) VALUES (?, ?, ?, ?, ?, NOW())";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("isdds", $user_id, $name, $amount, $returns, $type);
            $stmt->execute();

            if ($deduct_from_balance) {
                // Record cash outflow in ledger transactions
                $trans_sql = "INSERT INTO transactions (user_id, type, category, amount, date, description) VALUES (?, 'expense', 'Investment', ?, CURDATE(), ?)";
                $trans_stmt = $conn->prepare($trans_sql);
                $desc = "Investment in " . $name . " (" . $type . ")";
                $trans_stmt->bind_param("ids", $user_id, $amount, $desc);
                $trans_stmt->execute();
            }

            $conn->commit();
            $msg = 'Investment asset successfully added to portfolio!' . ($deduct_from_balance ? ' Recorded cash outflow of ₹' . number_format($amount, 2) . ' in expense ledger.' : '');
            header("Location: investments.php?msg=" . urlencode($msg));
            exit();
        } catch (Exception $e) {
            $conn->rollback();
            $error = 'Failed to add investment: ' . $e->getMessage();
        }
    }
}

// Handle update investment value
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_value') {
    $investment_id = intval($_POST['investment_id'] ?? 0);
    $new_returns = floatval($_POST['new_value'] ?? 0);

    if ($new_returns < 0 || $new_returns > 99999999.99) {
        $error = 'Valuation must be between ₹0 and ₹99,999,999.99.';
    } else {
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
                header("Location: investments.php?msg=" . urlencode('Current valuation updated successfully!'));
                exit();
            } else {
                $error = 'Failed to update valuation. Please try again.';
            }
        } else {
            $error = 'Investment record not found.';
        }
    }
}

// Handle delete investment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_investment') {
    $delete_id = intval($_POST['delete_id'] ?? 0);
    $sql = "DELETE FROM investments WHERE id = ? AND user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $delete_id, $user_id);
    if ($stmt->execute()) {
        header("Location: investments.php?msg=" . urlencode('Investment removed from portfolio.'));
        exit();
    } else {
        $error = 'Failed to delete investment.';
    }
}

// Fetch all investments for the user
$investments_sql = "SELECT * FROM investments WHERE user_id = ? ORDER BY created_at DESC";
$investments_stmt = $conn->prepare($investments_sql);
$investments_stmt->bind_param("i", $user_id);
$investments_stmt->execute();
$investments_result = $investments_stmt->get_result();
$investments = [];

$portfolio_by_type = [];

while ($row = $investments_result->fetch_assoc()) {
    $row['profit_loss'] = $row['returns'] - $row['amount'];
    $row['return_percentage'] = $row['amount'] > 0 ? (($row['returns'] - $row['amount']) / $row['amount']) * 100 : 0;
    $investments[] = $row;

    $t = $row['type'] ?: 'Equities';
    if (!isset($portfolio_by_type[$t])) {
        $portfolio_by_type[$t] = 0;
    }
    $portfolio_by_type[$t] += $row['returns'];
}

// Calculate portfolio summary
$total_invested = array_sum(array_column($investments, 'amount'));
$total_returns = array_sum(array_column($investments, 'returns'));
$total_profit_loss = $total_returns - $total_invested;
$total_return = $total_invested > 0 ? ($total_profit_loss / $total_invested) * 100 : 0;

require_once "includes/components/stat_card.php";
require_once "includes/components/page_header.php";
require_once "includes/components/alert.php";

$page_title = 'Asset Portfolio & Investments - FinCart';

include "includes/header.php";
include "includes/navbar.php";
?>

<main class="page-container" style="max-width: 1240px; margin: 0 auto; padding: 20px 24px;">
    <?php
    render_page_header([
        'title'    => 'Asset & Investment Portfolio',
        'subtitle' => 'Track your equities, mutual funds, precious metals, and real-time capital gains',
        'badge'    => count($investments) . ' Holdings',
        'actions'  => [
            ['label' => '+ Log New Holding', 'href' => '#add-asset-card', 'class' => 'btn-gold'],
            ['label' => 'Tax Impact & LTCG →', 'href' => 'tax_planning.php', 'class' => 'btn-outline']
        ]
    ]);

    if (!empty($message)) {
        render_alert($message, 'success');
    }
    if (!empty($error)) {
        render_alert($error, 'danger');
    }
    ?>

    <!-- Portfolio KPI Metric Cards -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 18px; margin-bottom: 28px;">
        <?php
        render_stat_card([
            'title'      => 'Principal Invested',
            'value'      => '₹' . number_format($total_invested, 2),
            'icon'       => '💼',
            'trend'      => count($investments) . ' Assets',
            'trend_type' => 'neutral',
            'subtitle'   => 'Total capital deployed'
        ]);

        render_stat_card([
            'title'      => 'Current Valuation',
            'value'      => '₹' . number_format($total_returns, 2),
            'icon'       => '💎',
            'trend'      => 'Market Mark',
            'trend_type' => 'positive',
            'subtitle'   => 'Live portfolio net worth'
        ]);

        render_stat_card([
            'title'      => 'Net Gain / Loss',
            'value'      => ($total_profit_loss >= 0 ? '+' : '') . '₹' . number_format($total_profit_loss, 2),
            'icon'       => $total_profit_loss >= 0 ? '📈' : '📉',
            'trend'      => ($total_profit_loss >= 0 ? '+' : '') . number_format($total_return, 1) . '% ROI',
            'trend_type' => $total_profit_loss >= 0 ? 'positive' : 'negative',
            'subtitle'   => 'Unrealized capital variance'
        ]);

        render_stat_card([
            'title'      => 'Active Asset Classes',
            'value'      => (string)max(1, count($portfolio_by_type)),
            'icon'       => '📊',
            'trend'      => 'Diversification',
            'trend_type' => count($portfolio_by_type) >= 3 ? 'positive' : 'neutral',
            'subtitle'   => 'Risk distribution'
        ]);
        ?>
    </div>

    <!-- Main Content Layout (Form + Distribution) -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 24px; margin-bottom: 28px;">
        <!-- Add Investment Card -->
        <div class="card-luxury" id="add-asset-card">
            <div class="card-luxury-header">
                <h2 class="card-luxury-title">+ Add Investment Asset</h2>
                <span class="badge-luxury badge-luxury-gold">Asset Intake</span>
            </div>
            <div class="card-luxury-body">
                <form method="POST">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="add_investment">

                    <div class="form-group-luxury">
                        <label for="investment_name">Asset / Ticker Name</label>
                        <input type="text" id="investment_name" name="investment_name" class="input-luxury input-luxury-no-icon" placeholder="e.g. Reliance Industries, Nifty 50 Index Fund" required>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                        <div class="form-group-luxury">
                            <label for="investment_type">Asset Class</label>
                            <select id="investment_type" name="investment_type" class="input-luxury input-luxury-no-icon" required>
                                <option value="Equities">Direct Equities</option>
                                <option value="Mutual Funds">Mutual Funds (SIP/Lump)</option>
                                <option value="Gold & Commodities">Gold / Sovereign Gold Bonds</option>
                                <option value="Fixed Deposit">Fixed Deposit / Debt</option>
                                <option value="Real Estate">Real Estate / REITs</option>
                                <option value="Other">Other Alternative Assets</option>
                            </select>
                        </div>

                        <div class="form-group-luxury">
                            <label for="investment_amount">Principal (₹)</label>
                            <input type="number" id="investment_amount" name="investment_amount" class="input-luxury input-luxury-no-icon" step="0.01" min="1" placeholder="50000" required>
                        </div>
                    </div>

                    <div class="form-group-luxury">
                        <label for="current_value">Current Valuation (₹)</label>
                        <input type="number" id="current_value" name="current_value" class="input-luxury input-luxury-no-icon" step="0.01" min="0" placeholder="54500" required>
                    </div>

                    <div class="form-group-luxury" style="margin-bottom: 20px;">
                        <label style="display: flex; align-items: flex-start; gap: 10px; cursor: pointer; font-size: 13.5px; font-weight: 500; color: var(--text-dark);">
                            <input type="checkbox" name="deduct_from_balance" value="1" checked style="width: 17px; height: 17px; margin-top: 2px; accent-color: var(--gold, #d4af37);">
                            <span>Deduct principal from cash balance (record expense transaction)</span>
                        </label>
                        <div style="font-size: 11.5px; color: var(--text-muted); margin-left: 27px; margin-top: 3px;">
                            Uncheck if you are logging an existing historical asset without spending current cash.
                        </div>
                    </div>

                    <button type="submit" class="btn-luxury btn-gold" style="width: 100%; padding: 13px; font-size: 14.5px;">
                        Log Asset to Portfolio →
                    </button>
                </form>
            </div>
        </div>

        <!-- Portfolio Allocation Chart -->
        <div class="card-luxury">
            <div class="card-luxury-header">
                <h2 class="card-luxury-title">Asset Allocation Distribution</h2>
                <span class="badge-luxury badge-luxury-gold">Weightage</span>
            </div>
            <div class="card-luxury-body" style="display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 280px;">
                <?php if (!empty($portfolio_by_type)): ?>
                    <div style="position: relative; width: 100%; height: 260px;">
                        <canvas id="portfolioChart"></canvas>
                    </div>
                <?php else: ?>
                    <div style="text-align: center; color: var(--text-muted); padding: 40px 10px;">
                        <span style="font-size: 36px; display: block; margin-bottom: 8px;">📊</span>
                        <p style="font-size: 14px;">No holdings registered yet. Add assets to view your diversification breakdown.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Holdings Table / List -->
    <div class="card-luxury">
        <div class="card-luxury-header">
            <h2 class="card-luxury-title">Portfolio Holdings Ledger</h2>
            <span class="badge-luxury badge-luxury-gold"><?php echo count($investments); ?> Recorded</span>
        </div>
        
        <?php if (!empty($investments)): ?>
            <div class="table-luxury-wrap">
                <table class="table-luxury">
                    <thead>
                        <tr>
                            <th>Asset Name</th>
                            <th>Class</th>
                            <th>Purchase Cost</th>
                            <th>Current Value</th>
                            <th>Unrealized P&L</th>
                            <th>ROI %</th>
                            <th style="text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($investments as $inv): 
                            $isProfit = $inv['profit_loss'] >= 0;
                            $pColor = $isProfit ? 'var(--success)' : 'var(--danger)';
                        ?>
                            <tr>
                                <td>
                                    <strong style="color: var(--primary); font-size: 14.5px;"><?php echo htmlspecialchars($inv['name']); ?></strong>
                                    <div style="font-size: 11.5px; color: var(--text-muted);">Added <?php echo date('d-M-Y', strtotime($inv['created_at'])); ?></div>
                                </td>
                                <td>
                                    <span class="badge-luxury badge-luxury-gold"><?php echo htmlspecialchars($inv['type'] ?: 'Equity'); ?></span>
                                </td>
                                <td>₹<?php echo number_format($inv['amount'], 2); ?></td>
                                <td><strong>₹<?php echo number_format($inv['returns'], 2); ?></strong></td>
                                <td style="color: <?php echo $pColor; ?>; font-weight: 700;">
                                    <?php echo ($isProfit ? '+' : '') . '₹' . number_format($inv['profit_loss'], 2); ?>
                                </td>
                                <td>
                                    <span class="badge-luxury" style="background: <?php echo $isProfit ? 'var(--success-bg)' : 'var(--danger-bg)'; ?>; color: <?php echo $pColor; ?>;">
                                        <?php echo ($isProfit ? '▲ +' : '▼ ') . number_format($inv['return_percentage'], 1); ?>%
                                    </span>
                                </td>
                                <td style="text-align: right;">
                                    <button type="button" class="btn-luxury btn-outline" style="padding: 5px 12px; font-size: 12px;" onclick="openUpdateModal(<?php echo (int)$inv['id']; ?>, <?php echo (float)$inv['returns']; ?>, '<?php echo htmlspecialchars(addslashes($inv['name'])); ?>')">
                                        Update Value
                                    </button>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Remove this investment from portfolio?');">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="action" value="delete_investment">
                                        <input type="hidden" name="delete_id" value="<?php echo (int)$inv['id']; ?>">
                                        <button type="submit" class="btn-luxury" style="padding: 5px 12px; font-size: 12px; background: #fee2e2; color: #dc2626;">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <!-- Friendly Onboarding Empty State -->
            <div style="text-align: center; padding: 60px 24px;">
                <div style="font-size: 48px; margin-bottom: 12px;">📈</div>
                <h3 style="font-size: 18px; color: var(--primary); margin-bottom: 8px;">Your Portfolio is Empty</h3>
                <p style="color: var(--text-muted); font-size: 14px; max-width: 440px; margin: 0 auto 20px auto;">
                    Track equities, mutual fund SIPs, gold, and fixed deposits with automated P&L analytics. Log your first asset above to begin!
                </p>
                <a href="#add-asset-card" class="btn-luxury btn-gold">+ Add Your First Asset</a>
            </div>
        <?php endif; ?>
    </div>
</main>

<!-- Valuation Update Modal -->
<div id="updateValuationModal" class="modal-overlay">
    <div class="modal-content">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px;">
            <h3 style="font-size: 18px; margin: 0;" id="modalAssetTitle">Update Current Valuation</h3>
            <button type="button" onclick="closeUpdateModal()" style="background: none; border: none; font-size: 24px; cursor: pointer;">&times;</button>
        </div>
        <form method="POST">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="update_value">
            <input type="hidden" id="modal_investment_id" name="investment_id" value="">

            <div class="form-group-luxury">
                <label for="modal_new_value">Latest Market Value (₹)</label>
                <input type="number" id="modal_new_value" name="new_value" class="input-luxury input-luxury-no-icon" step="0.01" min="0" required>
            </div>

            <div style="display: flex; gap: 10px; margin-top: 24px;">
                <button type="submit" class="btn-luxury btn-gold" style="flex: 1;">Save Valuation</button>
                <button type="button" class="btn-luxury btn-outline" onclick="closeUpdateModal()">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
window.openUpdateModal = function(id, currentVal, name) {
    const modalId = document.getElementById('modal_investment_id');
    const modalVal = document.getElementById('modal_new_value');
    const modalTitle = document.getElementById('modalAssetTitle');
    const modal = document.getElementById('updateValuationModal');
    if (!modalId || !modalVal || !modal) return;

    modalId.value = id;
    modalVal.value = currentVal;
    if (modalTitle) modalTitle.textContent = 'Update: ' + name;
    modal.style.display = 'flex';
    modalVal.focus();
};

window.closeUpdateModal = function() {
    const modal = document.getElementById('updateValuationModal');
    if (modal) modal.style.display = 'none';
};

window.addEventListener('click', function(e) {
    const modal = document.getElementById('updateValuationModal');
    if (e.target === modal) window.closeUpdateModal();
});

<?php if (!empty($portfolio_by_type)): ?>
(function() {
    const ctx = document.getElementById('portfolioChart');
    if (!ctx) return;
    const labels = <?php echo json_encode(array_keys($portfolio_by_type)); ?>;
    const values = <?php echo json_encode(array_values($portfolio_by_type)); ?>;

    const chartConfig = {
        type: 'doughnut',
        data: {
            labels: labels,
            datasets: [{
                data: values,
                backgroundColor: [
                    '#d4af37', '#10b981', '#3b82f6', '#f59e0b',
                    '#8b5cf6', '#ec4899', '#06b6d4', '#64748b'
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
                        font: { family: "'Inter', sans-serif", size: 12 },
                        padding: 12
                    }
                }
            }
        }
    };

    if (window.renderChartSafe) {
        window.renderChartSafe(ctx, chartConfig);
    } else if (typeof Chart !== 'undefined') {
        new Chart(ctx, chartConfig);
    }
})();
<?php endif; ?>
</script>

<?php include "includes/footer.php"; ?>
