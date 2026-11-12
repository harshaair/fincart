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

// Get income within Indian Financial Year
$income_sql = "SELECT SUM(amount) as total_income FROM transactions WHERE user_id = ? AND type = 'income' AND date BETWEEN ? AND ?";
$income_stmt = $conn->prepare($income_sql);
$income_stmt->bind_param("iss", $user_id, $start_date, $end_date);
$income_stmt->execute();
$income_result = $income_stmt->get_result();
$income_row = $income_result->fetch_assoc();
$current_income = (float)($income_row['total_income'] ?? 0);

// Get deductions within Indian Financial Year
$deduction_sql = "SELECT category, amount FROM tax_deductions WHERE user_id = ? AND deduction_date BETWEEN ? AND ?";
$deduction_stmt = $conn->prepare($deduction_sql);
$deduction_stmt->bind_param("iss", $user_id, $start_date, $end_date);
$deduction_stmt->execute();
$deduction_result = $deduction_stmt->get_result();
$raw_deductions = [];
while ($row = $deduction_result->fetch_assoc()) {
    $raw_deductions[] = $row;
}

// Compute statutory capped deductions
$caps = applyStatutoryCaps($raw_deductions);
$current_eligible_deductions = $caps['total_eligible'];
$current_claimed_deductions = $caps['total_claimed'];

// Live tax estimates for current transactions
$current_new_regime = computeTaxNewRegime($current_income, true);
$current_old_regime = computeTaxOldRegime($current_income, $current_eligible_deductions, true);

// Interactive comparison calculator
$calc_result = null;
$calc_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $gross_income_input = isset($_POST['gross_income']) ? max(0.0, floatval($_POST['gross_income'])) : $current_income;
    $input_80c   = isset($_POST['deduction_80c']) ? max(0.0, floatval($_POST['deduction_80c'])) : $caps['claimed_80c'];
    $input_80d   = isset($_POST['deduction_80d']) ? max(0.0, floatval($_POST['deduction_80d'])) : $caps['claimed_80d'];
    $input_other = isset($_POST['deduction_other']) ? max(0.0, floatval($_POST['deduction_other'])) : max(0.0, $caps['total_claimed'] - $caps['claimed_80c'] - $caps['claimed_80d']);
    $is_salaried = isset($_POST['is_salaried']) ? (bool)$_POST['is_salaried'] : true;

    // Apply statutory limits strictly
    $eligible_80c = min($input_80c, LIMIT_SECTION_80C);
    $eligible_80d = min($input_80d, LIMIT_SECTION_80D_PARENTS);
    $eligible_other = $input_other;
    $total_calc_deductions = $eligible_80c + $eligible_80d + $eligible_other;

    if ($gross_income_input < 0) {
        $calc_error = 'Gross income must be a non-negative value.';
    } else {
        // Compute for both regimes
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
            'tax_difference' => $tax_difference
        ];
    }
}

$page_title = 'Tax Planning & Optimization - FinCart';
include "includes/header.php";
include "includes/navbar.php";
?>

