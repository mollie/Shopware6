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

test('VoucherType is correctly loaded from custom fields', () => {

    const product = {
        customFields: {
            'mollie_payments': {
                'voucher_type': '2',
            }
        }
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
            'mollie_payments': {
                'voucher_type': '2',
            }
        }
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
