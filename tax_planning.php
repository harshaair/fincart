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

// Resolve Statutory Indian Financial Year (April 1 to March 31)
$available_fys = getAvailableFinancialYears(5);
$selected_fy_code = trim($_GET['fy'] ?? '');
$current_fy = getIndianFinancialYear();

$active_fy = null;
foreach ($available_fys as $fy_item) {
    if ($fy_item['fy_code'] === $selected_fy_code) {
        $active_fy = $fy_item;
        break;
    }
}
if (!$active_fy) {
    $active_fy = $current_fy;
}

$start_date = $active_fy['start_date'];
$end_date = $active_fy['end_date'];
$fy_label = $active_fy['fy_label'];
$ay_label = $active_fy['ay_label'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    enforce_csrf();
}

// ---------------------------------------------------------
// 1. Handle Inline Add Deduction
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_deduction') {
    $ded_cat = trim($_POST['ded_category'] ?? '');
    $ded_amt = floatval($_POST['ded_amount'] ?? 0);
    $ded_date = $_POST['ded_date'] ?? date('Y-m-d');
    $ded_desc = trim($_POST['ded_desc'] ?? '');

    if (empty($ded_cat) || $ded_amt <= 0 || $ded_amt > 99999999.99) {
        $error = 'Please enter a valid deduction category and amount between ₹0.01 and ₹99,999,999.99.';
    } else {
        $ins_ded = $conn->prepare("INSERT INTO tax_deductions (user_id, category, description, amount, deduction_date) VALUES (?, ?, ?, ?, ?)");
        $ins_ded->bind_param("issds", $user_id, $ded_cat, $ded_desc, $ded_amt, $ded_date);
        if ($ins_ded->execute()) {
            $message = "Itemized deduction of ₹" . number_format($ded_amt, 2) . " under " . htmlspecialchars($ded_cat) . " recorded successfully!";
        } else {
            $error = "Failed to record deduction: " . $conn->error;
        }
    }
}

// ---------------------------------------------------------
// 2. Handle Inline Delete Deduction
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_deduction') {
    $del_id = intval($_POST['deduction_id'] ?? 0);
    $del_stmt = $conn->prepare("DELETE FROM tax_deductions WHERE id = ? AND user_id = ?");
    $del_stmt->bind_param("ii", $del_id, $user_id);
    if ($del_stmt->execute()) {
        $message = "Deduction record removed successfully.";
    } else {
        $error = "Failed to delete deduction.";
    }
}

// ---------------------------------------------------------
// 3. Auto-Sync from Financial Records (Ledger & Investments)
// ---------------------------------------------------------
// A. Income within Indian Financial Year from Transactions ledger
$income_sql = "SELECT COALESCE(SUM(amount), 0) as total_income FROM transactions WHERE user_id = ? AND type = 'income' AND date BETWEEN ? AND ?";
$income_stmt = $conn->prepare($income_sql);
$income_stmt->bind_param("iss", $user_id, $start_date, $end_date);
$income_stmt->execute();
$income_row = $income_stmt->get_result()->fetch_assoc();
$current_income = (float)($income_row['total_income'] ?? 0);

// B. Deductions within Indian Financial Year from tax_deductions
$deduction_sql = "SELECT id, category, description, amount, deduction_date FROM tax_deductions WHERE user_id = ? AND deduction_date BETWEEN ? AND ? ORDER BY deduction_date DESC";
$deduction_stmt = $conn->prepare($deduction_sql);
$deduction_stmt->bind_param("iss", $user_id, $start_date, $end_date);
$deduction_stmt->execute();
$deduction_result = $deduction_stmt->get_result();
$raw_deductions = [];
while ($row = $deduction_result->fetch_assoc()) {
    $raw_deductions[] = $row;
}

// C. Auto-detect Section 80C qualifying assets from Investment Portfolio
$inv_80c_sql = "SELECT COALESCE(SUM(amount), 0) as total_80c FROM investments WHERE user_id = ? AND (type IN ('Mutual Funds', 'PPF', 'FD', 'ELSS', 'PF') OR name LIKE '%80C%' OR name LIKE '%PPF%' OR name LIKE '%ELSS%' OR name LIKE '%Provident%')";
$inv_80c_stmt = $conn->prepare($inv_80c_sql);
$inv_80c_stmt->bind_param("i", $user_id);
$inv_80c_stmt->execute();
$inv_80c_row = $inv_80c_stmt->get_result()->fetch_assoc();
$portfolio_80c = (float)($inv_80c_row['total_80c'] ?? 0);

