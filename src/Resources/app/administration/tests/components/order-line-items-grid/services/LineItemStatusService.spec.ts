import { beforeEach, describe, expect, test } from 'vitest';
import LineItemStatusService from '../../../../src/module/mollie-payments/components/mollie-order-line-items-grid/services/LineItemStatusService';

const service = new LineItemStatusService();

describe('LineItemStatusService shipping lookups', () => {
    const shippingStatus = {
        'item-1': { shippableQuantity: 3, quantityShipped: 2 },
    };

    test('returns the shippable quantity for a known item', () => {
        expect(service.shippableQuantity(shippingStatus, 'item-1')).toBe(3);
    });

    test('returns the shipped quantity for a known item', () => {
        expect(service.shippedQuantity(shippingStatus, 'item-1')).toBe(2);
    });

    test('returns the placeholder if the status map is null', () => {
        expect(service.shippableQuantity(null, 'item-1')).toBe('~');
        expect(service.shippedQuantity(null, 'item-1')).toBe('~');
    });

    test('returns the placeholder for an unknown item', () => {
        expect(service.shippableQuantity(shippingStatus, 'missing')).toBe('~');
    });

    test('keeps a shippable quantity of 0 instead of the placeholder', () => {
        expect(service.shippableQuantity({ 'item-1': { shippableQuantity: 0, quantityShipped: 0 } }, 'item-1')).toBe(0);
    });
});

describe('LineItemStatusService cancel lookups', () => {
    const cancelStatus = {
        'item-1': { quantityCanceled: 1, isCancelable: true },
        'item-2': { quantityCanceled: 0, isCancelable: false },
    };

    test('returns the canceled quantity for a known item', () => {
        expect(service.canceledQuantity(cancelStatus, 'item-1')).toBe(1);
    });

    test('returns the placeholder for canceled quantity if unknown', () => {
        expect(service.canceledQuantity(null, 'item-1')).toBe('~');
        expect(service.canceledQuantity(cancelStatus, 'missing')).toBe('~');
    });

    test('reports cancelable state', () => {
        expect(service.isCancelable(cancelStatus, 'item-1')).toBe(true);
        expect(service.isCancelable(cancelStatus, 'item-2')).toBe(false);
    });

    test('is not cancelable if the status is missing', () => {
        expect(service.isCancelable(null, 'item-1')).toBe(false);
        expect(service.isCancelable(cancelStatus, 'missing')).toBe(false);
    });
});

describe('LineItemStatusService.applyCancelResponse', () => {
    let cancelStatus;

    beforeEach(() => {
        cancelStatus = {
            'item-1': { mollieId: 'mol-1', quantityCanceled: 1, cancelableQuantity: 3, isCancelable: true },
            'item-2': { mollieId: 'mol-2', quantityCanceled: 0, cancelableQuantity: 5, isCancelable: true },
        };
    });

    test('returns the map unchanged for an unsuccessful response', () => {
        expect(service.applyCancelResponse(cancelStatus, { success: false })).toBe(cancelStatus);
    });

    test('returns the map unchanged when data is missing', () => {
        expect(service.applyCancelResponse(cancelStatus, { success: true })).toBe(cancelStatus);
    });

    test('updates the matching mollie line and leaves others untouched', () => {
        const result = service.applyCancelResponse(cancelStatus, {
            success: true,
            data: { id: 'mol-1', quantity: 2 },
        });

        expect(result['item-1']).toEqual({
            mollieId: 'mol-1',
            quantityCanceled: 3,
            cancelableQuantity: 1,
            isCancelable: true,
        });
        expect(result['item-2']).toEqual(cancelStatus['item-2']);
    });

    test('marks an item as no longer cancelable once fully canceled', () => {
        const result = service.applyCancelResponse(cancelStatus, {
            success: true,
            data: { id: 'mol-1', quantity: 3 },
        });

        expect(result['item-1'].cancelableQuantity).toBe(0);
        expect(result['item-1'].isCancelable).toBe(false);
    });

    test('never produces a negative cancelable quantity', () => {
        const result = service.applyCancelResponse(cancelStatus, {
            success: true,
            data: { id: 'mol-1', quantity: 99 },
        });

        expect(result['item-1'].cancelableQuantity).toBe(0);
    });
});

describe('LineItemStatusService.buildCancelData', () => {
    test('merges the stored status with the identifying item fields', () => {
        const cancelStatus = { 'item-1': { mollieId: 'mol-1', isCancelable: true } };

        const result = service.buildCancelData(cancelStatus, { id: 'item-1', label: 'Product 1', payload: { a: 1 } });

        expect(result).toEqual({
            mollieId: 'mol-1',
            isCancelable: true,
            shopwareItemId: 'item-1',
            label: 'Product 1',
            payload: { a: 1 },
        });
    });

    test('does not mutate the stored status', () => {
        const status = { mollieId: 'mol-1' };
        const cancelStatus = { 'item-1': status };

        service.buildCancelData(cancelStatus, { id: 'item-1', label: 'x' });

        expect(status).toEqual({ mollieId: 'mol-1' });
    });

    test('returns an empty object for an unknown item', () => {
        expect(service.buildCancelData(null, { id: 'item-1' })).toEqual({});
        expect(service.buildCancelData({}, { id: 'item-1' })).toEqual({});
    });
});
