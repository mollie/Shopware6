import template from './sw-order-detail-general.html.twig';

// eslint-disable-next-line no-undef
const {Component, Mixin} = Shopware;

Component.override('sw-order-detail-general', {
    template,

    mixins: [
        Mixin.getByName('notification'),
    ],
    props: {
        showRefundModal: false,
        showShipOrderModal: false,
    },
    data() {
        return {
            remainingAmount: 0.0,
            refundedAmount: 0.0,
            voucherAmount: 0.0,
            refundAmountPending: 0.0,
            refunds: [],
            shippedAmount: 0,
            shippedQuantity: 0,

            isRefundManagerPossible: false,
            isShippingPossible: false,
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
            if (!this.isMollieOrder) {
                return
            }

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


        },
        onOpenRefundManager() {
            this.showRefundModal = true;
        },

        onToggleRefundManagerModal(showRefundManagerModal) {
            this.showRefundModal = showRefundManagerModal;
        },
        onToggleShipOrderModal(shipOrderModal) {
            this.showShipOrderModal = shipOrderModal;
        },
        onOpenShipOrderModal() {
            this.showShipOrderModal = true;
        },
        onRefundManagerPossible(refundManagerPossible) {
            this.isRefundManagerPossible = refundManagerPossible;
        },
        onShippingPossible(shippingPossible) {

            this.isShippingPossible = shippingPossible;
        },
    },
});
