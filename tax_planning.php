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

// Compute statutory capped deductions safely
$caps = applyStatutoryCaps($raw_deductions);
$claimed_80c = (float)($caps['claimed_80c'] ?? 0);
$claimed_80d = (float)($caps['claimed_80d'] ?? 0);
$eligible_80d = (float)($caps['eligible_80d'] ?? 0);
$total_claimed_ded = (float)($caps['total_claimed'] ?? 0);
$total_eligible_ded = (float)($caps['total_eligible'] ?? 0);

$combined_80c = $claimed_80c + $portfolio_80c;
$eligible_combined_80c = min($combined_80c, LIMIT_SECTION_80C);
$other_eligible_ded = max(0.0, $total_eligible_ded - ($caps['eligible_80c'] ?? 0) - $eligible_80d);
$current_eligible_deductions = $eligible_combined_80c + $eligible_80d + $other_eligible_ded;
$current_claimed_deductions = $combined_80c + $claimed_80d + max(0.0, $total_claimed_ded - $claimed_80c - $claimed_80d);

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
    $input_80d   = isset($_POST['deduction_80d']) ? max(0.0, floatval($_POST['deduction_80d'])) : $claimed_80d;
    $input_other = isset($_POST['deduction_other']) ? max(0.0, floatval($_POST['deduction_other'])) : $other_eligible_ded;
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
        'has_actual_income' => true,
        'is_simulated'   => true
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
            $save_success = "Tax projection for " . htmlspecialchars($fy_label) . " saved to history!";
        } else {
            $calc_error = "Failed to save projection: " . $conn->error;
        }
    }
} else {
    // Initial load: reflect actual user ledger inflow and portfolio assets
    $auto_gross = $current_income;
    $auto_80c = $combined_80c;
    $auto_80d = $claimed_80d;
    $auto_other = $other_eligible_ded;
    $auto_elig_80c = $eligible_combined_80c;
    $auto_elig_80d = $eligible_80d;
    $auto_tot_ded = $current_eligible_deductions;

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
        'has_actual_income' => ($current_income > 0),
        'is_simulated'   => false
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
    <div style="max-width: 1100px; margin: 0 auto; padding: 12px 16px;">
        
        <!-- Header & FY Switcher -->
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 12px;">
            <div>
                <div style="display: flex; align-items: center; gap: 10px;">
                    <h1 style="font-size: 24px; margin: 0; font-weight: 700;">Smart Tax Optimizer</h1>
                    <span style="background: rgba(212,175,55,0.15); color: var(--primary); border: 1px solid rgba(212,175,55,0.3); padding: 3px 10px; border-radius: 12px; font-size: 11.5px; font-weight: 700;"><?php echo htmlspecialchars($fy_label); ?></span>
                </div>
                <p style="color: var(--text-muted); font-size: 13.5px; margin: 4px 0 0 0;">
                    Instant New vs. Old Tax Regime comparison • Section 87A rebate & 80C optimization
                </p>
            </div>
            <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                <form method="GET" action="tax_planning.php" style="margin: 0; display: inline-flex; align-items: center; gap: 6px;">
                    <select name="fy" id="fy" onchange="this.form.submit()" style="padding: 6px 10px; border-radius: 6px; border: 1px solid var(--border-color); font-size: 12.5px; background: #fff; font-weight: 600; color: var(--primary);">
                        <?php foreach ($available_fys as $f): ?>
                            <option value="<?php echo htmlspecialchars($f['fy_code']); ?>" <?php echo ($f['fy_code'] === $active_fy['fy_code']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($f['fy_label']); ?><?php echo $f['is_current'] ? ' • Current' : ''; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
                <a href="tax_planning.php?fy=<?php echo urlencode($active_fy['fy_code']); ?>" class="btn btn-secondary btn-sm" style="font-size: 12px;">↺ Auto-Sync</a>
            </div>
        </div>

        <?php if (!empty($message)): ?>
            <div class="alert alert-success" style="margin-bottom: 16px;">&#10003; <?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <?php if (!empty($save_success)): ?>
            <div class="alert alert-success" style="margin-bottom: 16px;">&#10003; <?php echo htmlspecialchars($save_success); ?></div>
        <?php endif; ?>
        <?php if (!empty($error) || !empty($calc_error)): ?>
            <div class="alert alert-danger" style="margin-bottom: 16px;">&#9888; <?php echo htmlspecialchars($error ?: $calc_error); ?></div>
        <?php endif; ?>

        <!-- HERO VERDICT CARD -->
        <?php 
            $has_inc = $calc_result['has_actual_income'];
            $is_new = ($calc_result['better_regime'] === 'new');
            $diff = $calc_result['tax_difference'];
        ?>

        <?php if (!$has_inc && !$calc_result['is_simulated']): ?>
            <!-- Clean Zero Income State with 1-Click Salary Scenario Presets -->
            <div style="background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); border: 1.5px solid #cbd5e1; border-radius: var(--radius-md); padding: 22px 24px; margin-bottom: 24px;">
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px;">
                    <div>
                        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px;">
                            <span style="font-size: 20px;">✨</span>
                            <h2 style="font-size: 18px; font-weight: 700; color: #1e293b; margin: 0;">Zero Tax Liability: ₹0.00 Inflow Recorded</h2>
                        </div>
                        <p style="font-size: 13.5px; color: #475569; margin: 0;">
                            No income transactions have been logged for <strong><?php echo htmlspecialchars($fy_label); ?></strong>. Your net tax liability is <strong>₹0.00</strong>.
                        </p>
                    </div>
                    <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                        <span style="font-size: 12px; font-weight: 700; color: #64748b; text-transform: uppercase;">Test Salary:</span>
                        <button type="button" class="btn btn-sm" style="background: #fff; border: 1px solid #cbd5e1; font-size: 12px; font-weight: 600;" onclick="testSalary(750000)">💼 ₹7.5L</button>
                        <button type="button" class="btn btn-sm" style="background: #fff; border: 1px solid #cbd5e1; font-size: 12px; font-weight: 600;" onclick="testSalary(1000000)">💼 ₹10L</button>
                        <button type="button" class="btn btn-sm" style="background: #fff; border: 1px solid #cbd5e1; font-size: 12px; font-weight: 600;" onclick="testSalary(1200000)">💼 ₹12L</button>
                        <button type="button" class="btn btn-sm" style="background: #fff; border: 1px solid #cbd5e1; font-size: 12px; font-weight: 600;" onclick="testSalary(1500000)">💼 ₹15L</button>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <!-- Highlighted Winner Card -->
            <div style="background: <?php echo $is_new ? 'linear-gradient(135deg, #f0fdf4 0%, #e6f7ec 100%)' : 'linear-gradient(135deg, #fefce8 0%, #fef9c3 100%)'; ?>; border: 1.5px solid <?php echo $is_new ? '#86efac' : '#fde047'; ?>; border-radius: var(--radius-md); padding: 20px 24px; margin-bottom: 24px; box-shadow: 0 2px 8px rgba(0,0,0,0.03);">
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px;">
                    <div>
                        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px;">
                            <span style="font-size: 20px;">🏆</span>
                            <h2 style="font-size: 18.5px; font-weight: 800; color: <?php echo $is_new ? '#15803d' : '#854d0e'; ?>; margin: 0;">
                                <?php if ($diff == 0): ?>
                                    Zero Net Tax in Both Regimes (Sec 87A Full Rebate)!
                                <?php elseif ($is_new): ?>
                                    Recommended: New Tax Regime (Section 115BAC)
                                <?php else: ?>
                                    Recommended: Old Tax Regime (With Deductions)
                                <?php endif; ?>
                            </h2>
                        </div>
                        <p style="font-size: 13.5px; color: <?php echo $is_new ? '#166534' : '#713f12'; ?>; margin: 0;">
                            <?php if ($diff == 0): ?>
                                Section 87A rebate wipes out 100% of your tax up to ₹7,00,000 taxable income.
                            <?php elseif ($is_new): ?>
                                The <strong>New Tax Regime</strong> saves you <strong>₹<?php echo number_format($diff, 2); ?></strong> in annual taxes over the Old Regime.
                            <?php else: ?>
                                Your allowable Chapter VI-A deductions save you <strong>₹<?php echo number_format($diff, 2); ?></strong> compared to the New Regime.
                            <?php endif; ?>
                            <?php if (!$calc_result['is_simulated']): ?>
                                <span style="background: rgba(0,0,0,0.06); padding: 1px 6px; border-radius: 8px; font-size: 11px; font-weight: 600; margin-left: 6px;">⚡ Auto-Synced from Ledger</span>
                            <?php else: ?>
                                <span style="background: rgba(0,0,0,0.06); padding: 1px 6px; border-radius: 8px; font-size: 11px; font-weight: 600; margin-left: 6px;">Simulated Scenario</span>
                            <?php endif; ?>
                        </p>
                    </div>
                    <div style="display: flex; gap: 12px; align-items: center;">
                        <button type="button" class="btn btn-sm" style="background: #fff; border: 1px solid rgba(0,0,0,0.1); font-size: 12px;" onclick="testSalary(1000000)">Test ₹10L</button>
                        <button type="button" class="btn btn-sm" style="background: #fff; border: 1px solid rgba(0,0,0,0.1); font-size: 12px;" onclick="testSalary(1500000)">Test ₹15L</button>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- SIDE-BY-SIDE REGIME COMPARISON CARDS -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 18px; margin-bottom: 24px;">
            
            <!-- NEW REGIME CARD -->
            <div style="background: #ffffff; border: 2px solid <?php echo $is_new ? '#22c55e' : 'var(--border-color)'; ?>; border-radius: var(--radius-md); padding: 20px; box-shadow: 0 2px 6px rgba(0,0,0,0.03); position: relative;">
                <?php if ($is_new): ?>
                    <span style="position: absolute; top: -11px; right: 18px; background: #15803d; color: #fff; font-size: 11px; font-weight: 700; padding: 2px 10px; border-radius: 12px; text-transform: uppercase;">★ Recommended</span>
                <?php endif; ?>
                <div style="display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 12px;">
                    <h3 style="font-size: 16px; margin: 0; font-weight: 700; color: var(--primary);">New Tax Regime</h3>
                    <span style="font-size: 12px; color: var(--text-muted);">Section 115BAC Default</span>
                </div>
                <div style="font-size: 26px; font-weight: 800; color: <?php echo $is_new ? '#15803d' : 'var(--text-dark)'; ?>; margin-bottom: 4px;">
                    ₹<?php echo number_format($calc_result['new']['total_tax'], 2); ?>
                </div>
                <div style="font-size: 12px; color: var(--text-muted); margin-bottom: 16px;">
                    Effective Rate: <strong><?php echo number_format($calc_result['new']['effective_rate'], 1); ?>%</strong>
                </div>

                <div style="border-top: 1px solid var(--border-color); padding-top: 12px; font-size: 13px; display: grid; gap: 8px;">
                    <div style="display: flex; justify-content: space-between;">
                        <span style="color: var(--text-muted);">Gross Income:</span>
                        <strong>₹<?php echo number_format($calc_result['new']['gross_income'], 2); ?></strong>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span style="color: var(--text-muted);">Standard Deduction (Sec 16):</span>
                        <span style="color: #15803d; font-weight: 600;">-₹<?php echo number_format($calc_result['new']['standard_deduction'], 2); ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span style="color: var(--text-muted);">Chapter VI-A Deductions:</span>
                        <span style="color: #94a3b8; font-size: 12px;">Disallowed by law</span>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span style="color: var(--text-muted);">Taxable Income:</span>
                        <strong>₹<?php echo number_format($calc_result['new']['taxable_income'], 2); ?></strong>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span style="color: var(--text-muted);">Section 87A Rebate:</span>
                        <span><?php echo $calc_result['new']['rebate_87a'] > 0 ? '<strong style="color: #15803d;">-₹' . number_format($calc_result['new']['rebate_87a'], 2) . ' (100% Relief)</strong>' : '₹0.00'; ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span style="color: var(--text-muted);">Health & Education Cess (4%):</span>
                        <span>₹<?php echo number_format($calc_result['new']['cess'], 2); ?></span>
                    </div>
                </div>
            </div>

            <!-- OLD REGIME CARD -->
            <div style="background: #ffffff; border: 2px solid <?php echo !$is_new ? '#22c55e' : 'var(--border-color)'; ?>; border-radius: var(--radius-md); padding: 20px; box-shadow: 0 2px 6px rgba(0,0,0,0.03); position: relative;">
                <?php if (!$is_new): ?>
                    <span style="position: absolute; top: -11px; right: 18px; background: #15803d; color: #fff; font-size: 11px; font-weight: 700; padding: 2px 10px; border-radius: 12px; text-transform: uppercase;">★ Recommended</span>
                <?php endif; ?>
                <div style="display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 12px;">
                    <h3 style="font-size: 16px; margin: 0; font-weight: 700; color: var(--primary);">Old Tax Regime</h3>
                    <span style="font-size: 12px; color: var(--text-muted);">With Itemized Deductions</span>
                </div>
                <div style="font-size: 26px; font-weight: 800; color: <?php echo !$is_new ? '#15803d' : 'var(--text-dark)'; ?>; margin-bottom: 4px;">
                    ₹<?php echo number_format($calc_result['old']['total_tax'], 2); ?>
                </div>
                <div style="font-size: 12px; color: var(--text-muted); margin-bottom: 16px;">
                    Effective Rate: <strong><?php echo number_format($calc_result['old']['effective_rate'], 1); ?>%</strong>
                </div>

                <div style="border-top: 1px solid var(--border-color); padding-top: 12px; font-size: 13px; display: grid; gap: 8px;">
                    <div style="display: flex; justify-content: space-between;">
                        <span style="color: var(--text-muted);">Gross Income:</span>
                        <strong>₹<?php echo number_format($calc_result['old']['gross_income'], 2); ?></strong>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span style="color: var(--text-muted);">Standard Deduction (Sec 16):</span>
                        <span style="color: #15803d; font-weight: 600;">-₹<?php echo number_format($calc_result['old']['standard_deduction'], 2); ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span style="color: var(--text-muted);">Allowable Deductions (80C/80D):</span>
                        <span style="color: #15803d; font-weight: 600;">-₹<?php echo number_format($calc_result['deductions'], 2); ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span style="color: var(--text-muted);">Taxable Income:</span>
                        <strong>₹<?php echo number_format($calc_result['old']['taxable_income'], 2); ?></strong>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span style="color: var(--text-muted);">Section 87A Rebate:</span>
                        <span><?php echo $calc_result['old']['rebate_87a'] > 0 ? '<strong style="color: #15803d;">-₹' . number_format($calc_result['old']['rebate_87a'], 2) . ' (100% Relief)</strong>' : '₹0.00'; ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span style="color: var(--text-muted);">Health & Education Cess (4%):</span>
                        <span>₹<?php echo number_format($calc_result['old']['cess'], 2); ?></span>
                    </div>
                </div>
            </div>

        </div>

        <!-- 1-ROW FAST SCENARIO SIMULATOR -->
        <div class="card" style="margin-bottom: 24px; padding: 18px 22px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; flex-wrap: wrap; gap: 8px;">
                <h3 style="font-size: 15px; margin: 0; font-weight: 700;">Simulate Salary & Deductions</h3>
                <span style="font-size: 12px; color: var(--text-muted);">Instant Recalculation</span>
            </div>
            <form method="POST" id="taxSimForm" data-turbo="false">
                <?php echo csrf_field(); ?>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; margin-bottom: 12px;">
                    <div>
                        <label style="font-size: 12px; font-weight: 600; display: block; margin-bottom: 4px;">Annual Salary / Income (₹)</label>
                        <input type="number" step="1000" min="0" id="gross_income" name="gross_income" class="form-control" value="<?php echo htmlspecialchars($calc_result['gross_income'] > 0 ? $calc_result['gross_income'] : 850000); ?>" required style="font-size: 13.5px; font-weight: 600;">
                    </div>
                    <div>
                        <label style="font-size: 12px; font-weight: 600; display: block; margin-bottom: 4px;">Section 80C Assets (Max ₹1.5L)</label>
                        <input type="number" step="1000" min="0" max="150000" id="deduction_80c" name="deduction_80c" class="form-control" value="<?php echo htmlspecialchars($calc_result['input_80c']); ?>" style="font-size: 13.5px;">
                    </div>
                    <div>
                        <label style="font-size: 12px; font-weight: 600; display: block; margin-bottom: 4px;">Section 80D Health Cover (Max ₹50k)</label>
                        <input type="number" step="1000" min="0" max="100000" id="deduction_80d" name="deduction_80d" class="form-control" value="<?php echo htmlspecialchars($calc_result['input_80d']); ?>" style="font-size: 13.5px;">
                    </div>
                    <div>
                        <label style="font-size: 12px; font-weight: 600; display: block; margin-bottom: 4px;">Other (NPS 80CCD, 80E)</label>
                        <input type="number" step="1000" min="0" name="deduction_other" class="form-control" value="<?php echo htmlspecialchars($calc_result['input_other']); ?>" style="font-size: 13.5px;">
                    </div>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                    <label style="display: inline-flex; align-items: center; gap: 6px; cursor: pointer; font-size: 12.5px;">
                        <input type="checkbox" name="is_salaried" value="1" <?php echo $calc_result['is_salaried'] ? 'checked' : ''; ?>>
                        <span>Salaried (Entitled to ₹50,000 standard deduction)</span>
                    </label>
                    <div style="display: flex; gap: 8px;">
                        <button type="submit" name="action" value="calculate" class="btn btn-primary btn-sm" style="padding: 7px 16px;">⚡ Recalculate</button>
                        <button type="submit" name="action" value="save_projection" class="btn btn-gold btn-sm" style="padding: 7px 16px;">💾 Save to History</button>
                    </div>
                </div>
            </form>
        </div>

        <!-- MODULE 1: ITEMIZED DEDUCTIONS TRACKER (COLLAPSIBLE) -->
        <details id="deductions" style="background: #ffffff; border: 1px solid var(--border-color); border-radius: var(--radius-sm); padding: 14px 18px; margin-bottom: 16px;">
            <summary style="font-size: 14.5px; font-weight: 700; cursor: pointer; color: var(--primary); display: flex; justify-content: space-between; align-items: center;">
                <span>📋 Itemized Deductions Tracker (Chapter VI-A) — <?php echo count($raw_deductions); ?> Claimed</span>
                <span style="font-size: 12px; color: var(--text-muted); font-weight: normal;">Expand / Collapse</span>
            </summary>
            
            <div style="margin-top: 14px; border-top: 1px solid var(--border-color); padding-top: 14px;">
                <!-- Add Deduction Form -->
                <form method="POST" style="background: #fafaf9; border: 1px solid var(--border-color); border-radius: var(--radius-sm); padding: 14px; margin-bottom: 16px;">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="add_deduction">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 10px; margin-bottom: 10px;">
                        <div>
                            <label style="font-size: 11.5px; font-weight: 600; display: block; margin-bottom: 3px;">Category</label>
                            <select name="ded_category" class="form-control" required style="font-size: 12.5px;">
                                <option value="80C - PPF / ELSS / Life Insurance">80C - PPF / ELSS / LIC (Max ₹1.5L)</option>
                                <option value="80D - Health Insurance (Self/Family)">80D - Health Self/Family (Max ₹25k)</option>
                                <option value="80D - Health Insurance (Senior Parents)">80D - Health Parents (Max ₹50k)</option>
                                <option value="80CCD(1B) - NPS Contribution">80CCD(1B) - NPS (Max ₹50k)</option>
                                <option value="80E - Education Loan Interest">80E - Education Loan</option>
                                <option value="80TTA - Savings Account Interest">80TTA - Savings Interest (Max ₹10k)</option>
                            </select>
                        </div>
                        <div>
                            <label style="font-size: 11.5px; font-weight: 600; display: block; margin-bottom: 3px;">Amount (₹)</label>
                            <input type="number" step="100" min="1" max="9999999" name="ded_amount" class="form-control" placeholder="e.g. 50000" required style="font-size: 12.5px;">
                        </div>
                        <div>
                            <label style="font-size: 11.5px; font-weight: 600; display: block; margin-bottom: 3px;">Date</label>
                            <input type="date" name="ded_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required style="font-size: 12.5px;">
                        </div>
                        <div>
                            <label style="font-size: 11.5px; font-weight: 600; display: block; margin-bottom: 3px;">Description</label>
                            <input type="text" name="ded_desc" class="form-control" placeholder="e.g. HDFC ELSS, LIC" style="font-size: 12.5px;">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm" style="padding: 6px 14px; font-size: 12px;">+ Record Deduction</button>
                </form>

                <?php if (!empty($raw_deductions)): ?>
                    <div class="table-responsive">
                        <table class="custom-table" style="font-size: 12.5px;">
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
                                            <form method="POST" style="margin: 0; display: inline;" onsubmit="return confirm('Remove this deduction?');">
                                                <?php echo csrf_field(); ?>
                                                <input type="hidden" name="action" value="delete_deduction">
                                                <input type="hidden" name="deduction_id" value="<?php echo intval($d['id']); ?>">
                                                <button type="submit" class="btn btn-danger btn-sm" style="padding: 2px 7px; font-size: 11px;">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p style="font-size: 12.5px; color: var(--text-muted); margin: 0; text-align: center; padding: 10px;">
                        No deductions recorded for this period yet.
                    </p>
                <?php endif; ?>
            </div>
        </details>

        <!-- MODULE 2: SAVED PROJECTIONS HISTORY (COLLAPSIBLE) -->
        <?php if (!empty($saved_projections)): ?>
            <details style="background: #ffffff; border: 1px solid var(--border-color); border-radius: var(--radius-sm); padding: 14px 18px; margin-bottom: 16px;">
                <summary style="font-size: 14.5px; font-weight: 700; cursor: pointer; color: var(--primary); display: flex; justify-content: space-between; align-items: center;">
                    <span>📜 Saved Projections History (<?php echo count($saved_projections); ?> Entries)</span>
                    <span style="font-size: 12px; color: var(--text-muted); font-weight: normal;">Expand / Collapse</span>
                </summary>
                <div style="margin-top: 14px; border-top: 1px solid var(--border-color); padding-top: 14px;">
                    <div class="table-responsive">
                        <table class="custom-table" style="font-size: 12.5px;">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Period</th>
                                    <th>Gross Income</th>
                                    <th>Taxable Income</th>
                                    <th>Estimated Tax</th>
                                    <th>Recommended Strategy</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($saved_projections as $p): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars(date('d M Y', strtotime($p['created_at']))); ?></td>
                                        <td><strong><?php echo htmlspecialchars($p['financial_year']); ?></strong></td>
                                        <td>₹<?php echo number_format($p['gross_income'], 2); ?></td>
                                        <td>₹<?php echo number_format($p['taxable_income'], 2); ?></td>
                                        <td style="font-weight: 700; color: var(--primary);">₹<?php echo number_format($p['estimated_tax'], 2); ?></td>
                                        <td><span class="badge badge-success"><?php echo htmlspecialchars($p['tax_bracket']); ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </details>
        <?php endif; ?>

        <!-- MODULE 3: TECHNICAL ARCHITECTURE & SLABS (COLLAPSIBLE FOR INTERVIEWERS) -->
        <details style="background: #ffffff; border: 1px solid var(--border-color); border-radius: var(--radius-sm); padding: 14px 18px; margin-bottom: 24px;">
            <summary style="font-size: 14.5px; font-weight: 700; cursor: pointer; color: var(--primary); display: flex; justify-content: space-between; align-items: center;">
                <span>🔍 Technical Deep-Dive: Finance Act Engine & Mathematical Architecture</span>
                <span style="font-size: 12px; color: var(--text-muted); font-weight: normal;">Inspect for Engineering Review</span>
            </summary>
            
            <div style="margin-top: 14px; font-size: 13px; line-height: 1.6; color: var(--text-dark); border-top: 1px solid var(--border-color); padding-top: 14px;">
                <h4 style="font-size: 14px; margin: 0 0 6px 0; color: var(--primary);">1. Domain-Driven Design & Stateless Computation</h4>
                <p style="color: var(--text-muted); margin-bottom: 12px;">
                    Implemented inside <code>services/TaxService.php</code> (namespace <code>FinCart\Services\TaxService</code>). All calculations are implemented as side-effect free, deterministic pure functions (<code>computeNewRegime()</code>, <code>computeOldRegime()</code>, <code>applyStatutoryCaps()</code>) completely decoupled from database queries or HTTP state.
                </p>

                <h4 style="font-size: 14px; margin: 0 0 6px 0; color: var(--primary);">2. Statutory Finance Act (FY 2024-25 / AY 2025-26) Rules</h4>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 12px; margin-bottom: 12px;">
                    <div style="padding: 10px 12px; background: #fafaf9; border: 1px solid var(--border-color); border-radius: 4px;">
                        <strong>Default New Regime (Section 115BAC):</strong>
                        <ul style="margin: 4px 0 0 16px; padding: 0; font-size: 12px; color: #555;">
                            <li>₹0 – ₹3L: Nil | ₹3L – ₹6L: 5% | ₹6L – ₹9L: 10%</li>
                            <li>₹9L – ₹12L: 15% | ₹12L – ₹15L: 20% | Above ₹15L: 30%</li>
                            <li><strong>Section 87A Rebate:</strong> 100% rebate up to ₹7,00,000 taxable income.</li>
                        </ul>
                    </div>
                    <div style="padding: 10px 12px; background: #fafaf9; border: 1px solid var(--border-color); border-radius: 4px;">
                        <strong>Old Tax Regime:</strong>
                        <ul style="margin: 4px 0 0 16px; padding: 0; font-size: 12px; color: #555;">
                            <li>₹0 – ₹2.5L: Nil | ₹2.5L – ₹5L: 5% | ₹5L – ₹10L: 20% | >₹10L: 30%</li>
                            <li><strong>Standard Deduction:</strong> ₹50,000 for salaried in BOTH regimes.</li>
                            <li><strong>Section 80C Limit:</strong> Clamped strictly to min(claimed, ₹1.5L).</li>
                            <li><strong>Health & Education Cess:</strong> Flat 4% on net tax.</li>
                        </ul>
                    </div>
                </div>

                <h4 style="font-size: 14px; margin: 0 0 6px 0; color: var(--primary);">3. Automated Verification</h4>
                <p style="color: var(--text-muted); margin-bottom: 0;">
                    Verified via <strong>14 automated PHP unit tests</strong> (<code>tests/php/test_tax_service.php</code>) and <strong>11 Vitest frontend assertions</strong> (<code>tests/tax.test.js</code>).
                </p>
            </div>
        </details>

    </div>
</div>

<script>
function testSalary(amount) {
    const input = document.getElementById('gross_income');
    const form = document.getElementById('taxSimForm');
    if (input && form) {
        input.value = amount;
        form.submit();
    }
}
</script>

<?php include "includes/footer.php"; ?>
