import { describe, expect, test } from 'vitest';
import getLatestTransaction from '../../../src/module/mollie-payments/extension/sw-order/getLatestTransaction';

describe('getLatestTransaction', () => {
    test('returns null for null/undefined/empty input', () => {
        expect(getLatestTransaction(null)).toBe(null);
        expect(getLatestTransaction(undefined)).toBe(null);
        expect(getLatestTransaction([])).toBe(null);
    });

    test('returns the transaction with the most recent createdAt', () => {
        const oldest = { id: 'a', createdAt: '2024-01-01T10:00:00.000Z' };
        const newest = { id: 'b', createdAt: '2024-03-01T10:00:00.000Z' };
        const middle = { id: 'c', createdAt: '2024-02-01T10:00:00.000Z' };

        expect(getLatestTransaction([oldest, newest, middle])).toBe(newest);
    });

    test('works with a single transaction', () => {
        const only = { id: 'a', createdAt: '2024-01-01T10:00:00.000Z' };

        expect(getLatestTransaction([only])).toBe(only);
    });

    test('accepts a DAL-like iterable collection', () => {
        const newest = { id: 'b', createdAt: '2024-03-01T10:00:00.000Z' };
        const collection = new Set([{ id: 'a', createdAt: '2024-01-01T10:00:00.000Z' }, newest]);

        expect(getLatestTransaction(collection)).toBe(newest);
    });

    test('treats a missing createdAt as the epoch (never the latest against a dated one)', () => {
        const dated = { id: 'a', createdAt: '2024-01-01T10:00:00.000Z' };
        const undatedFirst = { id: 'b' };

        expect(getLatestTransaction([undatedFirst, dated])).toBe(dated);
    });
});
