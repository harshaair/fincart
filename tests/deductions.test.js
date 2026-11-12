import { describe, it, expect } from 'vitest';

const LIMIT_SECTION_80C = 150000;
const LIMIT_SECTION_80CCD_1B = 50000;
const LIMIT_SECTION_80D_MAX = 50000;
const LIMIT_SECTION_80TTA = 10000;

function applyStatutoryCaps(deductionsList) {
    let sec80c = 0;
    let sec80ccd = 0;
    let sec80d = 0;
    let sec80tta = 0;
    let others = 0;

    for (const item of deductionsList) {
        const cat = (item.category || '').toLowerCase();
        const amt = parseFloat(item.amount) || 0;

        if (cat.includes('80c') && !cat.includes('80ccd')) {
            sec80c += amt;
        } else if (cat.includes('80ccd')) {
            sec80ccd += amt;
        } else if (cat.includes('80d')) {
            sec80d += amt;
        } else if (cat.includes('80tta')) {
            sec80tta += amt;
        } else {
            others += amt;
        }
    }

    const eligible80c = Math.min(sec80c, LIMIT_SECTION_80C);
    const eligible80ccd = Math.min(sec80ccd, LIMIT_SECTION_80CCD_1B);
    const eligible80d = Math.min(sec80d, LIMIT_SECTION_80D_MAX);
    const eligible80tta = Math.min(sec80tta, LIMIT_SECTION_80TTA);

    const totalClaimed = sec80c + sec80ccd + sec80d + sec80tta + others;
    const totalEligible = eligible80c + eligible80ccd + eligible80d + eligible80tta + others;
    const disallowed = Math.max(0, totalClaimed - totalEligible);

    return {
        claimed80c: sec80c,
        eligible80c: eligible80c,
        claimed80ccd: sec80ccd,
        eligible80ccd: eligible80ccd,
        claimed80d: sec80d,
        eligible80d: eligible80d,
        totalClaimed,
        totalEligible,
        disallowed
    };
}

describe('Statutory Tax Deduction Caps', () => {
    it('caps Section 80C strictly at ₹1,50,000 even if claim is higher', () => {
        const items = [
            { category: 'Section 80C - PPF', amount: 100000 },
            { category: 'Section 80C - Life Insurance', amount: 80000 }
        ];
        const res = applyStatutoryCaps(items);
        expect(res.claimed80c).toBe(180000);
        expect(res.eligible80c).toBe(150000);
        expect(res.disallowed).toBe(30000);
    });

    it('caps Section 80CCD(1B) NPS benefit at ₹50,000', () => {
        const items = [
            { category: 'Section 80CCD(1B) - NPS', amount: 75000 }
        ];
        const res = applyStatutoryCaps(items);
        expect(res.claimed80ccd).toBe(75000);
        expect(res.eligible80ccd).toBe(50000);
        expect(res.disallowed).toBe(25000);
    });

    it('handles multiple sections simultaneously and computes net allowable deductions', () => {
        const items = [
            { category: 'Section 80C - ELSS', amount: 200000 }, // capped at 150k (50k excess)
            { category: 'Section 80D - Health Insurance', amount: 35000 }, // allowed (up to 50k)
            { category: 'Section 80CCD(1B) - NPS', amount: 50000 }, // allowed
            { category: 'Section 80TTA - Savings Interest', amount: 15000 } // capped at 10k (5k excess)
        ];
        const res = applyStatutoryCaps(items);
        expect(res.totalClaimed).toBe(300000);
        expect(res.eligible80c).toBe(150000);
        expect(res.eligible80d).toBe(35000);
        expect(res.eligible80ccd).toBe(50000);
        expect(res.totalEligible).toBe(150000 + 35000 + 50000 + 10000); // 245000
        expect(res.disallowed).toBe(55000);
    });

    it('returns exactly ZERO deductions when no entries are logged (never assumes default 1.5L)', () => {
        const items = [];
        const res = applyStatutoryCaps(items);
        expect(res.claimed80c).toBe(0);
        expect(res.eligible80c).toBe(0);
        expect(res.claimed80d).toBe(0);
        expect(res.eligible80d).toBe(0);
        expect(res.totalClaimed).toBe(0);
        expect(res.totalEligible).toBe(0);
        expect(res.disallowed).toBe(0);
    });

    it('distinguishes between Section 80C and Section 80D without cross-bleeding limits', () => {
        const items = [
            { category: 'Section 80D - Health Insurance', amount: 30000 }
        ];
        const res = applyStatutoryCaps(items);
        expect(res.claimed80c).toBe(0);
        expect(res.eligible80c).toBe(0);
        expect(res.claimed80d).toBe(30000);
        expect(res.eligible80d).toBe(30000); // under 50k max cap
        expect(res.totalEligible).toBe(30000);
    });
});