<div class="main-content">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 15px;">
        <div>
            <h1 style="font-size: 28px; margin-bottom: 6px;">Indian Income Tax Planning Hub</h1>
            <p style="color: var(--text-muted);">Compare New vs. Old Tax Regimes with Section 87A rebate, standard deduction, and statutory caps.</p>
        </div>
        <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
            <!-- Indian Financial Year Switcher -->
            <form method="GET" action="tax_planning.php" style="margin: 0; display: inline-flex; align-items: center; gap: 8px;">
                <label for="fy" style="font-size: 13px; font-weight: 600; color: var(--text-dark); margin: 0;">Fiscal Year:</label>
                <select name="fy" id="fy" onchange="this.form.submit()" style="padding: 7px 12px; border-radius: 6px; border: 1px solid var(--border-light, #ddd); font-size: 13px; background: #fff; font-weight: 600; color: var(--primary);">
                    <?php foreach ($available_fys as $f): ?>
                        <option value="<?php echo htmlspecialchars($f['fy_code']); ?>" <?php echo ($f['fy_code'] === $active_fy['fy_code']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($f['fy_label'] . ' (' . $f['ay_label'] . ')'); ?><?php echo $f['is_current'] ? ' • Current' : ''; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
            <a href="tax_tracker.php?fy=<?php echo urlencode($active_fy['fy_code']); ?>" class="btn btn-secondary">Deductions Tracker &rarr;</a>
            <a href="tax_summary.php?fy=<?php echo urlencode($active_fy['fy_code']); ?>" class="btn btn-primary">Summary Report &rarr;</a>
            <a href="itr_preparation.php?fy=<?php echo urlencode($active_fy['fy_code']); ?>" class="btn btn-accent">ITR Assistant &rarr;</a>
        </div>
    </div>

    <!-- Active FY Notification Bar -->
    <div style="background: linear-gradient(135deg, rgba(212, 175, 55, 0.08), rgba(26, 26, 26, 0.03)); border-left: 4px solid #d4af37; padding: 12px 18px; border-radius: 6px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
        <div>
            <strong style="color: var(--primary); font-size: 14.5px;">Statutory Indian Financial Year: <?php echo htmlspecialchars($fy_label); ?></strong>
            <span style="display: inline-block; background: #d4af37; color: #fff; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 700; margin-left: 8px;"><?php echo htmlspecialchars($ay_label); ?></span>
            <div style="font-size: 12px; color: var(--text-muted); margin-top: 2px;">
                Assessment Period: <?php echo date('d M Y', strtotime($start_date)); ?> to <?php echo date('d M Y', strtotime($end_date)); ?>
            </div>
        </div>
        <div style="font-size: 12.5px; color: var(--text-dark);">
            Section 115BAC Default Regime active
        </div>
    </div>

    <!-- Live Current Year Tax Status Overview with Dedicated 80C & 80D Audit -->
    <div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 24px;">
        <div class="stat-card">
            <div class="stat-label">Inflow (<?php echo htmlspecialchars($fy_label); ?>)</div>
            <div class="stat-value">₹<?php echo number_format($current_income, 2); ?></div>
            <div class="stat-description">Total income across current FY</div>
        </div>
        <div class="stat-card" style="border-left: 4px solid var(--accent, #d4af37);">
            <div class="stat-label">Section 80C Deductions</div>
            <div class="stat-value" style="color: <?php echo $caps['claimed_80c'] > 0 ? '#111' : 'var(--text-muted)'; ?>;">
                ₹<?php echo number_format($caps['eligible_80c'], 2); ?>
            </div>
            <div class="stat-description">
                Claimed: ₹<?php echo number_format($caps['claimed_80c'], 0); ?> / Cap: ₹1.5L
                <?php if ($caps['claimed_80c'] == 0): ?>
                    <span style="color: #999;">(₹0 Claimed)</span>
                <?php endif; ?>
            </div>
        </div>
        <div class="stat-card" style="border-left: 4px solid #27ae60;">
            <div class="stat-label">Section 80D Health Cover</div>
            <div class="stat-value" style="color: <?php echo $caps['claimed_80d'] > 0 ? '#111' : 'var(--text-muted)'; ?>;">
                ₹<?php echo number_format($caps['eligible_80d'], 2); ?>
            </div>
            <div class="stat-description">
                Claimed: ₹<?php echo number_format($caps['claimed_80d'], 0); ?> / Cap: ₹25k-50k
                <?php if ($caps['claimed_80d'] == 0): ?>
                    <span style="color: #999;">(₹0 Claimed)</span>
                <?php endif; ?>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Total Allowable Deductions</div>
            <div class="stat-value">₹<?php echo number_format($current_eligible_deductions, 2); ?></div>
            <div class="stat-description">Old Regime (80C + 80D + others)</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">New Regime Tax (Sec 115BAC)</div>
            <div class="stat-value" style="color: <?php echo $current_new_regime['total_tax'] == 0 ? '#27ae60' : 'inherit'; ?>;">
                ₹<?php echo number_format($current_new_regime['total_tax'], 2); ?>
            </div>
            <div class="stat-description">
                <?php if ($current_new_regime['rebate_87a'] > 0): ?>
                    <span style="color: #27ae60; font-weight: 600;">Sec 87A Rebate Applied (₹0 Tax)</span>
                <?php else: ?>
                    Effective Rate: <?php echo number_format($current_new_regime['effective_rate'], 1); ?>%
                <?php endif; ?>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Old Regime Tax</div>
            <div class="stat-value" style="color: <?php echo $current_old_regime['total_tax'] == 0 ? '#27ae60' : 'inherit'; ?>;">
                ₹<?php echo number_format($current_old_regime['total_tax'], 2); ?>
            </div>
            <div class="stat-description">
                <?php if ($current_old_regime['rebate_87a'] > 0): ?>
                    <span style="color: #27ae60; font-weight: 600;">Sec 87A Rebate Applied (₹0 Tax)</span>
                <?php else: ?>
                    Effective Rate: <?php echo number_format($current_old_regime['effective_rate'], 1); ?>%
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Dual Regime Comparison Calculator -->
    <div class="card" style="margin-bottom: 25px;">
        <h2 style="font-size: 22px; margin-bottom: 8px;">Interactive Regime Comparison Calculator</h2>
        <p style="color: var(--text-muted); font-size: 14px; margin-bottom: 20px;">
            Simulate your gross income and itemized deductions (Section 80C, Section 80D, NPS) to determine whether the New or Old Regime minimizes your statutory liability.
        </p>

        <form method="POST" id="taxCompareForm" onsubmit="var btn=document.getElementById('taxCompareBtn'); if(btn.disabled) return false; btn.disabled=true; btn.innerHTML='⏳ Calculating Regimes...'; return true;" style="margin-bottom: 25px;">
            <?php echo csrf_field(); ?>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 18px; margin-bottom: 18px;">
                <div class="form-group">
                    <label style="font-weight: 600; font-size: 13.5px; margin-bottom: 6px; display: block;">Annual Gross Income (₹)</label>
                    <input type="number" step="1000" min="0" name="gross_income" class="form-control" value="<?php echo htmlspecialchars($calc_result ? $calc_result['gross_income'] : ($current_income > 0 ? $current_income : 800000)); ?>" required>
                    <small style="color: var(--text-muted); font-size: 11.5px;">Salary, freelancing, interest, etc.</small>
                </div>
                <div class="form-group">
                    <label style="font-weight: 600; font-size: 13.5px; margin-bottom: 6px; display: block;">Section 80C Investments (₹)</label>
                    <input type="number" step="1000" min="0" max="150000" name="deduction_80c" class="form-control" value="<?php echo htmlspecialchars($calc_result ? $calc_result['input_80c'] : $caps['claimed_80c']); ?>">
                    <small style="color: var(--text-muted); font-size: 11.5px;">EPF, PPF, ELSS, Life Ins. (Capped at ₹1.5L)</small>
                </div>
                <div class="form-group">
                    <label style="font-weight: 600; font-size: 13.5px; margin-bottom: 6px; display: block;">Section 80D Health Insurance (₹)</label>
                    <input type="number" step="1000" min="0" max="100000" name="deduction_80d" class="form-control" value="<?php echo htmlspecialchars($calc_result ? $calc_result['input_80d'] : $caps['claimed_80d']); ?>">
                    <small style="color: var(--text-muted); font-size: 11.5px;">Self/family (₹25k) + Senior parents (₹50k)</small>
                </div>
                <div class="form-group">
                    <label style="font-weight: 600; font-size: 13.5px; margin-bottom: 6px; display: block;">Other Deductions (80CCD, 80E) (₹)</label>
                    <input type="number" step="1000" min="0" name="deduction_other" class="form-control" value="<?php echo htmlspecialchars($calc_result ? $calc_result['input_other'] : max(0, $caps['total_claimed'] - $caps['claimed_80c'] - $caps['claimed_80d'])); ?>">
                    <small style="color: var(--text-muted); font-size: 11.5px;">NPS Tier-1 up to ₹50k, 80E loan interest</small>
                </div>
            </div>
            <div style="margin-bottom: 18px;">
                <label style="display: inline-flex; align-items: center; gap: 8px; cursor: pointer;">
                    <input type="checkbox" name="is_salaried" value="1" <?php echo (!isset($calc_result) || $calc_result['is_salaried']) ? 'checked' : ''; ?>>
                    <span style="font-size: 13.5px;"><strong>Salaried Individual</strong> (Entitled to ₹50,000 Standard Deduction under Sec 16(ia) in BOTH regimes)</span>
                </label>
            </div>
            <button type="submit" id="taxCompareBtn" class="btn btn-primary" style="padding: 11px 24px;">Calculate & Compare Regimes &rarr;</button>
        </form>

        <?php if (!empty($calc_error)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($calc_error); ?></div>
        <?php endif; ?>

        <?php if ($calc_result): ?>
            <div style="margin-top: 25px; border-top: 1px solid var(--border-color); padding-top: 25px;">
                <div class="alert alert-<?php echo $calc_result['better_regime'] === 'new' ? 'success' : 'info'; ?>" style="font-size: 15.5px; line-height: 1.6;">
                    <strong>Recommendation:</strong> 
                    <?php if ($calc_result['tax_difference'] == 0): ?>
                        Both regimes yield identical tax (₹0 liability due to Section 87A rebate or lower income slabs).
                    <?php else: ?>
                        The <strong><?php echo $calc_result['better_regime'] === 'new' ? 'New Tax Regime (Section 115BAC)' : 'Old Tax Regime'; ?></strong> saves you 
                        <strong>₹<?php echo number_format($calc_result['tax_difference'], 2); ?></strong> in annual taxes!
                    <?php endif; ?>
                    <div style="font-size: 13px; margin-top: 4px; color: #444;">
                        <?php if ($calc_result['better_regime'] === 'new'): ?>
                            The New Regime offers lower tax rates and an ₹7,00,000 threshold for Section 87A rebate, making it optimal because your eligible deductions (₹<?php echo number_format($calc_result['deductions'], 0); ?>) do not overcome the rate advantage.
                        <?php else: ?>
                            Your high itemized deductions under Section 80C (₹<?php echo number_format($calc_result['eligible_80c'], 0); ?>) and Section 80D (₹<?php echo number_format($calc_result['eligible_80d'], 0); ?>) reduce your taxable income enough to beat the New Regime.
                        <?php endif; ?>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="custom-table">
                        <thead>
                            <tr>
                                <th>Tax Component</th>
                                <th>New Regime (FY 2024-25 Default)</th>
                                <th>Old Regime</th>
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
                                <td><span style="color: #999; font-weight: 500;">❌ Not Permitted by Law (Sec 115BAC)</span></td>
                                <td>-₹<?php echo number_format($calc_result['eligible_80c'], 2); ?> <small style="color: #666;">(Claimed: ₹<?php echo number_format($calc_result['input_80c'], 2); ?>)</small></td>
                            </tr>
                            <tr>
                                <td>Section 80D Health Insurance (Cap ₹25k-50k)</td>
                                <td><span style="color: #999; font-weight: 500;">❌ Not Permitted by Law (Sec 115BAC)</span></td>
                                <td>-₹<?php echo number_format($calc_result['eligible_80d'], 2); ?> <small style="color: #666;">(Claimed: ₹<?php echo number_format($calc_result['input_80d'], 2); ?>)</small></td>
                            </tr>
                            <tr>
                                <td>Other Eligible Deductions (80CCD NPS, 80E, 80TTA)</td>
                                <td><span style="color: #999; font-weight: 500;">❌ Not Permitted by Law</span></td>
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
                                        <span style="color: #27ae60; font-weight: 600;">-₹<?php echo number_format($calc_result['new']['rebate_87a'], 2); ?> (100% Rebate up to ₹7L)</span>
                                    <?php else: ?>
                                        ₹0.00
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($calc_result['old']['rebate_87a'] > 0): ?>
                                        <span style="color: #27ae60; font-weight: 600;">-₹<?php echo number_format($calc_result['old']['rebate_87a'], 2); ?> (100% Rebate up to ₹5L)</span>
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
                            <tr style="background: var(--secondary); font-size: 16px; font-weight: 700;">
                                <td>Total Tax Payable</td>
                                <td style="color: <?php echo $calc_result['better_regime'] === 'new' ? '#27ae60' : 'inherit'; ?>;">
                                    ₹<?php echo number_format($calc_result['new']['total_tax'], 2); ?>
                                    <?php if ($calc_result['better_regime'] === 'new'): ?><span class="badge badge-success" style="margin-left: 6px;">Suggested</span><?php endif; ?>
                                </td>
                                <td style="color: <?php echo $calc_result['better_regime'] === 'old' ? '#27ae60' : 'inherit'; ?>;">
                                    ₹<?php echo number_format($calc_result['old']['total_tax'], 2); ?>
                                    <?php if ($calc_result['better_regime'] === 'old'): ?><span class="badge badge-success" style="margin-left: 6px;">Suggested</span><?php endif; ?>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Optimization Opportunities & Deduction Status Audit -->
    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 10px;">
            <h2 style="font-size: 20px; margin: 0;">Tax Optimization Opportunities & Deduction Audit</h2>
            <a href="tax_tracker.php?action=add" class="btn btn-primary btn-sm">+ Add Deduction in Tax Tracker</a>
        </div>
        <p style="color: var(--text-muted); font-size: 13.5px; margin-bottom: 20px;">
            Under Indian tax laws, deductions under Chapter VI-A are <strong>available exclusively under the Old Tax Regime</strong>. Under the default New Tax Regime (Section 115BAC), these are disallowed.
        </p>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 18px;">
            <div style="padding: 20px; border: 1px solid var(--border-color); border-radius: var(--radius-md); border-left: 4px solid var(--accent); background: #fafaf9;">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">
                    <h3 style="font-size: 16px; margin: 0;">Section 80C Ceiling</h3>
                    <span class="badge <?php echo $caps['claimed_80c'] > 0 ? 'badge-success' : 'badge-warning'; ?>">
                        <?php echo $caps['claimed_80c'] >= 150000 ? 'Fully Capped' : ($caps['claimed_80c'] > 0 ? 'Partially Claimed' : '₹0 Not Utilized'); ?>
                    </span>
                </div>
                <p style="font-size: 13px; color: var(--text-muted); margin-bottom: 10px;">Claim up to ₹1,50,000 across PPF, ELSS, EPF, Life Insurance, and Principal Home Loan repayment.</p>
                <div style="font-size: 14px; font-weight: 700; color: var(--primary); margin-bottom: 6px;">
                    Claimed: ₹<?php echo number_format($caps['claimed_80c'], 2); ?> / Limit: ₹1,50,000
                </div>
                <div style="font-size: 12px; color: #666;">
                    Eligible Benefit: <strong>₹<?php echo number_format($caps['eligible_80c'], 2); ?></strong>
                    <?php if ($caps['claimed_80c'] < 150000): ?>
                        • ₹<?php echo number_format(150000 - $caps['claimed_80c'], 2); ?> available headroom
                    <?php endif; ?>
                </div>
            </div>

            <div style="padding: 20px; border: 1px solid var(--border-color); border-radius: var(--radius-md); border-left: 4px solid var(--success); background: #fafaf9;">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">
                    <h3 style="font-size: 16px; margin: 0;">Section 80D Health Cover</h3>
                    <span class="badge <?php echo $caps['claimed_80d'] > 0 ? 'badge-success' : 'badge-warning'; ?>">
                        <?php echo $caps['claimed_80d'] > 0 ? 'Active Cover' : '₹0 Not Utilized'; ?>
                    </span>
                </div>
                <p style="font-size: 13px; color: var(--text-muted); margin-bottom: 10px;">Save up to ₹25,000 for self/family and up to ₹50,000 for senior citizen parents on health premiums.</p>
                <div style="font-size: 14px; font-weight: 700; color: var(--primary); margin-bottom: 6px;">
                    Claimed: ₹<?php echo number_format($caps['claimed_80d'], 2); ?> / Limit: ₹25k-50k
                </div>
                <div style="font-size: 12px; color: #666;">
                    Eligible Benefit: <strong>₹<?php echo number_format($caps['eligible_80d'], 2); ?></strong>
                </div>
            </div>

            <div style="padding: 20px; border: 1px solid var(--border-color); border-radius: var(--radius-md); border-left: 4px solid var(--info); background: #fafaf9;">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">
                    <h3 style="font-size: 16px; margin: 0;">NPS Benefit (80CCD 1B)</h3>
                    <span class="badge <?php echo $caps['claimed_80ccd'] > 0 ? 'badge-success' : 'badge-warning'; ?>">
                        <?php echo $caps['claimed_80ccd'] > 0 ? 'Claimed' : '₹0 Not Utilized'; ?>
                    </span>
                </div>
                <p style="font-size: 13px; color: var(--text-muted); margin-bottom: 10px;">Exclusive additional ₹50,000 deduction for National Pension System over and above Section 80C.</p>
                <div style="font-size: 14px; font-weight: 700; color: var(--primary); margin-bottom: 6px;">
                    Claimed: ₹<?php echo number_format($caps['claimed_80ccd'], 2); ?> / Limit: ₹50,000
                </div>
                <div style="font-size: 12px; color: #666;">
                    Eligible Benefit: <strong>₹<?php echo number_format($caps['eligible_80ccd'], 2); ?></strong>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include "includes/footer.php"; ?>
