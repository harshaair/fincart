<?php
/**
 * tests/php/test_tax_service.php
 * Automated Unit Tests for FinCart Tax Engine (PHP Backend)
 */

require_once __DIR__ . '/../../services/TaxService.php';
use FinCart\Services\TaxService;

$tests_passed = 0;
$tests_failed = 0;

function assert_equal($expected, $actual, $description) {
    global $tests_passed, $tests_failed;
    if ($expected == $actual) {
        echo "  [PASS] $description\n";
        $tests_passed++;
    } else {
        echo "  [FAIL] $description (Expected " . var_export($expected, true) . ", got " . var_export($actual, true) . ")\n";
        $tests_failed++;
    }
}

echo "Running Tax Service Suite:\n";

// 1. New Regime Section 87A Rebate: Zero tax up to 7 Lakhs (salaried)
$res_7L_salaried = TaxService::computeNewRegime(750000.00, true);
assert_equal(0.00, $res_7L_salaried['total_tax'], "New Regime: Gross 7.5L salaried (7L taxable) results in 0 net tax due to Sec 87A");
assert_equal(25000.00, $res_7L_salaried['rebate_87a'], "New Regime: Full 25,000 Sec 87A rebate applied at 7L taxable");

// 2. New Regime Just Above Threshold: 7,00,001 taxable loses Sec 87A rebate
$res_above = TaxService::computeNewRegime(750010.00, true);
assert_equal(0.00, $res_above['rebate_87a'], "New Regime: Sec 87A rebate is 0 when taxable income exceeds 7 Lakhs");
assert_equal(true, $res_above['total_tax'] > 25000.00, "New Regime: Tax exceeds 25,000 without rebate above 7L");

// 3. Old Regime Section 87A Rebate: Zero tax up to 5 Lakhs (non-salaried)
$res_old_5L = TaxService::computeOldRegime(500000.00, 0.00, false);
assert_equal(0.00, $res_old_5L['total_tax'], "Old Regime: 5L taxable yields 0 net tax due to Sec 87A rebate");
assert_equal(12500.00, $res_old_5L['rebate_87a'], "Old Regime: Max 12,500 rebate applied at 5L threshold");

// 4. Statutory Deduction Caps
$deductions = [
    ['category' => '80C - PPF', 'amount' => 200000.00],
    ['category' => '80CCD(1B) - NPS', 'amount' => 80000.00],
    ['category' => '80D - Health Insurance', 'amount' => 75000.00],
    ['category' => '80TTA - Savings Interest', 'amount' => 25000.00]
];
$caps = TaxService::applyStatutoryCaps($deductions);
assert_equal(150000.00, $caps['eligible']['80C'], "Section 80C capped to statutory ceiling 1,50,000");
assert_equal(50000.00, $caps['eligible']['80CCD_1B'], "Section 80CCD(1B) capped to statutory ceiling 50,000");
assert_equal(50000.00, $caps['eligible']['80D'], "Section 80D capped to statutory ceiling 50,000");
assert_equal(10000.00, $caps['eligible']['80TTA'], "Section 80TTA capped to statutory ceiling 10,000");
assert_equal(260000.00, $caps['total_eligible'], "Total eligible Chapter VI-A deductions sum to 2,60,000");
assert_equal(120000.00, $caps['disallowed'], "Disallowed deduction excess accurately calculated");

// 5. Regime Comparison Engine
$comp = TaxService::compareRegimes(1200000.00, 200000.00, true);
assert_equal('new', $comp['recommended_regime'], "At 12L gross with 2L deductions, New Regime is mathematically superior");
assert_equal(true, $comp['savings'] > 0, "Tax savings quantified and strictly positive");

echo "Tax Service Suite completed: $tests_passed passed, $tests_failed failed.\n\n";

if ($tests_failed > 0) {
    exit(1);
}
