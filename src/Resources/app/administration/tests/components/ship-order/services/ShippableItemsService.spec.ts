import { describe, expect, test } from 'vitest';
import ShippableItemsService from '../../../../src/module/mollie-payments/components/mollie-ship-order/services/ShippableItemsService';

const service = new ShippableItemsService();

describe('ShippableItemsService.buildShippableLineItems', () => {
    const lineItems = [
        { id: 'item-1', label: 'Shirt' },
        { id: 'item-2', label: 'Shoes' },
    ];

    test('maps line items with their shippable status', () => {
        const status = {
            'item-1': { mollieId: 'mol-1', shippableQuantity: 3 },
            'item-2': { mollieId: 'mol-2', shippableQuantity: 0 },
        };

        const result = service.buildShippableLineItems(lineItems, status);

        expect(result[0]).toEqual({
            id: 'item-1',
            mollieId: 'mol-1',
            label: 'Shirt',
            quantity: 3,
            originalQuantity: 3,
            selected: false,
        });
        expect(result[1].quantity).toBe(0);
    });

    test('defaults quantity to 0 and mollieId to null when there is no status', () => {
        const result = service.buildShippableLineItems(lineItems, {});

        expect(result[0].quantity).toBe(0);
        expect(result[0].originalQuantity).toBe(0);
        expect(result[0].mollieId).toBe(null);
    });

    test('handles a null status map', () => {
        const result = service.buildShippableLineItems(lineItems, null);

        expect(result.map((item) => item.quantity)).toEqual([0, 0]);
    });
});

describe('ShippableItemsService.collectSelectedItems', () => {
    test('returns only selected items reduced to id and quantity', () => {
        const items = [
            { id: 'a', mollieId: null, label: 'A', quantity: 2, originalQuantity: 2, selected: true },
            { id: 'b', mollieId: null, label: 'B', quantity: 1, originalQuantity: 1, selected: false },
            { id: 'c', mollieId: null, label: 'C', quantity: 5, originalQuantity: 5, selected: true },
        ];

        expect(service.collectSelectedItems(items)).toEqual([
            { id: 'a', quantity: 2 },
            { id: 'c', quantity: 5 },
        ]);
    });

    test('returns an empty array when nothing is selected', () => {
        const items = [{ id: 'a', mollieId: null, label: 'A', quantity: 2, originalQuantity: 2, selected: false }];

        expect(service.collectSelectedItems(items)).toEqual([]);
    });
});
