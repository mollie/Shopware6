import { expect, test } from 'vitest'
import ProductAttributes from '../../../src/core/models/ProductAttributes';


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

test('Missing Attributes are not added if no Mollie data exists initially', () => {
    const customFields = {
        'other_data': 'sample',
    };

    const attributes = new ProductAttributes(customFields);

    expect(attributes.toArray(customFields)).toBe(customFields);
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
