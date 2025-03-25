import { expect, test } from 'vitest'
import CsrfAjaxModeHelper from '../../src/mollie-payments/helper/csrf-ajax-mode.helper';

test('csrf mode is undefined', () => {
    const fakeConfig = {};
    const csrfMode = new CsrfAjaxModeHelper(fakeConfig.csrf);

    expect(csrfMode.isActive()).toBe(false);

});
test('csrf properties are not set',() =>{
    const fakeConfig = {
        csrf: {},
    };
    const csrfMode = new CsrfAjaxModeHelper(fakeConfig.csrf);
    expect(csrfMode.isActive()).toBe(false);
});

test('csrf mode is disabled', () => {
    const fakeConfig = {
        csrf: {
            enabled: false,
        },
    };
    const csrfMode = new CsrfAjaxModeHelper(fakeConfig.csrf);
    expect(csrfMode.isActive()).toBe(false);
});

test('csrf mode is not ajax', () => {
    const fakeConfig = {
        csrf: {
            enabled: true,
            mode:'twig',
        },
    };
    const csrfMode = new CsrfAjaxModeHelper(fakeConfig.csrf);
    expect(csrfMode.isActive()).toBe(false);
});
test('csrf is active', () => {
    const fakeConfig = {
        csrf: {
            enabled: true,
            mode:'ajax',
        },
    };
    const csrfMode = new CsrfAjaxModeHelper(fakeConfig.csrf);
    expect(csrfMode.isActive()).toBe(true);
});
