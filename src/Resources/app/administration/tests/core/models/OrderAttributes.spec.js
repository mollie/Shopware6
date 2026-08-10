import { expect, test } from 'vitest'
import OrderAttributes from '../../../src/core/models/OrderAttributes';

function buildOrder(transaction, customFields = null) {
    return {
        customFields: customFields,
        transactions: [transaction],
    };
}

test('Attributes do not crash with NULL order', () => {
    const attributes = new OrderAttributes(null);
    expect(attributes.isMollieOrder()).toBe(false);
});

test('Attributes do not crash with an order without transactions', () => {
    const attributes = new OrderAttributes({});
    expect(attributes.isMollieOrder()).toBe(false);
});

test('Attributes do not crash with an order without a payment method', () => {
    const attributes = new OrderAttributes(buildOrder({ createdAt: '2026-01-01' }));
    expect(attributes.isMollieOrder()).toBe(false);
});

test('Order is not a Mollie order for a foreign payment method', () => {
    const transaction = {
        createdAt: '2026-01-01',
        paymentMethod: {
            customFields: {},
            translated: { customFields: {} },
        },
    };

    const attributes = new OrderAttributes(buildOrder(transaction));
    expect(attributes.isMollieOrder()).toBe(false);
});

test('Order is a Mollie order in the system language', () => {
    const customFields = { mollie_payment_method_name: 'ideal' };
    const transaction = {
        createdAt: '2026-01-01',
        paymentMethod: {
            customFields: customFields,
            translated: { customFields: customFields },
        },
    };

    const attributes = new OrderAttributes(buildOrder(transaction));
    expect(attributes.isMollieOrder()).toBe(true);
});

test('Order is a Mollie order if only the translated custom fields are filled', () => {
    const transaction = {
        createdAt: '2026-01-01',
        paymentMethod: {
            customFields: null,
            translated: { customFields: { mollie_payment_method_name: 'ideal' } },
        },
    };

    const attributes = new OrderAttributes(buildOrder(transaction));
    expect(attributes.isMollieOrder()).toBe(true);
});

test('Order is a Mollie order if the entity has no translated node at all', () => {
    const transaction = {
        createdAt: '2026-01-01',
        paymentMethod: {
            customFields: { mollie_payment_method_name: 'ideal' },
        },
    };

    const attributes = new OrderAttributes(buildOrder(transaction));
    expect(attributes.isMollieOrder()).toBe(true);
});

test('Mollie IDs are read from the latest transaction', () => {
    const paymentMethod = {
        translated: { customFields: { mollie_payment_method_name: 'ideal' } },
    };

    const order = {
        customFields: null,
        transactions: [
            {
                createdAt: '2026-01-01',
                paymentMethod: paymentMethod,
                customFields: { mollie_payments: { orderId: 'ord_old', id: 'tr_old' } },
            },
            {
                createdAt: '2026-02-01',
                paymentMethod: paymentMethod,
                customFields: { mollie_payments: { orderId: 'ord_new', id: 'tr_new' } },
            },
        ],
    };

    const attributes = new OrderAttributes(order);

    expect(attributes.isMollieOrder()).toBe(true);
    expect(attributes.getOrderId()).toBe('ord_new');
    expect(attributes.getPaymentId()).toBe('tr_new');
});

test('Mollie IDs from the order custom fields win over the transaction', () => {
    const transaction = {
        createdAt: '2026-01-01',
        paymentMethod: {
            translated: { customFields: { mollie_payment_method_name: 'ideal' } },
        },
        customFields: { mollie_payments: { orderId: 'ord_transaction', id: 'tr_transaction' } },
    };

    const orderCustomFields = {
        mollie_payments: {
            order_id: 'ord_order',
            payment_id: 'tr_order',
            swSubscriptionId: 'sub_123',
            third_party_payment_id: 'ref_123',
        },
    };

    const attributes = new OrderAttributes(buildOrder(transaction, orderCustomFields));

    expect(attributes.getOrderId()).toBe('ord_order');
    expect(attributes.getPaymentId()).toBe('tr_order');
    expect(attributes.getMollieID()).toBe('ord_order');
    expect(attributes.isSubscription()).toBe(true);
    expect(attributes.getSwSubscriptionId()).toBe('sub_123');
    expect(attributes.getPaymentRef()).toBe('ref_123');
});
