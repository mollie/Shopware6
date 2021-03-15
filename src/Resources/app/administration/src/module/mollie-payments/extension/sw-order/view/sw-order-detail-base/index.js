import template from './sw-order-detail-base.html.twig';

const {Component} = Shopware;

Component.override('sw-order-detail-base', {
    template,

    data() {
        return {
            refundableAmount: 0.0,
            refundedAmount: 0.0,
            shippedAmount: 0,
            shippedItems: 0,
        }
    },

    inject: [
        'MolliePaymentsRefundService',
        'MolliePaymentsShippingService',
    ],

    mounted() {
        if (this.orderId !== '') {
            this.MolliePaymentsRefundService
                .total({orderId: this.orderId})
                .then((response) => {
                    this.refundableAmount = response.refundable;
                    this.refundedAmount = response.refunded;
                });

            this.MolliePaymentsShippingService
                .total({orderId: this.orderId})
                .then((response) => {
                    this.shippedAmount = response.amount;
                    this.shippedItems = response.items;
                });
        }
    }
});
