import { describe, expect, test } from 'vitest';
import ApiKeyTestService from '../../../../src/module/mollie-payments/components/mollie-pluginconfig-section-api/services/ApiKeyTestService';

const service = new ApiKeyTestService();

const labels = {
    apiKey: 'API key',
    isValid: 'is valid',
    isInvalid: 'is invalid',
};

describe('ApiKeyTestService.isValid', () => {
    test('is valid when the flag is exactly true', () => {
        expect(service.isValid({ key: 'live_x', mode: 'live', valid: true })).toBe(true);
    });

    test('is invalid when the flag is false', () => {
        expect(service.isValid({ key: 'live_x', mode: 'live', valid: false })).toBe(false);
    });
});

describe('ApiKeyTestService.buildResultMessage', () => {
    test('builds the message for a valid key', () => {
        const message = service.buildResultMessage({ key: 'live_abc', mode: 'live', valid: true }, labels);

        expect(message).toBe('API key "live_abc" (live) is valid.');
    });

    test('builds the message for an invalid key', () => {
        const message = service.buildResultMessage({ key: 'test_abc', mode: 'test', valid: false }, labels);

        expect(message).toBe('API key "test_abc" (test) is invalid.');
    });
});
