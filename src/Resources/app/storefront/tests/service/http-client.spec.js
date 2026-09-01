import { afterEach, describe, expect, test, vi } from 'vitest';
import HttpClientService from '../../src/mollie-payments/services/http-client.service';

const client = new HttpClientService();

/**
 * Builds a minimal Response stand-in. Only the parts the service touches are
 * implemented: the content-type header and the json()/text() body readers.
 */
function fakeResponse(contentType, body) {
    return {
        headers: { get: () => contentType },
        json: () => (typeof body === 'string' ? Promise.reject(new SyntaxError('invalid json')) : Promise.resolve(body)),
        text: () => Promise.resolve(String(body)),
    };
}

afterEach(() => {
    vi.unstubAllGlobals();
});

describe('HttpClientService', () => {
    test('parses a json response', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue(fakeResponse('application/json', { token: 'abc' })));

        const onSuccess = vi.fn();
        const onError = vi.fn();

        await client.get('/mollie/test', onSuccess, onError);

        expect(onSuccess).toHaveBeenCalledWith({ token: 'abc' });
        expect(onError).not.toHaveBeenCalled();
    });

    test('passes a non-json response through as text', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue(fakeResponse('text/html', '<html></html>')));

        const onSuccess = vi.fn();
        const onError = vi.fn();

        await client.get('/mollie/test', onSuccess, onError);

        expect(onSuccess).toHaveBeenCalledWith('<html></html>');
        expect(onError).not.toHaveBeenCalled();
    });

    // Regression: a malformed json body used to throw out of the XHR onload
    // handler, so neither callback ran and the calling flow hung.
    test('reports a malformed json body to the error callback', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue(fakeResponse('application/json', 'not json')));

        const onSuccess = vi.fn();
        const onError = vi.fn();

        await client.post('/mollie/test', null, onSuccess, onError);

        expect(onSuccess).not.toHaveBeenCalled();
        expect(onError).toHaveBeenCalled();
    });

    test('reports a network failure to the error callback', async () => {
        vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new TypeError('network down')));

        const onSuccess = vi.fn();
        const onError = vi.fn();

        await client.post('/mollie/test', null, onSuccess, onError);

        expect(onSuccess).not.toHaveBeenCalled();
        expect(onError).toHaveBeenCalled();
    });
});
