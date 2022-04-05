import ProductAttributes from "../../../src/core/models/ProductAttributes";


test('Attributes do not crash with NULL custom fields', () => {
    const attributes = new ProductAttributes(null);
    expect(attributes.getVoucherType()).toBe('');
});

test('Attributes do not crash with empty array as custom fields', () => {
    const attributes = new ProductAttributes({});
    expect(attributes.getVoucherType()).toBe('');
});

test('Attributes do not crash if our Mollie root node does not exist', () => {
    const customFields = {
        'other_data': 'sample',
    };

    const attributes = new ProductAttributes(customFields);
    expect(attributes.getVoucherType()).toBe('');
});

// --------------------------------------------------------------------------------------------------

test('VoucherType is correctly loaded from custom fields', () => {

    const product = {
        customFields: {
            'mollie_payments_product_voucher_type': '2',
        },
    };

    const attributes = new ProductAttributes(product);
    expect(attributes.getVoucherType()).toBe('2');
});

test('Invalid VoucherType returns empty string', () => {

    const product = {}
    const attributes = new ProductAttributes(product);

    attributes.setVoucherType('a');

    expect(attributes.getVoucherType()).toBe('');
});

test('VoucherType is converted to String', () => {

    const product = {}
    const attributes = new ProductAttributes(product);

    attributes.setVoucherType(1);

    expect(attributes.getVoucherType()).toBe('1');
});

test('VoucherType can be cleared again', () => {

    const product = {
        customFields: {
            'mollie_payments_product_voucher_type': '2',
        },
    };

    const attributes = new ProductAttributes(product);
    attributes.clearVoucherType();

    expect(attributes.getVoucherType()).toBe('');
});

test('Product Attributes hasData works correctly', () => {

    const product = {};
    const attributes = new ProductAttributes(product);

    // set some data
    attributes.setVoucherType('2');

    expect(attributes.hasData()).toBe(true);

    // now clear some data
    attributes.clearVoucherType();

    expect(attributes.hasData()).toBe(false);
});

// --------------------------------------------------------------------------------------------------

test('Subscription data is correctly loaded from custom fields', () => {

    const product = {
        customFields: {
            'mollie_payments_product_subscription_enabled': true,
            'mollie_payments_product_subscription_interval': 3,
            'mollie_payments_product_subscription_interval_unit': 'weeks',
            'mollie_payments_product_subscription_repetition': 2,
        },
    };

    const attributes = new ProductAttributes(product);

    expect(attributes.isSubscriptionProduct()).toBe(true);
    expect(attributes.getSubscriptionInterval()).toBe(3);
    expect(attributes.getSubscriptionIntervalUnit()).toBe('weeks');
    expect(attributes.getSubscriptionRepetition()).toBe(2);
});

test('Invalid Subscription data leads to disabled values', () => {

    const product = {}
    const attributes = new ProductAttributes(product);

    attributes.setSubscriptionProduct('a');
    attributes.setSubscriptionInterval('a');
    attributes.setSubscriptionIntervalUnit('a');
    attributes.setSubscriptionRepetition('a');

    expect(attributes.isSubscriptionProduct()).toBe(false);
    expect(attributes.getSubscriptionInterval()).toBe('');
    expect(attributes.getSubscriptionIntervalUnit()).toBe('');
    expect(attributes.getSubscriptionRepetition()).toBe('');
});

test('Subscription data can be cleared again', () => {

    const attributes = new ProductAttributes({});

    attributes.setSubscriptionInterval(3);
    attributes.setSubscriptionIntervalUnit('weeks');
    attributes.setSubscriptionRepetition(2);

    expect(attributes.getSubscriptionInterval()).toBe(3);
    expect(attributes.getSubscriptionIntervalUnit()).toBe('weeks');
    expect(attributes.getSubscriptionRepetition()).toBe(2);

    attributes.clearSubscriptionInterval();
    attributes.clearSubscriptionIntervalUnit();
    attributes.clearSubscriptionRepetition();

    expect(attributes.getSubscriptionInterval()).toBe('');
    expect(attributes.getSubscriptionIntervalUnit()).toBe('');
    expect(attributes.getSubscriptionRepetition()).toBe('');
});