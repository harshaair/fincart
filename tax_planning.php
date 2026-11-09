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
    $gross_income_input = isset($_POST['gross_income']) ? floatval($_POST['gross_income']) : $current_income;
    $deductions_input   = isset($_POST['deductions']) ? floatval($_POST['deductions']) : $current_eligible_deductions;
    $is_salaried = isset($_POST['is_salaried']) ? (bool)$_POST['is_salaried'] : true;

    if ($gross_income_input < 0 || $deductions_input < 0) {
        $calc_error = 'Income and deductions must be non-negative values.';
    } else {
        // Compute for both regimes
        $new_res = computeTaxNewRegime($gross_income_input, $is_salaried);
        $old_res = computeTaxOldRegime($gross_income_input, $deductions_input, $is_salaried);

        $better_regime = ($new_res['total_tax'] <= $old_res['total_tax']) ? 'new' : 'old';
        $tax_difference = abs($new_res['total_tax'] - $old_res['total_tax']);

        $calc_result = [
            'gross_income'   => $gross_income_input,
            'deductions'     => $deductions_input,
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

    <!-- Live Current Year Tax Status Overview -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-label">Income (<?php echo htmlspecialchars($fy_label); ?>)</div>
            <div class="stat-value">₹<?php echo number_format($current_income, 2); ?></div>
            <div class="stat-description">Recorded within Indian FY date bounds</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Allowable Deductions</div>
            <div class="stat-value">₹<?php echo number_format($current_eligible_deductions, 2); ?></div>
            <div class="stat-description">80C/80D capped to statutory limits</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">New Regime Tax</div>
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
    <div class="card">
        <h2 style="font-size: 22px; margin-bottom: 8px;">Interactive Regime Comparison Calculator</h2>
        <p style="color: var(--text-muted); font-size: 14px; margin-bottom: 20px;">
            Simulate your gross salary and deductions to instantly determine which tax regime minimizes your tax liability for FY 2024-25.
        </p>

        <form method="POST" style="margin-bottom: 25px;">
            <?php echo csrf_field(); ?>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px;">
                <div class="form-group">
                    <label>Annual Gross Income (₹)</label>
                    <input type="number" step="1000" min="0" name="gross_income" class="form-control" value="<?php echo htmlspecialchars($calc_result ? $calc_result['gross_income'] : ($current_income > 0 ? $current_income : 800000)); ?>" required>
                </div>
                <div class="form-group">
                    <label>Eligible Deductions for Old Regime (₹)</label>
                    <input type="number" step="1000" min="0" name="deductions" class="form-control" value="<?php echo htmlspecialchars($calc_result ? $calc_result['deductions'] : ($current_eligible_deductions > 0 ? $current_eligible_deductions : 150000)); ?>">
                    <small style="color: var(--text-muted); font-size: 12px;">Sec 80C, 80D, etc. (New Regime only allows Standard Deduction).</small>
                </div>
                <div class="form-group" style="display: flex; flex-direction: column; justify-content: center;">
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; margin-top: 15px;">
                        <input type="checkbox" name="is_salaried" value="1" <?php echo (!isset($calc_result) || $calc_result['is_salaried']) ? 'checked' : ''; ?>>
                        <span><strong>Salaried Individual</strong> (Includes ₹50,000 Standard Deduction)</span>
                    </label>
                </div>
            </div>
            <button type="submit" class="btn btn-primary" style="margin-top: 10px;">Calculate & Compare Regimes &rarr;</button>
        </form>

        <?php if (!empty($calc_error)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($calc_error); ?></div>
        <?php endif; ?>

        <?php if ($calc_result): ?>
            <div style="margin-top: 25px; border-top: 1px solid var(--border-color); padding-top: 25px;">
                <div class="alert alert-<?php echo $calc_result['better_regime'] === 'new' ? 'success' : 'info'; ?>" style="font-size: 16px;">
                    <strong>Recommendation:</strong> 
                    <?php if ($calc_result['tax_difference'] == 0): ?>
                        Both regimes yield identical tax (₹0 liability due to rebate/slabs).
                    <?php else: ?>
                        The <strong><?php echo $calc_result['better_regime'] === 'new' ? 'New Tax Regime' : 'Old Tax Regime'; ?></strong> saves you 
                        <strong>₹<?php echo number_format($calc_result['tax_difference'], 2); ?></strong> in taxes!
                    <?php endif; ?>
                </div>

                <div class="table-responsive">
                    <table class="custom-table">
                        <thead>
                            <tr>
                                <th>Tax Component</th>
                                <th>New Regime (FY 2024-25)</th>
                                <th>Old Regime</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Gross Income</td>
                                <td>₹<?php echo number_format($calc_result['new']['gross_income'], 2); ?></td>
                                <td>₹<?php echo number_format($calc_result['old']['gross_income'], 2); ?></td>
                            </tr>
                            <tr>
                                <td>Standard Deduction (Salaried)</td>
                                <td>-₹<?php echo number_format($calc_result['new']['standard_deduction'], 2); ?></td>
                                <td>-₹<?php echo number_format($calc_result['old']['standard_deduction'], 2); ?></td>
                            </tr>
                            <tr>
                                <td>Section 80C / 80D / Other Deductions</td>
                                <td><span style="color: #999;">Not Permitted</span></td>
                                <td>-₹<?php echo number_format($calc_result['old']['other_deductions'], 2); ?></td>
                            </tr>
                            <tr style="background: var(--off-white); font-weight: 600;">
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
                                        <span style="color: #27ae60; font-weight: 600;">-₹<?php echo number_format($calc_result['new']['rebate_87a'], 2); ?> (Zero Tax)</span>
                                    <?php else: ?>
                                        ₹0.00
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($calc_result['old']['rebate_87a'] > 0): ?>
                                        <span style="color: #27ae60; font-weight: 600;">-₹<?php echo number_format($calc_result['old']['rebate_87a'], 2); ?> (Zero Tax)</span>
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
                                    <?php if ($calc_result['better_regime'] === 'new'): ?><span class="badge badge-success">Suggested</span><?php endif; ?>
                                </td>
                                <td style="color: <?php echo $calc_result['better_regime'] === 'old' ? '#27ae60' : 'inherit'; ?>;">
                                    ₹<?php echo number_format($calc_result['old']['total_tax'], 2); ?>
                                    <?php if ($calc_result['better_regime'] === 'old'): ?><span class="badge badge-success">Suggested</span><?php endif; ?>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Optimization Recommendations -->
    <div class="card">
        <h2 style="font-size: 20px; margin-bottom: 16px;">Tax Optimization Opportunities (Old Regime)</h2>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px;">
            <div style="padding: 18px; border: 1px solid var(--border-color); border-radius: var(--radius-md); border-left: 4px solid var(--accent);">
                <h3 style="font-size: 16px; margin-bottom: 6px;">Section 80C Ceiling</h3>
                <p style="font-size: 13px; color: var(--text-muted); margin-bottom: 8px;">Claim up to ₹1,50,000 across PPF, ELSS, EPF, Life Insurance, and Principal Home Loan repayment.</p>
                <strong>Claimed: ₹<?php echo number_format($caps['claimed_80c'], 2); ?> / ₹1,50,000</strong>
            </div>
            <div style="padding: 18px; border: 1px solid var(--border-color); border-radius: var(--radius-md); border-left: 4px solid var(--info);">
                <h3 style="font-size: 16px; margin-bottom: 6px;">NPS Extra Benefit (80CCD 1B)</h3>
                <p style="font-size: 13px; color: var(--text-muted); margin-bottom: 8px;">Exclusive additional ₹50,000 deduction for National Pension System over and above Section 80C.</p>
                <strong>Claimed: ₹<?php echo number_format($caps['claimed_80ccd'], 2); ?> / ₹50,000</strong>
            </div>
            <div style="padding: 18px; border: 1px solid var(--border-color); border-radius: var(--radius-md); border-left: 4px solid var(--success);">
                <h3 style="font-size: 16px; margin-bottom: 6px;">Section 80D Health Cover</h3>
                <p style="font-size: 13px; color: var(--text-muted); margin-bottom: 8px;">Save up to ₹25,000 for self/family and up to ₹50,000 for senior citizen parents on health premiums.</p>
                <strong>Claimed: ₹<?php echo number_format($caps['claimed_80d'], 2); ?></strong>
            </div>
        </div>
    </div>
</div>

<?php include "includes/footer.php"; ?>
