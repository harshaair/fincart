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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    enforce_csrf();
}

// Handle add goal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_goal') {
    $goal_name = trim($_POST['goal_name'] ?? '');
    $target_amount = floatval($_POST['target_amount'] ?? 0);
    $current_amount = floatval($_POST['current_amount'] ?? 0);
    $deadline = $_POST['deadline'] ?? '';
    $deduct_from_balance = !empty($_POST['deduct_from_balance']);

    if (empty($goal_name) || $target_amount <= 0 || $current_amount < 0 || empty($deadline)) {
        $error = 'Please fill all fields with valid positive values and a target deadline.';
    } else {
        $conn->begin_transaction();
        try {
            $sql = "INSERT INTO goals (user_id, goal_name, target_amount, current_amount, deadline, created_at) VALUES (?, ?, ?, ?, ?, NOW())";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("isdds", $user_id, $goal_name, $target_amount, $current_amount, $deadline);
            $stmt->execute();

            if ($current_amount > 0 && $deduct_from_balance) {
                // Record cash outflow in ledger transactions
                $trans_sql = "INSERT INTO transactions (user_id, type, category, amount, date, description) VALUES (?, 'expense', 'Savings Transfer', ?, CURDATE(), ?)";
                $trans_stmt = $conn->prepare($trans_sql);
                $desc = "Initial funding for goal: " . $goal_name;
                $trans_stmt->bind_param("ids", $user_id, $current_amount, $desc);
                $trans_stmt->execute();
            }

            $conn->commit();
            $message = 'Financial goal successfully created!' . ($current_amount > 0 && $deduct_from_balance ? ' Recorded initial funding of ₹' . number_format($current_amount, 2) . ' in expense ledger.' : '');
        } catch (Exception $e) {
            $conn->rollback();
            $error = 'Failed to create goal: ' . $e->getMessage();
        }
    }
}

