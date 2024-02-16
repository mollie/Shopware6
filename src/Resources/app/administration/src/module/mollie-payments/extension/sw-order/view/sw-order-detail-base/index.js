import template from './sw-order-detail-base.html.twig';
import OrderAttributes from '../../../../../../core/models/OrderAttributes';
import MollieShippingEvents from '../../../../components/mollie-ship-order/MollieShippingEvents';

// eslint-disable-next-line no-undef
const {Component, Mixin} = Shopware;

Component.override('sw-order-detail-base', {
    template,

    mixins: [
        Mixin.getByName('notification'),
    ],

    inject: [
        'MolliePaymentsRefundService',
        'MolliePaymentsShippingService',
    ],


    data() {
        return {
            remainingAmount: 0.0,
            refundedAmount: 0.0,
            voucherAmount: 0.0,
            refundAmountPending: 0.0,
            existingRefunds: [],
            shippedAmount: 0,
            shippedQuantity: 0,
        }
    },


    computed: {

        /**
         *
         * @returns {boolean}
         */
        isMollieOrder() {
            const attr = new OrderAttributes(this.order);
            return attr.isMollieOrder();
        },
    },

    watch: {
        order() {
            this.loadMollieData();
        },
    },



    methods: {

        /**
         *
         */
        createdComponent() {

            this.$super('createdComponent');

            this.$root.$on(MollieShippingEvents.EventShippedOrder, () => {
                this.loadMollieData();
            });
        },

        /**
         *
         */
        loadMollieData() {

            if (!this.isMollieOrder) {
                return;
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
                    this.existingRefunds = response.refunds;
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
    },
});
