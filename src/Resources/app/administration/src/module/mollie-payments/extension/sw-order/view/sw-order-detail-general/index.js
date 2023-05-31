import template from './sw-order-detail-general.html.twig';
import './sw-order-detail-general.scss';
import OrderAttributes from '../../../../../../core/models/OrderAttributes';

// eslint-disable-next-line no-undef
const {Component, Mixin} = Shopware;

Component.override('sw-order-detail-general', {
    template,

    mixins: [
        Mixin.getByName('notification'),
    ],

    data() {
        return {
            configShowRefundManager: false,
            remainingAmount: 0.0,
            refundedAmount: 0.0,
            voucherAmount: 0.0,
            refundAmountPending: 0.0,
            refunds: [],
            shippedAmount: 0,
            shippedQuantity: 0,

            isRefundManagerPossible: false,
            isShippingPossible: false,
            molliePaymentUrl: '',
            molliePaymentUrlCopied: false,
        }
    },

    inject: [
        'MolliePaymentsRefundService',
        'MolliePaymentsShippingService',
        'MolliePaymentsOrderService',
        'MolliePaymentsConfigService',
    ],

    computed: {
        isMollieOrder() {
            return (this.order.customFields !== null && 'mollie_payments' in this.order.customFields);
        },

        /**
         *
         * @returns {string|*}
         */
        creditCardLabel() {
            return this._creditCardData().getLabel()
        },

        /**
         *
         * @returns {string|*}
         */
        creditCardNumber() {
            return '**** **** **** ' + this._creditCardData().getNumber()
        },

        /**
         *
         * @returns {string|*}
         */
        creditCardHolder() {
            return this._creditCardData().getHolder()
        },

        /**
         *
         * @returns {null|string|*}
         */
        mollieOrderId() {

            const orderAttributes = new OrderAttributes(this.order);

            if (orderAttributes.getOrderId() !== '') {
                return orderAttributes.getOrderId();
            }

            if (orderAttributes.getPaymentId() !== '') {
                return orderAttributes.getPaymentId();
            }

            return null;
        },
        mollieThirdPartyPaymentId() {
            if (
                !!this.order
                && !!this.order.customFields
                && !!this.order.customFields.mollie_payments
                && !!this.order.customFields.mollie_payments.third_party_payment_id
            ) {
                return this.order.customFields.mollie_payments.third_party_payment_id;
            }

            return null;
        },

        /**
         *
         * @returns {null|*}
         */
        isSubscription() {
            const orderAttributes = new OrderAttributes(this.order);
            return (orderAttributes.getSwSubscriptionId() !== '');
        },

        /**
         *
         * @returns {string|*}
         */
        subscriptionId() {
            const orderAttributes = new OrderAttributes(this.order);
            return orderAttributes.getSwSubscriptionId();
        },

        /**
         *
         * @returns {boolean}
         */
        hasPaymentLink() {
            return this.molliePaymentUrl !== '';
        },

        hasCreditCardData() {
            return this._creditCardData().hasCreditCardData();
        },
    },

    watch: {
        order() {
            this.getMollieData();
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {

        createdComponent() {
            this.molliePaymentUrl = '';

            if (this.mollieOrderId) {
                this.MolliePaymentsOrderService.getPaymentUrl({orderId: this.order.id}).then(response => {
                    this.molliePaymentUrl = (response.url !== null) ? response.url : '';
                });

                this.MolliePaymentsConfigService.getRefundManagerConfig(this.order.salesChannelId).then((response) => {
                    this.configShowRefundManager = response.enabled;
                });
            }
        },

        /**+
         * @returns {CreditcardAttributes|*|null}
         * @private
         */
        _creditCardData() {
            const orderAttributes = new OrderAttributes(this.order);
            return orderAttributes.getCreditCardAttributes();
        },

        copyPaymentUrlToClipboard() {
            // eslint-disable-next-line no-undef
            Shopware.Utils.dom.copyToClipboard(this.molliePaymentUrl);
            this.molliePaymentUrlCopied = true;
        },

        onMolliePaymentUrlProcessFinished(value) {
            this.molliePaymentUrlCopied = value;
        },

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
            this.$refs.swOrderLineItemsGrid.onOpenRefundManager();
        },

        onOpenShipOrderModal() {
            this.$refs.swOrderLineItemsGrid.onOpenShipOrderModal();
        },

        onRefundManagerPossible(refundManagerPossible) {

            if (!this.configShowRefundManager) {
                this.isRefundManagerPossible = false;
                return;
            }

            this.isRefundManagerPossible = refundManagerPossible;
        },

        /**
         *
         * @param shippingPossible
         */
        onShippingPossible(shippingPossible) {
            this.isShippingPossible = shippingPossible;
        },

    },
});
