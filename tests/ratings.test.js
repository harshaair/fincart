import { describe, it, expect } from 'vitest';

function validateRating(rating) {
    const num = parseInt(rating, 10);
    return !isNaN(num) && num >= 1 && num <= 5;
}

function calculateAverageRating(ratingsList) {
    if (!ratingsList || ratingsList.length === 0) {
        return { average: 0, count: 0, formatted: '0.0' };
    }
    const sum = ratingsList.reduce((acc, curr) => acc + curr.rating, 0);
    const avg = sum / ratingsList.length;
    return {
        average: avg,
        count: ratingsList.length,
        formatted: avg.toFixed(1)
    };
}

describe('Professional Ratings & Reviews Engine', () => {
    describe('Rating Validation', () => {
        it('accepts integer ratings between 1 and 5 stars', () => {
            expect(validateRating(1)).toBe(true);
            expect(validateRating(3)).toBe(true);
            expect(validateRating(5)).toBe(true);
            expect(validateRating('4')).toBe(true);
        });

        it('rejects invalid star ratings outside 1–5 range', () => {
            expect(validateRating(0)).toBe(false);
            expect(validateRating(6)).toBe(false);
            expect(validateRating(-1)).toBe(false);
            expect(validateRating('invalid')).toBe(false);
        });
    });

    describe('Average Score Calculation', () => {
        it('calculates accurate rounded average rating across multiple client reviews', () => {
            const reviews = [
                { rating: 5, review: 'Excellent tax advice' },
                { rating: 4, review: 'Very helpful' },
                { rating: 5, review: 'Clear guidance on 80C' }
            ];
            const result = calculateAverageRating(reviews);
            expect(result.count).toBe(3);
            expect(result.average).toBeCloseTo(4.6667, 3);
            expect(result.formatted).toBe('4.7');
        });

        it('handles unrated professionals gracefully', () => {
            const result = calculateAverageRating([]);
            expect(result.count).toBe(0);
            expect(result.average).toBe(0);
            expect(result.formatted).toBe('0.0');
        });
    });
});