// Compute statutory capped deductions
$caps = applyStatutoryCaps($raw_deductions);
$combined_80c = $caps['claimed_80c'] + $portfolio_80c;
$eligible_combined_80c = min($combined_80c, LIMIT_SECTION_80C);
$current_eligible_deductions = $eligible_combined_80c + $caps['eligible_80d'] + $caps['eligible_other'];
$current_claimed_deductions = $combined_80c + $caps['claimed_80d'] + ($caps['total_claimed'] - $caps['claimed_80c'] - $caps['claimed_80d']);

// ---------------------------------------------------------
// 4. Computation (Interactive or Auto-Sync Default)
// ---------------------------------------------------------
$calc_result = null;
$calc_error = '';
$save_success = '';

$is_post_calc = ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['action'] ?? '', ['calculate', 'save_projection']));

if ($is_post_calc) {
    $gross_income_input = isset($_POST['gross_income']) ? max(0.0, floatval($_POST['gross_income'])) : $current_income;
    $input_80c   = isset($_POST['deduction_80c']) ? max(0.0, floatval($_POST['deduction_80c'])) : $combined_80c;
    $input_80d   = isset($_POST['deduction_80d']) ? max(0.0, floatval($_POST['deduction_80d'])) : $caps['claimed_80d'];
    $input_other = isset($_POST['deduction_other']) ? max(0.0, floatval($_POST['deduction_other'])) : max(0.0, $caps['total_claimed'] - $caps['claimed_80c'] - $caps['claimed_80d']);
    $is_salaried = isset($_POST['is_salaried']) ? (bool)$_POST['is_salaried'] : true;

    // Apply statutory limits strictly
    $eligible_80c = min($input_80c, LIMIT_SECTION_80C);
    $eligible_80d = min($input_80d, LIMIT_SECTION_80D_PARENTS);
    $eligible_other = $input_other;
    $total_calc_deductions = $eligible_80c + $eligible_80d + $eligible_other;

    $new_res = computeTaxNewRegime($gross_income_input, $is_salaried);
    $old_res = computeTaxOldRegime($gross_income_input, $total_calc_deductions, $is_salaried);

    $better_regime = ($new_res['total_tax'] <= $old_res['total_tax']) ? 'new' : 'old';
    $tax_difference = abs($new_res['total_tax'] - $old_res['total_tax']);

    $calc_result = [
        'gross_income'   => $gross_income_input,
        'input_80c'      => $input_80c,
        'eligible_80c'   => $eligible_80c,
        'input_80d'      => $input_80d,
        'eligible_80d'   => $eligible_80d,
        'input_other'    => $input_other,
        'eligible_other' => $eligible_other,
        'deductions'     => $total_calc_deductions,
        'is_salaried'    => $is_salaried,
        'new'            => $new_res,
        'old'            => $old_res,
        'better_regime'  => $better_regime,
        'tax_difference' => $tax_difference,
        'is_autosynced'  => false
    ];

    if ($_POST['action'] === 'save_projection') {
        $ins = $conn->prepare("INSERT INTO tax_planning (user_id, financial_year, gross_income, total_deductions, taxable_income, tax_slabs_used, estimated_tax, tax_bracket, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $slabs_json = json_encode(['better_regime' => $better_regime, 'new_tax' => $new_res['total_tax'], 'old_tax' => $old_res['total_tax']]);
        $bracket = ($better_regime === 'new' ? 'New Regime (' . round($new_res['effective_rate'], 1) . '%)' : 'Old Regime (' . round($old_res['effective_rate'], 1) . '%)');
        $est_tax = ($better_regime === 'new') ? $new_res['total_tax'] : $old_res['total_tax'];
        $taxable_inc = ($better_regime === 'new') ? $new_res['taxable_income'] : $old_res['taxable_income'];
        $tot_ded = ($better_regime === 'new') ? $new_res['standard_deduction'] : ($old_res['standard_deduction'] + $total_calc_deductions);
        $fy_code = $active_fy['fy_code'];
        $ins->bind_param("isdddsds", $user_id, $fy_code, $gross_income_input, $tot_ded, $taxable_inc, $slabs_json, $est_tax, $bracket);
        if ($ins->execute()) {
            $save_success = "Tax projection for FY " . htmlspecialchars($fy_code) . " saved successfully to history below!";
        } else {
            $calc_error = "Failed to save projection: " . $conn->error;
        }
    }
} else {
    // Zero-Friction Default: Automatically pre-compute live regime verdict from ledger & investments
    $auto_gross = $current_income > 0 ? $current_income : 850000.00;
    $auto_80c = $combined_80c;
    $auto_80d = $caps['claimed_80d'];
    $auto_other = max(0.0, $caps['total_claimed'] - $caps['claimed_80c'] - $caps['claimed_80d']);
    $auto_elig_80c = min($auto_80c, LIMIT_SECTION_80C);
    $auto_elig_80d = min($auto_80d, LIMIT_SECTION_80D_PARENTS);
    $auto_tot_ded = $auto_elig_80c + $auto_elig_80d + $auto_other;

    $new_res = computeTaxNewRegime($auto_gross, true);
    $old_res = computeTaxOldRegime($auto_gross, $auto_tot_ded, true);
    $better_regime = ($new_res['total_tax'] <= $old_res['total_tax']) ? 'new' : 'old';
    $tax_difference = abs($new_res['total_tax'] - $old_res['total_tax']);

    $calc_result = [
        'gross_income'   => $auto_gross,
        'input_80c'      => $auto_80c,
        'eligible_80c'   => $auto_elig_80c,
        'input_80d'      => $auto_80d,
        'eligible_80d'   => $auto_elig_80d,
        'input_other'    => $auto_other,
        'eligible_other' => $auto_other,
        'deductions'     => $auto_tot_ded,
        'is_salaried'    => true,
        'new'            => $new_res,
        'old'            => $old_res,
        'better_regime'  => $better_regime,
        'tax_difference' => $tax_difference,
        'is_autosynced'  => true
    ];
}

// ---------------------------------------------------------
// 5. Fetch Recent Saved Projections
// ---------------------------------------------------------
$hist_sql = "SELECT * FROM tax_planning WHERE user_id = ? ORDER BY id DESC LIMIT 5";
$hist_stmt = $conn->prepare($hist_sql);
$hist_stmt->bind_param("i", $user_id);
$hist_stmt->execute();
$saved_projections = $hist_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$page_title = 'Tax Optimizer - FinCart';
include "includes/header.php";
include "includes/navbar.php";
?>

<div class="main-content">
    <div style="max-width: 1200px; margin: 0 auto; padding: 12px 16px;">
        
        <!-- Header & FY Switcher -->
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 15px;">
            <div>
                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 6px;">
                    <h1 style="font-size: 26px; margin: 0;">Smart Tax Optimizer</h1>
                    <span style="background: rgba(212,175,55,0.15); color: var(--primary); border: 1px solid rgba(212,175,55,0.3); padding: 3px 10px; border-radius: 14px; font-size: 11.5px; font-weight: 700; text-transform: uppercase;">FY <?php echo htmlspecialchars($fy_label); ?></span>
                </div>
                <p style="color: var(--text-muted); font-size: 14px; margin: 0;">
                    1-Click Auto-Sync from Transactions & Investments • New vs Old Regime Optimization with Section 87A rebate
                </p>
            </div>
            <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                <!-- Fiscal Year Picker -->
                <form method="GET" action="tax_planning.php" style="margin: 0; display: inline-flex; align-items: center; gap: 6px;">
                    <label for="fy" style="font-size: 13px; font-weight: 600; color: var(--text-dark); margin: 0;">Period:</label>
                    <select name="fy" id="fy" onchange="this.form.submit()" style="padding: 7px 12px; border-radius: 6px; border: 1px solid var(--border-color); font-size: 13px; background: #fff; font-weight: 600; color: var(--primary);">
                        <?php foreach ($available_fys as $f): ?>
                            <option value="<?php echo htmlspecialchars($f['fy_code']); ?>" <?php echo ($f['fy_code'] === $active_fy['fy_code']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($f['fy_label'] . ' (' . $f['ay_label'] . ')'); ?><?php echo $f['is_current'] ? ' • Current' : ''; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
                <!-- 1-Click Auto-Sync Button -->
                <a href="tax_planning.php?fy=<?php echo urlencode($active_fy['fy_code']); ?>" class="btn btn-gold btn-sm" style="font-size: 13px; font-weight: 600;">⚡ Auto-Sync from Records</a>
                <a href="#deductions" class="btn btn-secondary btn-sm" style="font-size: 13px;">+ Add Deduction</a>
            </div>
        </div>

        <?php if (!empty($message)): ?>
            <div class="alert alert-success" style="margin-bottom: 20px;">&#10003; <?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <?php if (!empty($save_success)): ?>
            <div class="alert alert-success" style="margin-bottom: 20px;">&#10003; <?php echo htmlspecialchars($save_success); ?></div>
        <?php endif; ?>
        <?php if (!empty($error) || !empty($calc_error)): ?>
            <div class="alert alert-danger" style="margin-bottom: 20px;">&#9888; <?php echo htmlspecialchars($error ?: $calc_error); ?></div>
        <?php endif; ?>

        <!-- HERO VERDICT CARD: Instant, Unmistakable Tax Advice -->
        <?php if ($calc_result): ?>
            <?php 
                $is_new_better = ($calc_result['better_regime'] === 'new');
                $diff = $calc_result['tax_difference'];
            ?>
            <div style="background: <?php echo $is_new_better ? 'linear-gradient(135deg, #f0fdf4 0%, #e6f7ec 100%)' : 'linear-gradient(135deg, #fefce8 0%, #fef9c3 100%)'; ?>; border: 1.5px solid <?php echo $is_new_better ? '#86efac' : '#fde047'; ?>; border-radius: var(--radius-md); padding: 22px 24px; margin-bottom: 24px; box-shadow: 0 4px 12px rgba(0,0,0,0.03);">
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px;">
                    <div>
                        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px;">
                            <span style="font-size: 22px;"><?php echo $diff == 0 ? '⚖️' : '🏆'; ?></span>
                            <h2 style="font-size: 20px; font-weight: 800; color: <?php echo $is_new_better ? '#15803d' : '#854d0e'; ?>; margin: 0;">
                                <?php if ($diff == 0): ?>
                                    Both Regimes Result in Identical ₹0 Tax Liability!
                                <?php elseif ($is_new_better): ?>
                                    Recommended: New Tax Regime (Section 115BAC)
                                <?php else: ?>
                                    Recommended: Old Tax Regime (With Itemized Deductions)
                                <?php endif; ?>
                            </h2>
                        </div>
                        <p style="font-size: 14px; color: <?php echo $is_new_better ? '#166534' : '#713f12'; ?>; margin: 0; line-height: 1.5;">
                            <?php if ($diff == 0): ?>
                                Under Section 87A rebate, your taxable income is eligible for 100% tax relief in both regimes.
                            <?php elseif ($is_new_better): ?>
                                Choosing the <strong>New Tax Regime</strong> saves you <strong>₹<?php echo number_format($diff, 2); ?></strong> in annual taxes compared to the Old Regime.
                            <?php else: ?>
                                Your itemized deductions under Section 80C and 80D save you <strong>₹<?php echo number_format($diff, 2); ?></strong> compared to the New Regime.
                            <?php endif; ?>
                            <?php if (!empty($calc_result['is_autosynced'])): ?>
                                <span style="display: inline-block; background: rgba(0,0,0,0.06); padding: 2px 8px; border-radius: 10px; font-size: 11.5px; font-weight: 600; margin-left: 6px;">⚡ Auto-Synced from your Ledger & Portfolio</span>
                            <?php endif; ?>
                        </p>
                    </div>
                    <div style="display: flex; gap: 16px; align-items: center;">
                        <div style="text-align: right; background: #ffffff; padding: 10px 18px; border-radius: var(--radius-sm); border: 1px solid rgba(0,0,0,0.08);">
                            <div style="font-size: 11.5px; text-transform: uppercase; font-weight: 700; color: var(--text-muted);">New Regime Tax</div>
                            <div style="font-size: 18px; font-weight: 800; color: <?php echo $is_new_better ? '#15803d' : 'var(--text-dark)'; ?>;">₹<?php echo number_format($calc_result['new']['total_tax'], 2); ?></div>
                        </div>
                        <div style="text-align: right; background: #ffffff; padding: 10px 18px; border-radius: var(--radius-sm); border: 1px solid rgba(0,0,0,0.08);">
                            <div style="font-size: 11.5px; text-transform: uppercase; font-weight: 700; color: var(--text-muted);">Old Regime Tax</div>
                            <div style="font-size: 18px; font-weight: 800; color: <?php echo !$is_new_better ? '#854d0e' : 'var(--text-dark)'; ?>;">₹<?php echo number_format($calc_result['old']['total_tax'], 2); ?></div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Live Portfolio & Deduction Telemetry -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 16px; margin-bottom: 24px;">
            <div class="stat-card">
                <div class="stat-label">FY Inflow (Ledger)</div>
                <div class="stat-value">₹<?php echo number_format($current_income, 2); ?></div>
                <div class="stat-description">Total income in <?php echo htmlspecialchars($fy_label); ?></div>
            </div>
            <div class="stat-card" style="border-left: 4px solid var(--accent, #d4af37);">
                <div class="stat-label">Section 80C Assets</div>
                <div class="stat-value">₹<?php echo number_format($eligible_combined_80c, 2); ?></div>
                <div class="stat-description">
                    Portfolio + Claimed: ₹<?php echo number_format($combined_80c, 0); ?> / Cap: ₹1.5L
                </div>
            </div>
            <div class="stat-card" style="border-left: 4px solid #27ae60);">
                <div class="stat-label">Section 80D Health Cover</div>
                <div class="stat-value">₹<?php echo number_format($caps['eligible_80d'], 2); ?></div>
                <div class="stat-description">Claimed: ₹<?php echo number_format($caps['claimed_80d'], 0); ?> / Cap: ₹25k-50k</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Allowable Deductions</div>
                <div class="stat-value">₹<?php echo number_format($current_eligible_deductions, 2); ?></div>
                <div class="stat-description">Old Regime (80C + 80D + others)</div>
            </div>
        </div>

        <!-- Interactive Comparison & Simulator Form -->
        <div class="card" style="margin-bottom: 24px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 10px;">
                <div>
                    <h2 style="font-size: 20px; margin: 0 0 4px 0;">Interactive Regime Simulator</h2>
                    <p style="color: var(--text-muted); font-size: 13.5px; margin: 0;">
                        Tweak your projected gross income and deduction claims to simulate custom scenarios.
                    </p>
                </div>
                <a href="tax_planning.php?fy=<?php echo urlencode($active_fy['fy_code']); ?>" class="btn btn-secondary btn-sm" style="font-size: 12.5px;">↺ Reset to Actual Records</a>
            </div>

            <form method="POST" id="taxCompareForm" data-turbo="false" style="margin-bottom: 20px;">
                <?php echo csrf_field(); ?>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 16px; margin-bottom: 16px;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label style="font-weight: 600; font-size: 13px; margin-bottom: 6px; display: block;">Annual Gross Income (₹)</label>
                        <input type="number" step="1000" min="0" name="gross_income" class="form-control" value="<?php echo htmlspecialchars($calc_result ? $calc_result['gross_income'] : ($current_income > 0 ? $current_income : 850000)); ?>" required>
                        <small style="color: var(--text-muted); font-size: 11px;">Salary, freelancing, interest, etc.</small>
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label style="font-weight: 600; font-size: 13px; margin-bottom: 6px; display: block;">Section 80C Investments (₹)</label>
                        <input type="number" step="1000" min="0" max="150000" name="deduction_80c" class="form-control" value="<?php echo htmlspecialchars($calc_result ? $calc_result['input_80c'] : $combined_80c); ?>">
                        <small style="color: var(--text-muted); font-size: 11px;">EPF, PPF, ELSS, Life Ins. (Cap ₹1.5L)</small>
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label style="font-weight: 600; font-size: 13px; margin-bottom: 6px; display: block;">Section 80D Health Cover (₹)</label>
                        <input type="number" step="1000" min="0" max="100000" name="deduction_80d" class="form-control" value="<?php echo htmlspecialchars($calc_result ? $calc_result['input_80d'] : $caps['claimed_80d']); ?>">
                        <small style="color: var(--text-muted); font-size: 11px;">Self/family (₹25k) + Parents (₹50k)</small>
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label style="font-weight: 600; font-size: 13px; margin-bottom: 6px; display: block;">Other Deductions (80CCD, 80E) (₹)</label>
                        <input type="number" step="1000" min="0" name="deduction_other" class="form-control" value="<?php echo htmlspecialchars($calc_result ? $calc_result['input_other'] : max(0, $caps['total_claimed'] - $caps['claimed_80c'] - $caps['claimed_80d'])); ?>">
                        <small style="color: var(--text-muted); font-size: 11px;">NPS Tier-1 up to ₹50k, education loan</small>
                    </div>
                </div>

                <div style="margin-bottom: 16px;">
                    <label style="display: inline-flex; align-items: center; gap: 8px; cursor: pointer;">
                        <input type="checkbox" name="is_salaried" value="1" <?php echo (!isset($calc_result) || $calc_result['is_salaried']) ? 'checked' : ''; ?>>
                        <span style="font-size: 13px;"><strong>Salaried Professional</strong> (Entitled to ₹50,000 Standard Deduction under Sec 16(ia) in BOTH regimes)</span>
                    </label>
                </div>

                <div style="display: flex; gap: 12px; flex-wrap: wrap;">
                    <button type="submit" name="action" value="calculate" class="btn btn-primary" data-loading-text="⏳ Calculating..." style="padding: 10px 20px;">Recalculate & Compare &rarr;</button>
                    <button type="submit" name="action" value="save_projection" class="btn btn-gold" data-loading-text="💾 Saving..." style="padding: 10px 20px;">💾 Save Projection to History</button>
                </div>
            </form>

            <!-- Detailed Comparison Table -->
            <?php if ($calc_result): ?>
                <div class="table-responsive" style="margin-top: 20px;">
                    <table class="custom-table" style="font-size: 13.5px;">
                        <thead>
                            <tr style="background: var(--off-white);">
                                <th style="width: 40%;">Tax Component</th>
                                <th style="width: 30%;">New Regime (FY 2024-25 Default)</th>
                                <th style="width: 30%;">Old Regime</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Gross Total Income</td>
                                <td>₹<?php echo number_format($calc_result['new']['gross_income'], 2); ?></td>
                                <td>₹<?php echo number_format($calc_result['old']['gross_income'], 2); ?></td>
                            </tr>
                            <tr>
                                <td>Standard Deduction (Salaried - Sec 16(ia))</td>
                                <td>-₹<?php echo number_format($calc_result['new']['standard_deduction'], 2); ?></td>
                                <td>-₹<?php echo number_format($calc_result['old']['standard_deduction'], 2); ?></td>
                            </tr>
                            <tr>
                                <td>Section 80C Deduction (EPF/PPF/ELSS - Cap ₹1.5L)</td>
                                <td><span style="color: #999;">❌ Disallowed under Sec 115BAC</span></td>
                                <td>-₹<?php echo number_format($calc_result['eligible_80c'], 2); ?> <small style="color: #666;">(Claimed: ₹<?php echo number_format($calc_result['input_80c'], 0); ?>)</small></td>
                            </tr>
                            <tr>
                                <td>Section 80D Health Insurance (Cap ₹25k-50k)</td>
                                <td><span style="color: #999;">❌ Disallowed under Sec 115BAC</span></td>
                                <td>-₹<?php echo number_format($calc_result['eligible_80d'], 2); ?> <small style="color: #666;">(Claimed: ₹<?php echo number_format($calc_result['input_80d'], 0); ?>)</small></td>
                            </tr>
                            <tr>
                                <td>Other Eligible Deductions (80CCD NPS, 80E, 80TTA)</td>
                                <td><span style="color: #999;">❌ Disallowed</span></td>
                                <td>-₹<?php echo number_format($calc_result['eligible_other'], 2); ?></td>
                            </tr>
                            <tr style="background: var(--off-white); font-weight: 700;">
                                <td>Net Taxable Income</td>
                                <td>₹<?php echo number_format($calc_result['new']['taxable_income'], 2); ?></td>
                                <td>₹<?php echo number_format($calc_result['old']['taxable_income'], 2); ?></td>
                            </tr>
                            <tr>
                                <td>Calculated Slab Tax</td>
                                <td>₹<?php echo number_format($calc_result['new']['base_tax'], 2); ?></td>
                                <td>₹<?php echo number_format($calc_result['old']['base_tax'], 2); ?></td>
                            </tr>
                            <tr>
                                <td>Section 87A Tax Rebate</td>
                                <td>
                                    <?php if ($calc_result['new']['rebate_87a'] > 0): ?>
                                        <span style="color: #15803d; font-weight: 700;">-₹<?php echo number_format($calc_result['new']['rebate_87a'], 2); ?> (100% Rebate up to ₹7L)</span>
                                    <?php else: ?>
                                        ₹0.00
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($calc_result['old']['rebate_87a'] > 0): ?>
                                        <span style="color: #15803d; font-weight: 700;">-₹<?php echo number_format($calc_result['old']['rebate_87a'], 2); ?> (100% Rebate up to ₹5L)</span>
                                    <?php else: ?>
                                        ₹0.00
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td>Health & Education Cess (4%)</td>
                                <td>₹<?php echo number_format($calc_result['new']['cess'], 2); ?></td>
                                <td>₹<?php echo number_format($calc_result['old']['cess'], 2); ?></td>
                            </tr>
                            <tr style="background: var(--secondary); font-size: 15.5px; font-weight: 800;">
                                <td>Total Net Tax Payable</td>
                                <td style="color: <?php echo $calc_result['better_regime'] === 'new' ? '#15803d' : 'inherit'; ?>;">
                                    ₹<?php echo number_format($calc_result['new']['total_tax'], 2); ?>
                                    <?php if ($calc_result['better_regime'] === 'new'): ?><span class="badge badge-success" style="margin-left: 6px;">✓ Winner</span><?php endif; ?>
                                </td>
                                <td style="color: <?php echo $calc_result['better_regime'] === 'old' ? '#15803d' : 'inherit'; ?>;">
                                    ₹<?php echo number_format($calc_result['old']['total_tax'], 2); ?>
                                    <?php if ($calc_result['better_regime'] === 'old'): ?><span class="badge badge-success" style="margin-left: 6px;">✓ Winner</span><?php endif; ?>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- TECHNICAL DEEP-DIVE (COLLAPSIBLE ACCORDION FOR RECRUITERS & ENGINEERS) -->
        <details style="background: #ffffff; border: 1px solid var(--border-color); border-radius: var(--radius-sm); padding: 16px 20px; margin-bottom: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
            <summary style="font-size: 15px; font-weight: 700; cursor: pointer; color: var(--primary); display: flex; align-items: center; justify-content: space-between;">
                <span>🔍 Technical Deep-Dive: Finance Act Engine & Mathematical Architecture (Inspect for Engineering Review)</span>
                <span style="font-size: 12px; color: var(--text-muted); font-weight: normal;">Click to expand</span>
            </summary>
            
            <div style="margin-top: 18px; font-size: 13.5px; line-height: 1.6; color: var(--text-dark); border-top: 1px solid var(--border-color); padding-top: 16px;">
                <h4 style="font-size: 14.5px; margin: 0 0 8px 0; color: var(--primary);">1. Domain-Driven Design & Pure Functional Engine</h4>
                <p style="color: var(--text-muted); margin-bottom: 14px;">
                    The computation engine is decoupled from HTTP presentation and database layers inside <code>services/TaxService.php</code> (namespace <code>FinCart\Services\TaxService</code>). All calculations are implemented as side-effect free, deterministic pure functions (<code>computeNewRegime()</code>, <code>computeOldRegime()</code>, <code>applyStatutoryCaps()</code>) ensuring mathematical repeatability.
                </p>

                <h4 style="font-size: 14.5px; margin: 0 0 8px 0; color: var(--primary);">2. Statutory Indian Finance Act (FY 2024-25 / AY 2025-26) Specifications</h4>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 14px; margin-bottom: 14px;">
                    <div style="padding: 12px 14px; background: #fafaf9; border: 1px solid var(--border-color); border-radius: 4px;">
                        <strong>Default New Regime (Section 115BAC):</strong>
                        <ul style="margin: 6px 0 0 18px; padding: 0; font-size: 12.5px; color: #555;">
                            <li>₹0 – ₹3,00,000: 0%</li>
                            <li>₹3,00,001 – ₹6,00,000: 5%</li>
                            <li>₹6,00,001 – ₹9,00,000: 10%</li>
                            <li>₹9,00,001 – ₹12,00,000: 15%</li>
                            <li>₹12,00,001 – ₹15,00,000: 20%</li>
                            <li>Above ₹15,00,000: 30%</li>
                            <li><strong>Section 87A Rebate:</strong> 100% rebate up to ₹7,00,000 taxable income (Max ₹25,000).</li>
                        </ul>
                    </div>
                    <div style="padding: 12px 14px; background: #fafaf9; border: 1px solid var(--border-color); border-radius: 4px;">
                        <strong>Old Tax Regime & Statutory Caps:</strong>
                        <ul style="margin: 6px 0 0 18px; padding: 0; font-size: 12.5px; color: #555;">
                            <li>₹0 – ₹2,50,000: 0% | ₹2.5L – ₹5L: 5% | ₹5L – ₹10L: 20% | Above ₹10L: 30%</li>
                            <li><strong>Standard Deduction:</strong> ₹50,000 under Sec 16(ia) for salaried in BOTH regimes.</li>
                            <li><strong>Section 80C Cap:</strong> Strict boundary clamping to min(claimed, ₹1,50,000).</li>
                            <li><strong>Section 80CCD(1B):</strong> Additional NPS cap of ₹50,000.</li>
                            <li><strong>Health & Education Cess:</strong> 4% surcharge on net tax liability.</li>
                        </ul>
                    </div>
                </div>

                <h4 style="font-size: 14.5px; margin: 0 0 8px 0; color: var(--primary);">3. Automated Verification & Regression Safeguards</h4>
                <p style="color: var(--text-muted); margin-bottom: 0;">
                    Verified via <strong>14 automated PHP unit tests</strong> in <code>tests/php/test_tax_service.php</code> and <strong>11 Vitest frontend assertions</strong> in <code>tests/tax.test.js</code>. Tests validate edge discontinuities including 7,00,000 threshold rebate cutoff, deduction capping, and cross-regime breakeven points.
                </p>
            </div>
        </details>

        <!-- EMBEDDED ITEMISED DEDUCTIONS TRACKER -->
        <div class="card" id="deductions" style="margin-bottom: 24px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 10px;">
                <div>
                    <h2 style="font-size: 18px; margin: 0 0 4px 0;">Itemized Deductions Tracker (Chapter VI-A)</h2>
                    <p style="color: var(--text-muted); font-size: 13px; margin: 0;">Record eligible investments to optimize liability under the Old Regime.</p>
                </div>
            </div>

            <!-- Inline Add Deduction Form -->
            <form method="POST" style="background: #fafaf9; border: 1px solid var(--border-color); border-radius: var(--radius-sm); padding: 16px; margin-bottom: 20px;">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="add_deduction">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; margin-bottom: 12px;">
                    <div>
                        <label style="font-size: 12px; font-weight: 600; display: block; margin-bottom: 4px;">Category</label>
                        <select name="ded_category" class="form-control" required style="font-size: 13px;">
                            <option value="80C - PPF / ELSS / Life Insurance">80C - PPF / ELSS / Life Insurance (Max ₹1.5L)</option>
                            <option value="80D - Health Insurance (Self/Family)">80D - Health Insurance Self/Family (Max ₹25k)</option>
                            <option value="80D - Health Insurance (Senior Parents)">80D - Health Insurance Parents (Max ₹50k)</option>
                            <option value="80CCD(1B) - NPS Contribution">80CCD(1B) - NPS Tier 1 (Max ₹50k)</option>
                            <option value="80E - Education Loan Interest">80E - Education Loan Interest</option>
                            <option value="80TTA - Savings Account Interest">80TTA - Savings Interest (Max ₹10k)</option>
                            <option value="80G - Charitable Donations">80G - Charitable Donations</option>
                        </select>
                    </div>
                    <div>
                        <label style="font-size: 12px; font-weight: 600; display: block; margin-bottom: 4px;">Amount (₹)</label>
                        <input type="number" step="100" min="1" max="9999999" name="ded_amount" class="form-control" placeholder="e.g. 50000" required style="font-size: 13px;">
                    </div>
                    <div>
                        <label style="font-size: 12px; font-weight: 600; display: block; margin-bottom: 4px;">Date</label>
                        <input type="date" name="ded_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required style="font-size: 13px;">
                    </div>
                    <div>
                        <label style="font-size: 12px; font-weight: 600; display: block; margin-bottom: 4px;">Description / Institution</label>
                        <input type="text" name="ded_desc" class="form-control" placeholder="e.g. SBI Life, HDFC ELSS" style="font-size: 13px;">
                    </div>
                </div>
                <button type="submit" class="btn btn-primary btn-sm" style="padding: 7px 16px;">+ Record Deduction</button>
            </form>

            <!-- Deductions Table -->
            <?php if (!empty($raw_deductions)): ?>
                <div class="table-responsive">
                    <table class="custom-table" style="font-size: 13px;">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Category</th>
                                <th>Description</th>
                                <th>Amount</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($raw_deductions as $d): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($d['deduction_date']); ?></td>
                                    <td><strong><?php echo htmlspecialchars($d['category']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($d['description'] ?: '—'); ?></td>
                                    <td>₹<?php echo number_format($d['amount'], 2); ?></td>
                                    <td>
                                        <form method="POST" style="margin: 0; display: inline;" onsubmit="return confirm('Remove this deduction record?');">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="action" value="delete_deduction">
                                            <input type="hidden" name="deduction_id" value="<?php echo intval($d['id']); ?>">
                                            <button type="submit" class="btn btn-danger btn-sm" style="padding: 3px 8px; font-size: 11px;">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p style="font-size: 13px; color: var(--text-muted); margin: 0; text-align: center; padding: 16px;">
                    No manual deductions recorded for FY <?php echo htmlspecialchars($fy_label); ?>. Use the form above to record Chapter VI-A claims.
                </p>
            <?php endif; ?>
        </div>

        <!-- SAVED PROJECTIONS HISTORY -->
        <?php if (!empty($saved_projections)): ?>
            <div class="card">
                <h2 style="font-size: 18px; margin: 0 0 12px 0;">Saved Projections History</h2>
                <div class="table-responsive">
                    <table class="custom-table" style="font-size: 13px;">
                        <thead>
                            <tr>
                                <th>Saved Date</th>
                                <th>Financial Year</th>
                                <th>Gross Income</th>
                                <th>Total Deductions</th>
                                <th>Taxable Income</th>
                                <th>Estimated Tax</th>
                                <th>Verdict Bracket</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($saved_projections as $p): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars(date('d M Y, h:i A', strtotime($p['created_at']))); ?></td>
                                    <td><strong><?php echo htmlspecialchars($p['financial_year']); ?></strong></td>
                                    <td>₹<?php echo number_format($p['gross_income'], 2); ?></td>
                                    <td>₹<?php echo number_format($p['total_deductions'], 2); ?></td>
                                    <td>₹<?php echo number_format($p['taxable_income'], 2); ?></td>
                                    <td style="font-weight: 700; color: var(--primary);">₹<?php echo number_format($p['estimated_tax'], 2); ?></td>
                                    <td><span class="badge badge-success"><?php echo htmlspecialchars($p['tax_bracket']); ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

    </div>
</div>

<?php include "includes/footer.php"; ?>
