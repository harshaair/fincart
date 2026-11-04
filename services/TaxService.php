<?php
/**
 * services/TaxService.php
 * Production Tax Optimization Service for Indian Income Tax (FY 2024-25 / AY 2025-26)
 */

namespace FinCart\Services;

class TaxService {
    public const STANDARD_DEDUCTION_SALARIED = 50000.00;

    // Statutory deduction limits
    public const LIMIT_80C = 150000.00;
    public const LIMIT_80CCD_1B = 50000.00;
    public const LIMIT_80D = 50000.00;
    public const LIMIT_80TTA = 10000.00;

    // Section 87A Rebate limits
    public const SEC_87A_NEW_REGIME_CEILING = 700000.00;
    public const SEC_87A_NEW_REGIME_MAX_REBATE = 25000.00;
    public const SEC_87A_OLD_REGIME_CEILING = 500000.00;
    public const SEC_87A_OLD_REGIME_MAX_REBATE = 12500.00;

    public const HEALTH_AND_EDUCATION_CESS_RATE = 0.04;

    /**
     * Clamps user deduction claims to Indian Income Tax Act statutory limits
     */
    public static function applyStatutoryCaps(array $deductions): array {
        $claimed = [
            '80C' => 0.00,
            '80CCD_1B' => 0.00,
            '80D' => 0.00,
            '80TTA' => 0.00,
            'other' => 0.00
        ];

        foreach ($deductions as $d) {
            $cat = strtoupper(trim($d['category'] ?? ''));
            $amt = (float)($d['amount'] ?? 0);
            if ($amt <= 0) continue;

            if (strpos($cat, '80C') !== false && strpos($cat, 'CCD') === false) {
                $claimed['80C'] += $amt;
            } elseif (strpos($cat, 'CCD') !== false) {
                $claimed['80CCD_1B'] += $amt;
            } elseif (strpos($cat, '80D') !== false) {
                $claimed['80D'] += $amt;
            } elseif (strpos($cat, '80TTA') !== false || strpos($cat, 'SAVINGS') !== false) {
                $claimed['80TTA'] += $amt;
            } else {
                $claimed['other'] += $amt;
            }
        }

        $eligible = [
            '80C' => min($claimed['80C'], self::LIMIT_80C),
            '80CCD_1B' => min($claimed['80CCD_1B'], self::LIMIT_80CCD_1B),
            '80D' => min($claimed['80D'], self::LIMIT_80D),
            '80TTA' => min($claimed['80TTA'], self::LIMIT_80TTA),
            'other' => $claimed['other']
        ];

        $total_claimed = array_sum($claimed);
        $total_eligible = array_sum($eligible);

        return [
            'claimed' => $claimed,
            'eligible' => $eligible,
            'total_claimed' => $total_claimed,
            'total_eligible' => $total_eligible,
            'disallowed' => max(0.00, $total_claimed - $total_eligible)
        ];
    }

    /**
     * Computes tax liability under the Default New Tax Regime (Section 115BAC)
     */
    public static function computeNewRegime(float $gross_income, bool $is_salaried = true): array {
        $std_deduction = $is_salaried ? self::STANDARD_DEDUCTION_SALARIED : 0.00;
        $taxable_income = max(0.00, $gross_income - $std_deduction);

        $slabs = [
            ['limit' => 300000.00, 'rate' => 0.00],
            ['limit' => 300000.00, 'rate' => 0.05],
            ['limit' => 300000.00, 'rate' => 0.10],
            ['limit' => 300000.00, 'rate' => 0.15],
            ['limit' => 300000.00, 'rate' => 0.20],
            ['limit' => INF,       'rate' => 0.30]
        ];

        $breakdown = [];
        $tax_before_rebate = 0.00;
        $remaining = $taxable_income;

        foreach ($slabs as $slab) {
            if ($remaining <= 0) break;
            $taxable_in_slab = min($remaining, $slab['limit']);
            $tax_in_slab = $taxable_in_slab * $slab['rate'];
            $tax_before_rebate += $tax_in_slab;
            $breakdown[] = [
                'taxable_amount' => $taxable_in_slab,
                'rate' => $slab['rate'],
                'tax' => $tax_in_slab
            ];
            $remaining -= $taxable_in_slab;
        }

        // Section 87A Rebate for New Regime: full rebate up to ₹25,000 for taxable income <= ₹7,00,000
        $rebate_87a = 0.00;
        if ($taxable_income <= self::SEC_87A_NEW_REGIME_CEILING) {
            $rebate_87a = min($tax_before_rebate, self::SEC_87A_NEW_REGIME_MAX_REBATE);
        }

        $tax_after_rebate = max(0.00, $tax_before_rebate - $rebate_87a);
        $cess = $tax_after_rebate * self::HEALTH_AND_EDUCATION_CESS_RATE;
        $total_tax = round($tax_after_rebate + $cess);

        return [
            'gross_income' => $gross_income,
            'standard_deduction' => $std_deduction,
            'taxable_income' => $taxable_income,
            'tax_before_rebate' => $tax_before_rebate,
            'rebate_87a' => $rebate_87a,
            'cess' => $cess,
            'total_tax' => $total_tax,
            'effective_rate' => $gross_income > 0 ? round(($total_tax / $gross_income) * 100, 2) : 0.00,
            'breakdown' => $breakdown
        ];
    }

