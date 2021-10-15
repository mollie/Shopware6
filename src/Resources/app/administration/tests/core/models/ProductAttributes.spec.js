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
    const customFields = {
        'mollie_payments': {
            'voucher_type': '2',
        }
    };

    const attributes = new ProductAttributes(customFields);
    expect(attributes.getVoucherType()).toBe('2');
});
