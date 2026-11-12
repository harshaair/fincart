import { describe, it, expect } from 'vitest';

// Pure JS implementation matching includes/tax_helper.php
const STANDARD_DEDUCTION = 50000;
const LIMIT_80C = 150000;

function computeTaxNewRegime(grossIncome, isSalaried = true) {
    const stdDeduction = isSalaried ? STANDARD_DEDUCTION : 0;
    const taxableIncome = Math.max(0, grossIncome - stdDeduction);

    const slabs = [
        { min: 0, max: 300000, rate: 0 },
        { min: 300000, max: 600000, rate: 0.05 },
        { min: 600000, max: 900000, rate: 0.10 },
        { min: 900000, max: 1200000, rate: 0.15 },
        { min: 1200000, max: 1500000, rate: 0.20 },
        { min: 1500000, max: Infinity, rate: 0.30 }
    ];

    let baseTax = 0;
    for (const slab of slabs) {
        if (taxableIncome > slab.min) {
            const inSlab = Math.min(taxableIncome, slab.max) - slab.min;
            baseTax += inSlab * slab.rate;
        }
    }

    // Section 87A rebate: taxable income <= 7,00,000 gets 100% rebate (max 25,000)
    let rebate87A = 0;
    if (taxableIncome <= 700000) {
        rebate87A = Math.min(baseTax, 25000);
    }
    const taxAfterRebate = Math.max(0, baseTax - rebate87A);

    // Statutory Surcharge (FY 2024-25 / AY 2025-26)
    let surcharge = 0;
    if (taxableIncome > 20000000) {
        surcharge = taxAfterRebate * 0.25;
    } else if (taxableIncome > 10000000) {
        surcharge = taxAfterRebate * 0.15;
    } else if (taxableIncome > 5000000) {
        surcharge = taxAfterRebate * 0.10;
    }

    const cess = (taxAfterRebate + surcharge) * 0.04;
    const totalTax = Math.round(taxAfterRebate + surcharge + cess);

    return {
        grossIncome,
        taxableIncome,
        baseTax,
        rebate87A,
        taxAfterRebate,
        surcharge,
        cess,
        totalTax
    };
}

function computeTaxOldRegime(grossIncome, deductions = 0, isSalaried = true) {
    const stdDeduction = isSalaried ? STANDARD_DEDUCTION : 0;
    const taxableIncome = Math.max(0, grossIncome - stdDeduction - deductions);

    const slabs = [
        { min: 0, max: 250000, rate: 0 },
        { min: 250000, max: 500000, rate: 0.05 },
        { min: 500000, max: 1000000, rate: 0.20 },
        { min: 1000000, max: Infinity, rate: 0.30 }
    ];

    let baseTax = 0;
    for (const slab of slabs) {
        if (taxableIncome > slab.min) {
            const inSlab = Math.min(taxableIncome, slab.max) - slab.min;
            baseTax += inSlab * slab.rate;
        }
    }

    // Section 87A rebate for Old Regime: taxable income <= 5,00,000 gets max 12,500 rebate
    let rebate87A = 0;
    if (taxableIncome <= 500000) {
        rebate87A = Math.min(baseTax, 12500);
    }
    const taxAfterRebate = Math.max(0, baseTax - rebate87A);

    // Statutory Surcharge (FY 2024-25 / AY 2025-26)
    let surcharge = 0;
    if (taxableIncome > 20000000) {
        surcharge = taxAfterRebate * 0.25;
    } else if (taxableIncome > 10000000) {
        surcharge = taxAfterRebate * 0.15;
    } else if (taxableIncome > 5000000) {
        surcharge = taxAfterRebate * 0.10;
    }

    const cess = (taxAfterRebate + surcharge) * 0.04;
    const totalTax = Math.round(taxAfterRebate + surcharge + cess);

    return {
        grossIncome,
        taxableIncome,
        baseTax,
        rebate87A,
        taxAfterRebate,
        surcharge,
        cess,
        totalTax
    };
}

// Pure JS implementation matching getIndianFinancialYear in includes/tax_helper.php
function getIndianFinancialYear(dateInput = null) {
    const d = dateInput ? new Date(dateInput) : new Date();
    const year = d.getFullYear();
    const month = d.getMonth() + 1; // 1 to 12

    let startYear, endYear;
    if (month >= 4) {
        startYear = year;
        endYear = year + 1;
    } else {
        startYear = year - 1;
        endYear = year;
    }

    const shortEnd = String(endYear).slice(-2);
    const shortAyEnd = String(endYear + 1).slice(-2);

    return {
        startYear,
        endYear,
        startDate: `${startYear}-04-01`,
        endDate: `${endYear}-03-31`,
        fyLabel: `FY ${startYear}-${shortEnd}`,
        ayLabel: `AY ${startYear + 1}-${shortAyEnd}`,
        fyCode: `${startYear}-${shortEnd}`
    };
}