    /**
     * Computes tax liability under the Old Tax Regime (with Chapter VI-A deductions)
     */
    public static function computeOldRegime(float $gross_income, float $eligible_deductions, bool $is_salaried = true): array {
        $std_deduction = $is_salaried ? self::STANDARD_DEDUCTION_SALARIED : 0.00;
        $total_deductions = $std_deduction + $eligible_deductions;
        $taxable_income = max(0.00, $gross_income - $total_deductions);

        $slabs = [
            ['limit' => 250000.00, 'rate' => 0.00],
            ['limit' => 250000.00, 'rate' => 0.05],
            ['limit' => 500000.00, 'rate' => 0.20],
            ['limit' => INF,       'rate' => 0.30]
        ];

        $breakdown = [];
        $tax_before_rebate = 0.00;
        $remaining = $taxable_income;

        foreach ($slabs as $slab) {
            if ($remaining <= 0) break;
            $taxable_in_slab = min($remaining, $slab['limit']);
            $tax_in_slab = $taxable_in_slab * $slab['rate'];
            $tax_before_rebate += $tax_in_slab;
            $breakdown[] = [
                'taxable_amount' => $taxable_in_slab,
                'rate' => $slab['rate'],
                'tax' => $tax_in_slab
            ];
            $remaining -= $taxable_in_slab;
        }

        // Section 87A Rebate for Old Regime: up to ₹12,500 for taxable income <= ₹5,00,000
        $rebate_87a = 0.00;
        if ($taxable_income <= self::SEC_87A_OLD_REGIME_CEILING) {
            $rebate_87a = min($tax_before_rebate, self::SEC_87A_OLD_REGIME_MAX_REBATE);
        }

        $tax_after_rebate = max(0.00, $tax_before_rebate - $rebate_87a);
        $cess = $tax_after_rebate * self::HEALTH_AND_EDUCATION_CESS_RATE;
        $total_tax = round($tax_after_rebate + $cess);

        return [
            'gross_income' => $gross_income,
            'standard_deduction' => $std_deduction,
            'chapter_via_deductions' => $eligible_deductions,
            'taxable_income' => $taxable_income,
            'tax_before_rebate' => $tax_before_rebate,
            'rebate_87a' => $rebate_87a,
            'cess' => $cess,
            'total_tax' => $total_tax,
            'effective_rate' => $gross_income > 0 ? round(($total_tax / $gross_income) * 100, 2) : 0.00,
            'breakdown' => $breakdown
        ];
    }

    /**
     * Compares both regimes and returns the recommended strategy with net savings
     */
    public static function compareRegimes(float $gross_income, float $eligible_deductions, bool $is_salaried = true): array {
        $new = self::computeNewRegime($gross_income, $is_salaried);
        $old = self::computeOldRegime($gross_income, $eligible_deductions, $is_salaried);

        $better = ($new['total_tax'] <= $old['total_tax']) ? 'new' : 'old';
        $savings = abs($new['total_tax'] - $old['total_tax']);

        return [
            'new_regime' => $new,
            'old_regime' => $old,
            'recommended_regime' => $better,
            'savings' => $savings
        ];
    }
}