// Handle add deposit to goal (wrapped in atomic transaction with row locking)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_deposit') {
    $goal_id = intval($_POST['goal_id'] ?? 0);
    $deposit_amount = floatval($_POST['deposit_amount'] ?? 0);
    $deduct_from_balance = !empty($_POST['deduct_from_balance']);

    if ($deposit_amount <= 0) {
        $error = 'Deposit amount must be greater than zero.';
    } else {
        $conn->begin_transaction();
        try {
            $verify_sql = "SELECT goal_name, current_amount, target_amount FROM goals WHERE id = ? AND user_id = ? FOR UPDATE";
            $verify_stmt = $conn->prepare($verify_sql);
            $verify_stmt->bind_param("ii", $goal_id, $user_id);
            $verify_stmt->execute();
            $verify_result = $verify_stmt->get_result();

            if ($verify_result->num_rows > 0) {
                $goal_data = $verify_result->fetch_assoc();
                $new_amount = min($goal_data['current_amount'] + $deposit_amount, $goal_data['target_amount']);
                $actual_added = $new_amount - $goal_data['current_amount'];

                $sql = "UPDATE goals SET current_amount = ? WHERE id = ? AND user_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("dii", $new_amount, $goal_id, $user_id);
                $stmt->execute();

                if ($deduct_from_balance && $actual_added > 0) {
                    // Record cash outflow in ledger transactions
                    $trans_sql = "INSERT INTO transactions (user_id, type, category, amount, date, description) VALUES (?, 'expense', 'Savings Transfer', ?, CURDATE(), ?)";
                    $trans_stmt = $conn->prepare($trans_sql);
                    $desc = "Deposit to goal: " . $goal_data['goal_name'];
                    $trans_stmt->bind_param("ids", $user_id, $actual_added, $desc);
                    $trans_stmt->execute();
                }

                $conn->commit();
                $message = 'Deposit of ₹' . number_format($actual_added, 2) . ' credited successfully to goal balance!' . ($deduct_from_balance && $actual_added > 0 ? ' Deducted from cash balance in expense ledger.' : '');
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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_goal') {
    $delete_id = intval($_POST['delete_id'] ?? 0);
    $sql = "DELETE FROM goals WHERE id = ? AND user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $delete_id, $user_id);
    if ($stmt->execute()) {
        $message = 'Goal removed.';
    } else {
        $error = 'Failed to delete goal.';
    }
}

// Fetch all goals for user
$goals_sql = "SELECT * FROM goals WHERE user_id = ? ORDER BY deadline ASC";
$goals_stmt = $conn->prepare($goals_sql);
$goals_stmt->bind_param("i", $user_id);
$goals_stmt->execute();
$goals_result = $goals_stmt->get_result();
$goals = [];

while ($row = $goals_result->fetch_assoc()) {
    $row['percentage'] = $row['target_amount'] > 0 ? min(100, ($row['current_amount'] / $row['target_amount']) * 100) : 0;
    $row['remaining'] = max(0, $row['target_amount'] - $row['current_amount']);
    $row['days_left'] = max(0, ceil((strtotime($row['deadline']) - time()) / (60 * 60 * 24)));
    $row['status'] = $row['current_amount'] >= $row['target_amount'] ? 'Achieved' : (time() > strtotime($row['deadline']) ? 'Overdue' : 'In Progress');
    $goals[] = $row;
}

$total_target = array_sum(array_column($goals, 'target_amount'));
$total_saved = array_sum(array_column($goals, 'current_amount'));
$goals_achieved = count(array_filter($goals, fn($g) => $g['status'] === 'Achieved'));
$overall_progress = $total_target > 0 ? ($total_saved / $total_target) * 100 : 0;

require_once "includes/components/stat_card.php";
require_once "includes/components/page_header.php";
require_once "includes/components/alert.php";

$page_title = 'Financial Savings Goals - FinCart';
include "includes/header.php";
include "includes/navbar.php";
?>

<main class="page-container" style="max-width: 1240px; margin: 0 auto; padding: 20px 24px;">
    <?php
    render_page_header([
        'title'    => 'Financial Milestone Goals',
        'subtitle' => 'Set spending horizons, emergency fund reserves, and track target completion',
        'badge'    => count($goals) . ' Active Horizons',
        'actions'  => [
            ['label' => '+ Create New Goal', 'href' => '#create-goal-card', 'class' => 'btn-gold'],
            ['label' => 'Budget Envelopes →', 'href' => 'budget.php', 'class' => 'btn-outline']
        ]
    ]);

    if (!empty($message)) {
        render_alert($message, 'success');
    }
    if (!empty($error)) {
        render_alert($error, 'danger');
    }
    ?>

    <!-- KPI Metric Summary -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 18px; margin-bottom: 28px;">
        <?php
        render_stat_card([
            'title'      => 'Cumulative Target',
            'value'      => '₹' . number_format($total_target, 2),
            'icon'       => '🎯',
            'trend'      => count($goals) . ' Goals',
            'trend_type' => 'neutral',
            'subtitle'   => 'Total planned milestones'
        ]);

        render_stat_card([
            'title'      => 'Current Reserves',
            'value'      => '₹' . number_format($total_saved, 2),
            'icon'       => '💰',
            'trend'      => number_format($overall_progress, 1) . '% Saved',
            'trend_type' => 'positive',
            'subtitle'   => 'Allocated milestone funds'
        ]);

        render_stat_card([
            'title'      => 'Goals Completed',
            'value'      => $goals_achieved . ' / ' . count($goals),
            'icon'       => '🏆',
            'trend'      => ($goals_achieved > 0 ? 'Success' : 'In Flight'),
            'trend_type' => $goals_achieved > 0 ? 'positive' : 'neutral',
            'subtitle'   => 'Targets 100% funded'
        ]);

        render_stat_card([
            'title'      => 'Funding Deficit',
            'value'      => '₹' . number_format(max(0, $total_target - $total_saved), 2),
            'icon'       => '⏳',
            'trend'      => 'Required Balance',
            'trend_type' => 'neutral',
            'subtitle'   => 'Remaining to target'
        ]);
        ?>
    </div>

    <!-- Create Goal Form -->
    <div class="card-luxury" id="create-goal-card" style="margin-bottom: 28px;">
        <div class="card-luxury-header">
            <h2 class="card-luxury-title">+ Create Milestone Goal</h2>
            <span class="badge-luxury badge-luxury-gold">Goal Setting</span>
        </div>
        <div class="card-luxury-body">
            <form method="POST">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="add_goal">

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 16px;">
                    <div class="form-group-luxury">
                        <label for="goal_name">Goal Title</label>
                        <input type="text" id="goal_name" name="goal_name" class="input-luxury input-luxury-no-icon" placeholder="e.g. Emergency Fund (6 Mos), Europe Trip" required>
                    </div>

                    <div class="form-group-luxury">
                        <label for="target_amount">Target Capital (₹)</label>
                        <input type="number" id="target_amount" name="target_amount" class="input-luxury input-luxury-no-icon" step="0.01" min="1" placeholder="200000" required>
                    </div>

                    <div class="form-group-luxury">
                        <label for="current_amount">Initial Deposit (₹)</label>
                        <input type="number" id="current_amount" name="current_amount" class="input-luxury input-luxury-no-icon" step="0.01" min="0" placeholder="25000" value="0" required>
                    </div>

                    <div class="form-group-luxury">
                        <label for="deadline">Target Completion Date</label>
                        <input type="date" id="deadline" name="deadline" class="input-luxury input-luxury-no-icon" required>
                    </div>
                </div>

                <div class="form-group-luxury" style="margin-top: 14px; margin-bottom: 8px;">
                    <label style="display: flex; align-items: flex-start; gap: 10px; cursor: pointer; font-size: 13.5px; font-weight: 500; color: var(--text-dark);">
                        <input type="checkbox" name="deduct_from_balance" value="1" checked style="width: 17px; height: 17px; margin-top: 2px; accent-color: var(--gold, #d4af37);">
                        <span>Deduct initial deposit from cash balance (record expense transaction)</span>
                    </label>
                    <div style="font-size: 11.5px; color: var(--text-muted); margin-left: 27px; margin-top: 2px;">
                        Only applies if Initial Deposit is greater than ₹0. Uncheck if tracking previously saved funds.
                    </div>
                </div>

                <button type="submit" class="btn-luxury btn-gold" style="padding: 12px 24px; margin-top: 8px;">
                    Save Milestone Goal →
                </button>
            </form>
        </div>
    </div>

    <!-- Goals Card Grid -->
    <div class="card-luxury">
        <div class="card-luxury-header">
            <h2 class="card-luxury-title">Active Milestone Horizons</h2>
            <span class="badge-luxury badge-luxury-gold"><?php echo count($goals); ?> Enrolled</span>
        </div>
        <div class="card-luxury-body">
            <?php if (!empty($goals)): ?>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 20px;">
                    <?php foreach ($goals as $goal): 
                        $pct = $goal['percentage'];
                        $isDone = $goal['status'] === 'Achieved';
                        $statusBadgeClass = $isDone ? 'badge-luxury-success' : ($goal['status'] === 'Overdue' ? 'badge-luxury-danger' : 'badge-luxury-gold');
                    ?>
                        <div style="border: 1px solid var(--border-color); border-radius: var(--radius-sm); padding: 20px; background: #fafaf9; display: flex; flex-direction: column; justify-content: space-between;">
                            <div>
                                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px;">
                                    <h3 style="font-size: 16px; font-weight: 700; color: var(--primary); margin: 0;"><?php echo htmlspecialchars($goal['goal_name']); ?></h3>
                                    <span class="badge-luxury <?php echo $statusBadgeClass; ?>"><?php echo htmlspecialchars($goal['status']); ?></span>
                                </div>

                                <div style="display: flex; justify-content: space-between; font-size: 13.5px; margin-bottom: 8px;">
                                    <span style="color: var(--text-muted);">Saved: <strong style="color: var(--primary);">₹<?php echo number_format($goal['current_amount'], 2); ?></strong></span>
                                    <span style="color: var(--text-muted);">Target: <strong>₹<?php echo number_format($goal['target_amount'], 2); ?></strong></span>
                                </div>

                                <!-- Progress Bar -->
                                <div style="width: 100%; height: 8px; background: #e5e5e0; border-radius: 999px; overflow: hidden; margin-bottom: 12px;">
                                    <div style="width: <?php echo min(100, $pct); ?>%; height: 100%; background: linear-gradient(90deg, var(--accent) 0%, #10b981 100%); border-radius: 999px; transition: width 0.3s ease;"></div>
                                </div>

                                <div style="display: flex; justify-content: space-between; font-size: 12px; color: var(--text-muted); margin-bottom: 16px;">
                                    <span><?php echo number_format($pct, 1); ?>% complete</span>
                                    <span><?php echo $isDone ? 'Target Reached! 🎉' : ($goal['days_left'] . ' day(s) remaining'); ?></span>
                                </div>
                            </div>

                            <div style="display: flex; gap: 8px; border-top: 1px solid var(--border-light); padding-top: 14px;">
                                <?php if (!$isDone): ?>
                                    <button type="button" class="btn-luxury btn-outline" style="flex: 1; padding: 7px; font-size: 12.5px;" onclick="openDepositModal(<?php echo (int)$goal['id']; ?>, '<?php echo htmlspecialchars(addslashes($goal['goal_name'])); ?>')">
                                        + Add Deposit
                                    </button>
                                <?php endif; ?>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this savings goal?');">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="action" value="delete_goal">
                                    <input type="hidden" name="delete_id" value="<?php echo (int)$goal['id']; ?>">
                                    <button type="submit" class="btn-luxury" style="padding: 7px 12px; font-size: 12.5px; background: #fee2e2; color: #dc2626;">Delete</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <!-- Onboarding Empty State -->
                <div style="text-align: center; padding: 60px 24px;">
                    <div style="font-size: 48px; margin-bottom: 12px;">🎯</div>
                    <h3 style="font-size: 18px; color: var(--primary); margin-bottom: 8px;">No Savings Goals Created Yet</h3>
                    <p style="color: var(--text-muted); font-size: 14px; max-width: 440px; margin: 0 auto 20px auto;">
                        Break down major financial targets like buying a home, emergency funds, or family vacations into manageable monthly milestones.
                    </p>
                    <a href="#create-goal-card" class="btn-luxury btn-gold">+ Create Your First Goal</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<!-- Deposit Modal -->
<div id="depositModal" class="modal-overlay">
    <div class="modal-content">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px;">
            <h3 style="font-size: 18px; margin: 0;" id="modalGoalTitle">Credit Deposit to Goal</h3>
            <button type="button" onclick="closeDepositModal()" style="background: none; border: none; font-size: 24px; cursor: pointer;">&times;</button>
        </div>
        <form method="POST">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="add_deposit">
            <input type="hidden" id="modal_goal_id" name="goal_id" value="">

            <div class="form-group-luxury">
                <label for="deposit_amount">Deposit Amount (₹)</label>
                <input type="number" id="deposit_amount" name="deposit_amount" class="input-luxury input-luxury-no-icon" step="0.01" min="1" placeholder="5000" required>
            </div>

            <div class="form-group-luxury" style="margin-top: 14px;">
                <label style="display: flex; align-items: flex-start; gap: 10px; cursor: pointer; font-size: 13px; font-weight: 500; color: var(--text-dark);">
                    <input type="checkbox" name="deduct_from_balance" value="1" checked style="width: 16px; height: 16px; margin-top: 2px; accent-color: var(--gold, #d4af37);">
                    <span>Deduct from cash balance (record expense transaction)</span>
                </label>
            </div>

            <div style="display: flex; gap: 10px; margin-top: 22px;">
                <button type="submit" class="btn-luxury btn-gold" style="flex: 1;">Credit Deposit</button>
                <button type="button" class="btn-luxury btn-outline" onclick="closeDepositModal()">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
function openDepositModal(goalId, goalName) {
    document.getElementById('modal_goal_id').value = goalId;
    document.getElementById('modalGoalTitle').textContent = 'Deposit to: ' + goalName;
    document.getElementById('deposit_amount').value = '';
    document.getElementById('depositModal').style.display = 'flex';
    document.getElementById('deposit_amount').focus();
}

function closeDepositModal() {
    document.getElementById('depositModal').style.display = 'none';
}

window.addEventListener('click', function(e) {
    const modal = document.getElementById('depositModal');
    if (e.target === modal) closeDepositModal();
});
</script>

<?php include "includes/footer.php"; ?>
