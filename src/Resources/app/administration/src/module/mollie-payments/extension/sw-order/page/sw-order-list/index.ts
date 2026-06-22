import template from './sw-order-list.html.twig';
import OrderAttributes from '../../../../../../core/models/OrderAttributes';

const { Component } = Shopware;

interface SwOrderListOverride {
    [key: string]: any;
}

const overrideConfig: ThisType<SwOrderListOverride> = {
    template,

    computed: {
        orderCriteria() {
            const baseCriteria = this.$super('orderCriteria');
            baseCriteria.addAssociation('transactions.paymentMethod');

            return baseCriteria;
        },
    },

    methods: {
        getOrderColumns() {
            const cols = this.$super('getOrderColumns');

            // add a custom, hideable mollie column
            cols.push({
                property: 'mollie',
                label: 'mollie-payments.sw-order-list.columns.mollie',
                allowResize: true,
                primary: false,
            });

            return cols;
        },

        isMollie(order: any): boolean {
            const attributes = new OrderAttributes(order);

            return attributes.getOrderId() !== '' || attributes.getPaymentId() !== '';
        },

        getMollieId(order: any): string {
            const attributes = new OrderAttributes(order);

            return attributes.getOrderId() || attributes.getPaymentId() || '';
        },

        isMollieSubscription(order: any): boolean {
            const attributes = new OrderAttributes(order);

            return attributes.getSwSubscriptionId() !== '';
        },
    },
};

Component.override('sw-order-list', overrideConfig);
