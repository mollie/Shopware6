import { describe, expect, test } from 'vitest';
import CancelItemService from '../../../../src/module/mollie-payments/components/mollie-cancel-item/services/CancelItemService';

const service = new CancelItemService();

describe('CancelItemService.buildCancelRequest', () => {
    test('maps the item and user input to the request payload', () => {
        const result = service.buildCancelRequest({ shopwareItemId: 'line-1' }, 2, true);

        expect(result).toEqual({
            shopwareLineId: 'line-1',
            quantity: 2,
            resetStock: true,
        });
    });

    test('keeps resetStock false when not requested', () => {
        const result = service.buildCancelRequest({ shopwareItemId: 'line-2' }, 1, false);

        expect(result.resetStock).toBe(false);
    });
});

describe('CancelItemService.isCancelSuccess', () => {
    test('is success when the success flag is true', () => {
        expect(service.isCancelSuccess({ success: true })).toBe(true);
    });

    test('is no success when the success flag is false', () => {
        expect(service.isCancelSuccess({ success: false })).toBe(false);
    });

    test('is no success when the flag is missing', () => {
        expect(service.isCancelSuccess({})).toBe(false);
    });
});

describe('CancelItemService.getFailureSnippetKey', () => {
    test('builds the snippet key from the response message', () => {
        expect(service.getFailureSnippetKey({ message: 'already_canceled' })).toBe(
            'mollie-payments.modals.cancel.item.failed.already_canceled',
        );
    });
});
