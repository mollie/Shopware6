import template from './sw-order-detail-base.html.twig';

const { Component } = Shopware;

Component.override('sw-order-detail-base', {
    template,

    props: {
        orderId: {
            type: String,
            required: true
        },
    },

    data() {
        return {
            refundedAmount: 0.0,
            refundedItems: 0,
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
            this.MolliePaymentsRefundService.total({
                orderId: this.orderId
            })
                .then((response) => {
                    this.refundedAmount = response.amount;
                    this.refundedItems = response.items;
                });

            this.MolliePaymentsShippingService.total({
                orderId: this.orderId
            })
                .then((response) => {
                    this.shippedAmount = response.amount;
                    this.shippedItems = response.items;
                });
        }
    }
});