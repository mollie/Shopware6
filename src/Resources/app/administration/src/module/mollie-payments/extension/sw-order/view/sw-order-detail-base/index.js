import template from './sw-order-detail-base.html.twig';

// eslint-disable-next-line no-undef
const {Component, Mixin} = Shopware;

Component.override('sw-order-detail-base', {
    template,

    mixins: [
        Mixin.getByName('notification'),
    ],

    data() {
        return {
            remainingAmount: 0.0,
            refundedAmount: 0.0,
            voucherAmount: 0.0,
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

    computed: {
        isMollieOrder() {
            return (this.order.customFields !== null && 'mollie_payments' in this.order.customFields);
        },
    },

    watch: {
        order() {
            this.getMollieData();
        },
    },

    methods: {
        getMollieData() {
            if (this.isMollieOrder) {
                this.MolliePaymentsRefundService
                    .total({orderId: this.order.id})
                    .then((response) => {
                        this.remainingAmount = response.remaining;
                        this.refundedAmount = response.refunded;
                        this.voucherAmount = response.voucherAmount;
                    })
                    .catch((response) => {
                        this.createNotificationError({
                            message: response.message,
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
                                this.refundAmountPending += (refund.amount.value || 0);
                            }
                        });
                    })
                    .catch((response) => {
                        this.createNotificationError({
                            message: response.message,
                        });
                    });
            }
        },
    },
});
