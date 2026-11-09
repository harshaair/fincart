<?php
/**
 * includes/tax_helper.php
 * Indian Income Tax Computation Engine (FY 2024-25 / AY 2025-26)
 * Supports New Regime and Old Regime, Section 87A Rebate, Standard Deduction, and Statutory Caps.
 */

// Statutory deduction limits
define('LIMIT_SECTION_80C', 150000.0);
define('LIMIT_SECTION_80CCD_1B', 50000.0);
define('LIMIT_SECTION_80D_SELF', 25000.0);
define('LIMIT_SECTION_80D_PARENTS', 50000.0);
define('LIMIT_SECTION_80TTA', 10000.0);
define('STANDARD_DEDUCTION_SALARY', 50000.0);

/**
 * Compute Tax under New Tax Regime (Default Regime for FY 2024-25)
 * Slabs:
 *   Up to ₹3,00,000: Nil
 *   ₹3,00,001 - ₹6,00,000: 5%
 *   ₹6,00,001 - ₹9,00,000: 10%
 *   ₹9,00,001 - ₹12,00,000: 15%
 *   ₹12,00,001 - ₹15,00,000: 20%
 *   Above ₹15,00,000: 30%
 *
 * Section 87A: Taxable Income up to ₹7,00,000 has 100% tax rebate (Max ₹25,000).
 * Standard Deduction: ₹50,000 for salaried employees.
 * (Note: Section 80C, 80D, HRA etc. are not available under New Regime).
 */
function computeTaxNewRegime($gross_income, $is_salaried = true) {
    $std_deduction = $is_salaried ? STANDARD_DEDUCTION_SALARY : 0.0;
    $taxable_income = max(0.0, $gross_income - $std_deduction);

    $slabs = [
        ['min' => 0, 'max' => 300000, 'rate' => 0],
        ['min' => 300000, 'max' => 600000, 'rate' => 5],
        ['min' => 600000, 'max' => 900000, 'rate' => 10],
        ['min' => 900000, 'max' => 1200000, 'rate' => 15],
        ['min' => 1200000, 'max' => 1500000, 'rate' => 20],
        ['min' => 1500000, 'max' => PHP_INT_MAX, 'rate' => 30]
    ];

    $calculated_tax = 0.0;
    foreach ($slabs as $slab) {
        if ($taxable_income > $slab['min']) {
            $taxable_in_slab = min($taxable_income, $slab['max']) - $slab['min'];
            $calculated_tax += $taxable_in_slab * ($slab['rate'] / 100);
        }
    }

    // Section 87A rebate for New Regime:
    $rebate_87a = 0.0;
    if ($taxable_income <= 700000.0) {
        $rebate_87a = min($calculated_tax, 25000.0);
    }
    $tax_after_rebate = max(0.0, $calculated_tax - $rebate_87a);

    // Surcharge
    $surcharge = 0.0;
    if ($taxable_income > 5000000.0) {
        $surcharge = $tax_after_rebate * 0.25;
    } elseif ($taxable_income > 2000000.0) {
        $surcharge = $tax_after_rebate * 0.15;
    } elseif ($taxable_income > 1000000.0 && $taxable_income > 5000000.0) {
        $surcharge = $tax_after_rebate * 0.10;
    }

    // 4% Health & Education Cess
    $cess = ($tax_after_rebate + $surcharge) * 0.04;
    $total_tax = $tax_after_rebate + $surcharge + $cess;

    return [
        'regime' => 'New Regime (FY 2024-25)',
        'gross_income' => $gross_income,
        'standard_deduction' => $std_deduction,
        'other_deductions' => 0.0,
        'taxable_income' => $taxable_income,
        'base_tax' => $calculated_tax,
        'rebate_87a' => $rebate_87a,
        'tax_after_rebate' => $tax_after_rebate,
        'surcharge' => $surcharge,
        'cess' => $cess,
        'total_tax' => $total_tax,
        'effective_rate' => $gross_income > 0 ? ($total_tax / $gross_income) * 100 : 0
    ];
}

/**
 * Compute Tax under Old Tax Regime
 * Slabs:
 *   Up to ₹2,50,000: Nil
 *   ₹2,50,001 - ₹5,00,000: 5%
 *   ₹5,00,001 - ₹10,00,000: 20%
 *   Above ₹10,00,000: 30%
 *
 * Section 87A: Taxable Income up to ₹5,00,000 has 100% rebate (Max ₹12,500).
 * Standard Deduction: ₹50,000 for salaried employees.
 * Section 80C, 80D, 80E, etc. deductions are allowed up to their caps.
 */
function computeTaxOldRegime($gross_income, $eligible_deductions = 0.0, $is_salaried = true) {
    $std_deduction = $is_salaried ? STANDARD_DEDUCTION_SALARY : 0.0;
    $total_deductions = $std_deduction + $eligible_deductions;
    $taxable_income = max(0.0, $gross_income - $total_deductions);

    $slabs = [
        ['min' => 0, 'max' => 250000, 'rate' => 0],
        ['min' => 250000, 'max' => 500000, 'rate' => 5],
        ['min' => 500000, 'max' => 1000000, 'rate' => 20],
        ['min' => 1000000, 'max' => PHP_INT_MAX, 'rate' => 30]
    ];

    $calculated_tax = 0.0;
    foreach ($slabs as $slab) {
        if ($taxable_income > $slab['min']) {
            $taxable_in_slab = min($taxable_income, $slab['max']) - $slab['min'];
            $calculated_tax += $taxable_in_slab * ($slab['rate'] / 100);
        }
    }

    // Section 87A rebate for Old Regime:
    $rebate_87a = 0.0;
    if ($taxable_income <= 500000.0) {
        $rebate_87a = min($calculated_tax, 12500.0);
    }
    $tax_after_rebate = max(0.0, $calculated_tax - $rebate_87a);

    // Surcharge
    $surcharge = 0.0;
    if ($taxable_income > 5000000.0) {
        $surcharge = $tax_after_rebate * 0.25;
    } elseif ($taxable_income > 2000000.0) {
        $surcharge = $tax_after_rebate * 0.15;
    }

    // 4% Health & Education Cess
    $cess = ($tax_after_rebate + $surcharge) * 0.04;
    $total_tax = $tax_after_rebate + $surcharge + $cess;

    return [
        'regime' => 'Old Regime',
        'gross_income' => $gross_income,
        'standard_deduction' => $std_deduction,
        'other_deductions' => $eligible_deductions,
        'taxable_income' => $taxable_income,
        'base_tax' => $calculated_tax,
        'rebate_87a' => $rebate_87a,
        'tax_after_rebate' => $tax_after_rebate,
        'surcharge' => $surcharge,
        'cess' => $cess,
        'total_tax' => $total_tax,
        'effective_rate' => $gross_income > 0 ? ($total_tax / $gross_income) * 100 : 0
    ];
}

