import template from './sw-order-user-card.html.twig';

const { Component } = Shopware;

Component.override('sw-order-user-card', {
    template,

    computed: {
        mollieOrderId() {
            if (
                !!this.currentOrder
                && !!this.currentOrder.customFields
                && !!this.currentOrder.customFields.mollie_payments
                && !!this.currentOrder.customFields.mollie_payments.order_id
            ) {
                return this.currentOrder.customFields.mollie_payments.order_id;
            }

            return null;
        }
    }
});