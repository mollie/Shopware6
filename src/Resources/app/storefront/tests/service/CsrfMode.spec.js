import CsrfMode from "../../src/mollie-payments/services/CsrfMode";

test('csrf mode is undefined', () => {
    const fakeConfig = {};
    const csrfMode = new CsrfMode(fakeConfig.csrf);

    expect(csrfMode.isActive()).toBe(false);

});
test('csrf properties are not set',() =>{
    const fakeConfig = {
        csrf: {},
    };
    const csrfMode = new CsrfMode(fakeConfig.csrf);
    expect(csrfMode.isActive()).toBe(false);
});

test('csrf mode is disabled', () => {
    const fakeConfig = {
        csrf: {
            enabled: false,
        },
    };
    const csrfMode = new CsrfMode(fakeConfig.csrf);
    expect(csrfMode.isActive()).toBe(false);
});

test('csrf mode is not ajax', () => {
    const fakeConfig = {
        csrf: {
            enabled: true,
            mode:'twig',
        },
    };
    const csrfMode = new CsrfMode(fakeConfig.csrf);
    expect(csrfMode.isActive()).toBe(false);
});
test('csrf is active', () => {
    const fakeConfig = {
        csrf: {
            enabled: true,
            mode:'ajax',
        },
    };
    const csrfMode = new CsrfMode(fakeConfig.csrf);
    expect(csrfMode.isActive()).toBe(true);
});