/**
 * Apply statutory caps to user deduction records
 */
function applyStatutoryCaps($deductions_list) {
    $sec80c = 0.0;
    $sec80d = 0.0;
    $sec80ccd = 0.0;
    $sec80tta = 0.0;
    $others = 0.0;

    foreach ($deductions_list as $item) {
        $cat = strtolower($item['category'] ?? '');
        $amt = (float)($item['amount'] ?? 0);

        if (strpos($cat, '80c') !== false && strpos($cat, '80ccd') === false) {
            $sec80c += $amt;
        } elseif (strpos($cat, '80ccd') !== false) {
            $sec80ccd += $amt;
        } elseif (strpos($cat, '80d') !== false) {
            $sec80d += $amt;
        } elseif (strpos($cat, '80tta') !== false) {
            $sec80tta += $amt;
        } else {
            $others += $amt;
        }
    }

    $eligible_80c = min($sec80c, LIMIT_SECTION_80C);
    $eligible_80ccd = min($sec80ccd, LIMIT_SECTION_80CCD_1B);
    $eligible_80d = min($sec80d, LIMIT_SECTION_80D_PARENTS); // up to 50k max with parents
    $eligible_80tta = min($sec80tta, LIMIT_SECTION_80TTA);

    $total_claimed = $sec80c + $sec80ccd + $sec80d + $sec80tta + $others;
    $total_eligible = $eligible_80c + $eligible_80ccd + $eligible_80d + $eligible_80tta + $others;

    return [
        'claimed_80c' => $sec80c,
        'eligible_80c' => $eligible_80c,
        'claimed_80d' => $sec80d,
        'eligible_80d' => $eligible_80d,
        'claimed_80ccd' => $sec80ccd,
        'eligible_80ccd' => $eligible_80ccd,
        'total_claimed' => $total_claimed,
        'total_eligible' => $total_eligible,
        'disallowed_amount' => max(0.0, $total_claimed - $total_eligible)
    ];
}

/**
 * Computes Indian Financial Year (FY) and Assessment Year (AY) boundaries for a given date.
 * Statutory Indian FY runs from April 1 to March 31 of the following year.
 *
 * @param string|int|null $date Date string (Y-m-d) or unix timestamp. Defaults to current date.
 * @return array Array with start_date, end_date, start_year, end_year, fy_label, ay_label, fy_code
 */
function getIndianFinancialYear($date = null) {
    if ($date === null || $date === '') {
        $ts = time();
    } elseif (is_numeric($date)) {
        $ts = (int)$date;
    } else {
        $ts = strtotime($date);
        if ($ts === false) $ts = time();
    }

    $year = (int)date('Y', $ts);
    $month = (int)date('n', $ts);

    if ($month >= 4) {
        $start_year = $year;
        $end_year = $year + 1;
    } else {
        $start_year = $year - 1;
        $end_year = $year;
    }

    $start_date = sprintf('%04d-04-01', $start_year);
    $end_date = sprintf('%04d-03-31', $end_year);
    $short_end = substr((string)$end_year, -2);
    $short_ay_end = substr((string)($end_year + 1), -2);

    return [
        'start_year' => $start_year,
        'end_year' => $end_year,
        'start_date' => $start_date,
        'end_date' => $end_date,
        'fy_label' => "FY {$start_year}-{$short_end}",
        'ay_label' => "AY " . ($start_year + 1) . "-{$short_ay_end}",
        'fy_code' => "{$start_year}-{$short_end}"
    ];
}

/**
 * Returns a list of Indian Financial Years for selection dropdowns.
 *
 * @param int $count Number of fiscal years to generate around the current FY.
 * @return array List of FY data arrays.
 */
function getAvailableFinancialYears($count = 4) {
    $current = getIndianFinancialYear();
    $current_start = $current['start_year'];
    $list = [];

    // Include 1 future FY and ($count - 1) past FYs
    for ($y = $current_start + 1; $y >= $current_start - ($count - 2); $y--) {
        $start_date = sprintf('%04d-04-01', $y);
        $end_year = $y + 1;
        $short_end = substr((string)$end_year, -2);
        $short_ay_end = substr((string)($end_year + 1), -2);

        $list[] = [
            'start_year' => $y,
            'end_year' => $end_year,
            'start_date' => $start_date,
            'end_date' => sprintf('%04d-03-31', $end_year),
            'fy_label' => "FY {$y}-{$short_end}",
            'ay_label' => "AY " . ($y + 1) . "-{$short_ay_end}",
            'fy_code' => "{$y}-{$short_end}",
            'is_current' => ($y === $current_start)
        ];
    }

    return $list;
}


