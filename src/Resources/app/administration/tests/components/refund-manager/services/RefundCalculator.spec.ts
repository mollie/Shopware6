import { describe, expect, test } from 'vitest';
import RefundCalculator from '../../../../src/module/mollie-payments/components/mollie-refund-manager/services/RefundCalculator';

const calculator = new RefundCalculator();

describe('RefundCalculator.calculateTotalRefundAmount', () => {
    test('returns 0 for an empty list', () => {
        expect(calculator.calculateTotalRefundAmount([])).toBe(0);
    });

    test('sums up the refund amounts of all items', () => {
        const items = [{ refundAmount: 10 }, { refundAmount: 5.5 }, { refundAmount: 0 }];

        expect(calculator.calculateTotalRefundAmount(items)).toBe(15.5);
    });

    test('parses string amounts coming from input fields', () => {
        const items = [{ refundAmount: '10.00' }, { refundAmount: '2.50' }];

        expect(calculator.calculateTotalRefundAmount(items)).toBe(12.5);
    });

    test('rounds the total to two decimals', () => {
        const items = [{ refundAmount: 0.1 }, { refundAmount: 0.2 }];

        // 0.1 + 0.2 = 0.30000000000000004 without rounding
        expect(calculator.calculateTotalRefundAmount(items)).toBe(0.3);
    });
});

describe('RefundCalculator.roundToTwo', () => {
    test('rounds typical floating point results', () => {
        expect(calculator.roundToTwo(0.1 + 0.2)).toBe(0.3);
    });

    test('rounds half up', () => {
        expect(calculator.roundToTwo(1.005)).toBe(1.01);
        expect(calculator.roundToTwo(2.345)).toBe(2.35);
    });

    test('keeps already rounded values', () => {
        expect(calculator.roundToTwo(14.99)).toBe(14.99);
    });
});

describe('RefundCalculator.isFixDiffAvailable', () => {
    test('is not available if amounts are identical', () => {
        expect(calculator.isFixDiffAvailable(10, 10)).toBe(false);
    });

    test('is available for a small rounding difference', () => {
        expect(calculator.isFixDiffAvailable(9.95, 10)).toBe(true);
        expect(calculator.isFixDiffAvailable(10.05, 10)).toBe(true);
    });

    test('is not available for a large difference', () => {
        expect(calculator.isFixDiffAvailable(9, 10)).toBe(false);
    });

    test('is available regardless of the sign of the difference', () => {
        expect(calculator.isFixDiffAvailable(10, 9.95)).toBe(true);
    });
});
