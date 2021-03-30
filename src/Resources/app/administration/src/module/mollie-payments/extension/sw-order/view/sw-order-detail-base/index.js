import template from './sw-order-detail-base.html.twig';

const {Component, Mixin} = Shopware;

Component.override('sw-order-detail-base', {
    template,

    mixins: [
        Mixin.getByName('notification')
    ],

    data() {
        return {
            refundableAmount: 0.0,
            refundedAmount: 0.0,
            refundAmountPending: 0.0,
            refunds: [],
            shippedAmount: 0,
            shippedItems: 0,
        }
    },

    inject: [
        'MolliePaymentsRefundService',
        'MolliePaymentsShippingService',
    ],

    watch: {
        order() {
            this.getMollieData();
        }
    },

    methods: {
        getMollieData() {
            if (this.order.id !== '') {
                this.MolliePaymentsRefundService
                    .total({orderId: this.order.id})
                    .then((response) => {
                        this.refundableAmount = response.refundable;
                        this.refundedAmount = response.refunded;
                    })
                    .catch((response) => {
                        this.createNotificationError({
                            message: response.message
                        });
                    });

                this.MolliePaymentsShippingService
                    .total({orderId: this.order.id})
                    .then((response) => {
                        this.shippedAmount = response.amount;
                        this.shippedItems = response.items;
                    });

                this.MolliePaymentsRefundService
                    .list({orderId: this.order.id})
                    .then((response) => {
                        return this.refunds = response;
                    })
                    .then((refunds) => {
                        this.refundAmountPending = 0.0;
                        refunds.forEach((refund) => {
                            if(refund.isPending || refund.isQueued) {
                                this.refundAmountPending += refund.amount.value;
                            }
                        });
                    })
                    .catch((response) => {
                        this.createNotificationError({
                            message: response.message
                        });
                    });
            }
        }
    }
});
