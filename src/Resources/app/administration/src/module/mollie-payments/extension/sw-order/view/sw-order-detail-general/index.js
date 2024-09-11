import template from './sw-order-detail-general.html.twig';
import './sw-order-detail-general.scss';
import OrderAttributes from '../../../../../../core/models/OrderAttributes';
import RefundManager from '../../../../components/mollie-refund-manager/RefundManager';
import MollieShipping from '../../../../components/mollie-ship-order/MollieShipping';
import MollieShippingEvents from '../../../../components/mollie-ship-order/MollieShippingEvents';

// eslint-disable-next-line no-undef
const {Component, Mixin, Filter} = Shopware;

Component.override('sw-order-detail-general', {
    template,

    mixins: [
        Mixin.getByName('notification'),
    ],

    inject: [
        'MolliePaymentsRefundService',
        'MolliePaymentsShippingService',
        'MolliePaymentsOrderService',
        'MolliePaymentsConfigService',
        'acl',
    ],

    data() {
        return {
            // -------------------------------------
            // services
            refundManagerService: null,
            shippingManagerService: null,
            // -------------------------------------
            // card data
            molliePaymentUrl: '',
            molliePaymentUrlCopied: false,
            isRefundManagerPossible: false,
            showRefundModal: false,
            isShippingPossible: false,
            showShippingModal: false,
            // -------------------------------------
            // summary data
            remainingAmount: 0.0,
            refundedAmount: 0.0,
            voucherAmount: 0.0,
            refundAmountPending: 0.0,
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

        /**
         *
         * @returns {*}
         */
        hasCreditCardData() {
            return this._creditCardData().hasCreditCardData();
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
            return orderAttributes.getMollieID();
        },

        /**
         *
         * @returns {*|null}
         */
        mollieThirdPartyPaymentId() {
            const orderAttributes = new OrderAttributes(this.order);
            return orderAttributes.getPaymentRef();
        },

        /**
         *
         * @returns {null|*}
         */
        isSubscription() {
            const orderAttributes = new OrderAttributes(this.order);
            return orderAttributes.isSubscription();
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

        currencyFilter() {
            return Filter.getByName('currency');
        },

    },

    watch: {

        /**
         *
         */
        order() {
            this.getMollieData();
        },

    },

    created() {
        this.createdComponent();
    },

    methods: {

        /**
         *
         */
        createdComponent() {

            this.molliePaymentUrl = '';
            this.isShippingPossible = false;
            this.isRefundManagerPossible = false;

            if (!this.mollieOrderId) {
                return;
            }

            this.refundedManagerService = new RefundManager(this.MolliePaymentsConfigService, this.acl);
            this.shippingManagerService = new MollieShipping(this.MolliePaymentsShippingService);

            this.$root.$on(MollieShippingEvents.EventShippedOrder, () => {
                this.onCloseShippingManager();
                // let's reload our page so that the
                // full order is updated like shipping status and more
                location.reload();
            });

            this.getMollieData();
        },

        /**
         *
         */
        onOpenRefundManager() {
            this.showRefundModal = true;
        },

        /**
         *
         */
        onCloseRefundManager() {
            this.showRefundModal = false;
        },

        /**
         *
         */
        onOpenShippingManager() {
            this.showShippingModal = true;
        },

        /**
         *
         */
        onCloseShippingManager() {
            this.showShippingModal = false;
        },

        /**+
         * @returns {CreditcardAttributes|*|null}
         * @private
         */
        _creditCardData() {
            const orderAttributes = new OrderAttributes(this.order);
            return orderAttributes.getCreditCardAttributes();
        },



        /**
         *
         */
        copyPaymentUrlToClipboard() {
            let fallback = async function(e) {
                await navigator.clipboard.writeText(e)
            };

            // eslint-disable-next-line no-undef
            let clipboard = typeof Shopware.Utils.dom.copyToClipboard === 'function' ? Shopware.Utils.dom.copyToClipboard : fallback;
            // eslint-disable-next-line no-undef
            clipboard(this.molliePaymentUrl);
            this.molliePaymentUrlCopied = true;
        },

        /**
         *
         * @param value
         */
        onMolliePaymentUrlProcessFinished(value) {
            this.molliePaymentUrlCopied = value;
        },

        /**
         *
         */
        getMollieData() {
            if (!this.isMollieOrder) {
                return
            }

            this.MolliePaymentsOrderService.getPaymentUrl({orderId: this.order.id}).then(response => {
                this.molliePaymentUrl = (response.url !== null) ? response.url : '';
            });

            this.shippingManagerService.isShippingPossible(this.order).then((enabled) => {
                this.isShippingPossible = enabled;
            });

            this.refundedManagerService.isRefundManagerAvailable(this.order.salesChannelId, this.order.id).then((possible)=>{
                this.isRefundManagerPossible =possible;
            });



            this.MolliePaymentsRefundService.getRefundManagerData(
                {
                    orderId: this.order.id,
                })
                .then((response) => {
                    this.remainingAmount = response.totals.remaining;
                    this.refundedAmount = response.totals.refunded;
                    this.voucherAmount = response.totals.voucherAmount;
                    this.refundAmountPending = response.totals.pendingRefunds;
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
