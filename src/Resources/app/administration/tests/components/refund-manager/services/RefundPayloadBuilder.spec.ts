import { describe, expect, test } from 'vitest';
import RefundPayloadBuilder from '../../../../src/module/mollie-payments/components/mollie-refund-manager/services/RefundPayloadBuilder';

const builder = new RefundPayloadBuilder();

function createItem(overrides = {}) {
    return {
        shopware: { id: 'item-1', label: 'Product 1' },
        refundQuantity: 2,
        refundAmount: 20,
        resetStock: 1,
        ...overrides,
    };
}

describe('RefundPayloadBuilder.buildItems', () => {
    test('returns an empty array for no items', () => {
        expect(builder.buildItems([])).toEqual([]);
    });

    test('maps a line item to the refund payload structure', () => {
        const result = builder.buildItems([createItem()]);

        expect(result).toEqual([
            {
                id: 'item-1',
                label: 'Product 1',
                quantity: 2,
                amount: 20,
                resetStock: 1,
            },
        ]);
    });

    test('maps multiple items while keeping order', () => {
        const result = builder.buildItems([
            createItem({ shopware: { id: 'a', label: 'A' } }),
            createItem({ shopware: { id: 'b', label: 'B' } }),
        ]);

        expect(result.map((item) => item.id)).toEqual(['a', 'b']);
    });
});

describe('RefundPayloadBuilder.isRefundSuccess', () => {
    test('is success if the response carries a created refund', () => {
        expect(builder.isRefundSuccess({ refund: { id: 're_123' } })).toBe(true);
    });

    test('is no success for an empty response', () => {
        expect(builder.isRefundSuccess({})).toBe(false);
    });

    test('is no success if the refund node has no id', () => {
        expect(builder.isRefundSuccess({ refund: {} })).toBe(false);
    });

    test('is no success if the refund id is not a string', () => {
        // @ts-expect-error testing defensive runtime behaviour
        expect(builder.isRefundSuccess({ refund: { id: 123 } })).toBe(false);
    });

    test('a success flag alone is not a created refund', () => {
        // @ts-expect-error the backend no longer answers refunds with a bare success flag
        expect(builder.isRefundSuccess({ success: true })).toBe(false);
    });
});