describe('Indian Income Tax Engine - FY 2024-25', () => {
    describe('New Tax Regime', () => {
        it('applies standard deduction of ₹50,000 for salaried employees', () => {
            const res = computeTaxNewRegime(800000, true);
            expect(res.taxableIncome).toBe(750000);
        });

        it('gives ZERO tax under Section 87A rebate when taxable income <= ₹7,00,000', () => {
            // Gross ₹7,50,000 - ₹50,000 std deduction = ₹7,00,000 taxable income
            const res = computeTaxNewRegime(750000, true);
            expect(res.taxableIncome).toBe(700000);
            expect(res.baseTax).toBe(25000); // 3L-6L @ 5% (15k) + 6L-7L @ 10% (10k) = 25k
            expect(res.rebate87A).toBe(25000);
            expect(res.totalTax).toBe(0);
        });

        it('calculates correct slab tax and 4% cess above ₹7,00,000 threshold', () => {
            // Gross ₹10,50,000 - ₹50k std deduction = ₹10,00,000 taxable
            // 0 - 3L: 0
            // 3L - 6L: 15,000 (5%)
            // 6L - 9L: 30,000 (10%)
            // 9L - 10L: 15,000 (15%)
            // Base tax = 60,000
            // Cess 4% = 2,400
            // Total = 62,400
            const res = computeTaxNewRegime(1050000, true);
            expect(res.taxableIncome).toBe(1000000);
            expect(res.baseTax).toBe(60000);
            expect(res.rebate87A).toBe(0);
            expect(res.cess).toBe(2400);
            expect(res.totalTax).toBe(62400);
        });

        it('charges ZERO surcharge at ₹25 Lakh income (statutory threshold is ₹50 Lakh)', () => {
            // Gross ₹25,00,000 - ₹50k std deduction = ₹24,50,000 taxable
            // Base tax = 4,35,000
            // Surcharge = 0 (under ₹50L)
            // Cess 4% = 17,400
            // Total = 4,52,400
            const res = computeTaxNewRegime(2500000, true);
            expect(res.taxableIncome).toBe(2450000);
            expect(res.baseTax).toBe(435000);
            expect(res.surcharge).toBe(0);
            expect(res.cess).toBe(17400);
            expect(res.totalTax).toBe(452400);
        });

        it('applies 10% surcharge when taxable income exceeds ₹50 Lakh', () => {
            // Gross ₹60,50,000 - ₹50k = ₹60,00,000 taxable
            // Base tax: 0-3L (0), 3-6L (15k), 6-9L (30k), 9-12L (45k), 12-15L (60k), >15L (45L @ 30% = 13.5L) = 15,00,000
            // Surcharge: 10% of 15,00,000 = 1,50,000
            // Cess: 4% of (15,00,000 + 1,50,000) = 66,000
            // Total: 17,16,000
            const res = computeTaxNewRegime(6050000, true);
            expect(res.taxableIncome).toBe(6000000);
            expect(res.baseTax).toBe(1500000);
            expect(res.surcharge).toBe(150000);
            expect(res.cess).toBe(66000);
            expect(res.totalTax).toBe(1716000);
        });
    });

    describe('Old Tax Regime', () => {
        it('gives ZERO tax under Section 87A rebate when taxable income <= ₹5,00,000', () => {
            // Gross ₹5,50,000 - ₹50,000 std deduction = ₹5,00,000 taxable
            const res = computeTaxOldRegime(550000, 0, true);
            expect(res.taxableIncome).toBe(500000);
            expect(res.baseTax).toBe(12500);
            expect(res.rebate87A).toBe(12500);
            expect(res.totalTax).toBe(0);
        });

        it('reduces taxable income by eligible section deductions', () => {
            // Gross ₹10,00,000 - ₹50k std - ₹1.5L 80C = ₹8,00,000 taxable
            const res = computeTaxOldRegime(1000000, 150000, true);
            expect(res.taxableIncome).toBe(800000);
            // 0 - 2.5L: 0
            // 2.5L - 5L: 12,500 (5%)
            // 5L - 8L: 60,000 (20%)
            // Base: 72,500
            // Cess 4%: 2,900
            // Total: 75,400
            expect(res.baseTax).toBe(72500);
            expect(res.cess).toBe(2900);
            expect(res.totalTax).toBe(75400);
        });
    });

    describe('Regime Comparison', () => {
        it('accurately identifies the regime that minimizes tax liability', () => {
            // At ₹12,00,000 with ₹1,50,000 deduction:
            // New Regime: 12L - 50k = 11.5L taxable -> Tax: 85,800
            // Old Regime: 12L - 50k - 1.5L = 10L taxable -> Base 1,12,500 + Cess = 1,17,000
            const newRes = computeTaxNewRegime(1200000, true);
            const oldRes = computeTaxOldRegime(1200000, 150000, true);
            expect(newRes.totalTax).toBeLessThan(oldRes.totalTax);
        });
    });

    describe('Indian Financial Year (FY) & Assessment Year (AY) Boundary Logic', () => {
        it('maps November 2026 to FY 2026-27 and AY 2027-28', () => {
            const fy = getIndianFinancialYear('2026-11-09');
            expect(fy.startYear).toBe(2026);
            expect(fy.endYear).toBe(2027);
            expect(fy.startDate).toBe('2026-04-01');
            expect(fy.endDate).toBe('2027-03-31');
            expect(fy.fyLabel).toBe('FY 2026-27');
            expect(fy.ayLabel).toBe('AY 2027-28');
            expect(fy.fyCode).toBe('2026-27');
        });

        it('maps February 2026 to FY 2025-26 and AY 2026-27', () => {
            const fy = getIndianFinancialYear('2026-02-15');
            expect(fy.startYear).toBe(2025);
            expect(fy.endYear).toBe(2026);
            expect(fy.startDate).toBe('2025-04-01');
            expect(fy.endDate).toBe('2026-03-31');
            expect(fy.fyLabel).toBe('FY 2025-26');
            expect(fy.ayLabel).toBe('AY 2026-27');
            expect(fy.fyCode).toBe('2025-26');
        });

        it('correctly treats boundary dates (April 1st vs March 31st)', () => {
            // April 1st starts the new FY
            const apr1 = getIndianFinancialYear('2026-04-01');
            expect(apr1.fyCode).toBe('2026-27');

            // March 31st belongs to the previous FY
            const mar31 = getIndianFinancialYear('2026-03-31');
            expect(mar31.fyCode).toBe('2025-26');
        });
    });
});

