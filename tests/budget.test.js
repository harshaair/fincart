import { describe, it, expect } from 'vitest';

function calculateBudgetMetrics(budgets, categoryExpenses) {
    const spentMap = {};
    for (const exp of categoryExpenses) {
        spentMap[exp.category] = (spentMap[exp.category] || 0) + exp.amount;
    }

    const processed = budgets.map(b => {
        const spent = spentMap[b.category] || 0;
        const percentage = b.limitAmount > 0 ? (spent / b.limitAmount) * 100 : 0;
        const isOverBudget = spent > b.limitAmount;
        const remaining = Math.max(0, b.limitAmount - spent);

        return {
            ...b,
            spent,
            percentage: Math.min(percentage, 100),
            rawPercentage: percentage,
            isOverBudget,
            remaining
        };
    });

    const totalBudget = processed.reduce((acc, curr) => acc + curr.limitAmount, 0);
    const totalSpent = processed.reduce((acc, curr) => acc + curr.spent, 0);
    const totalPercentage = totalBudget > 0 ? (totalSpent / totalBudget) * 100 : 0;

    return {
        processed,
        totalBudget,
        totalSpent,
        totalPercentage,
        overallRemaining: Math.max(0, totalBudget - totalSpent)
    };
}

describe('Budget Performance & Calculation', () => {
    it('accurately maps aggregated transactions into budgets without N+1 loops', () => {
        const budgets = [
            { category: 'Food', limitAmount: 5000 },
            { category: 'Transportation', limitAmount: 3000 },
            { category: 'Shopping', limitAmount: 2000 }
        ];

        const transactions = [
            { category: 'Food', amount: 1500 },
            { category: 'Food', amount: 1000 },
            { category: 'Transportation', amount: 1200 },
            { category: 'Entertainment', amount: 500 } // Unbudgeted category
        ];

        const result = calculateBudgetMetrics(budgets, transactions);

        const food = result.processed.find(b => b.category === 'Food');
        expect(food.spent).toBe(2500);
        expect(food.percentage).toBe(50);
        expect(food.remaining).toBe(2500);
        expect(food.isOverBudget).toBe(false);

        const transport = result.processed.find(b => b.category === 'Transportation');
        expect(transport.spent).toBe(1200);
        expect(transport.percentage).toBe(40);

        const shopping = result.processed.find(b => b.category === 'Shopping');
        expect(shopping.spent).toBe(0);
        expect(shopping.percentage).toBe(0);

        expect(result.totalBudget).toBe(10000);
        expect(result.totalSpent).toBe(3700);
        expect(result.overallRemaining).toBe(6300);
    });

    it('correctly flags over-budget categories when spent exceeds ceiling', () => {
        const budgets = [{ category: 'Dining Out', limitAmount: 2000 }];
        const transactions = [{ category: 'Dining Out', amount: 2600 }];

        const result = calculateBudgetMetrics(budgets, transactions);
        const dining = result.processed[0];

        expect(dining.isOverBudget).toBe(true);
        expect(dining.rawPercentage).toBe(130);
        expect(dining.percentage).toBe(100); // UI visual capped at 100%
        expect(dining.remaining).toBe(0);
    });
});

