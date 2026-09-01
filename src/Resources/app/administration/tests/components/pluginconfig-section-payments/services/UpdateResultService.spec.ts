import { describe, expect, test } from 'vitest';
import UpdateResultService from '../../../../src/module/mollie-payments/components/mollie-pluginconfig-section-payments/services/UpdateResultService';

const service = new UpdateResultService();

describe('UpdateResultService.isSuccess', () => {
    test('is success when the flag is exactly true', () => {
        expect(service.isSuccess({ success: true })).toBe(true);
    });

    test('is no success when the flag is false', () => {
        expect(service.isSuccess({ success: false })).toBe(false);
    });

    test('is no success when the flag is missing', () => {
        expect(service.isSuccess({})).toBe(false);
    });
});

describe('UpdateResultService.buildErrorMessage', () => {
    test('appends the exception details to the failure label', () => {
        const message = service.buildErrorMessage({ success: false, message: 'API down' }, 'Update failed');

        expect(message).toBe('Update failed\n\nException:\nAPI down');
    });
});
