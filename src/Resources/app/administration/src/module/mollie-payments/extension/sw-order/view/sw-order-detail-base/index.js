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
            shippedQuantity: 0,
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

                this.MolliePaymentsRefundService.getRefundManagerData(
                    {
                        orderId: this.order.id,
                    })
                    .then((response) => {
                        this.remainingAmount = response.totals.remaining;
                        this.refundedAmount = response.totals.refunded;
                        this.voucherAmount = response.totals.voucherAmount;
                        this.refundAmountPending = response.totals.pendingRefunds;
                        this.refunds = response.refunds;
                    })
                    .catch((response) => {
                        this.createNotificationError({
                            message: response.message,
                        });
                    });

                this.MolliePaymentsShippingService
                    .total({orderId: this.order.id})
                    .then((response) => {
                        this.shippedAmount = Math.round(response.amount * 100) / 100;
                        this.shippedQuantity = response.quantity;
                    });

            }
        },
    },
});
