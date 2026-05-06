import template from './sw-order-detail-general.html.twig';
import './sw-order-detail-general.scss';
import OrderAttributes from '../../../../../../core/models/OrderAttributes';
import RefundManager from '../../../../components/mollie-refund-manager/RefundManager';
import MollieShipping from '../../../../components/mollie-ship-order/MollieShipping';
import MollieShippingEvents from '../../../../components/mollie-ship-order/MollieShippingEvents';

// eslint-disable-next-line no-undef
const { Component, Mixin, Filter } = Shopware;

Component.override('sw-order-detail-general', {
    template,

    mixins: [Mixin.getByName('notification')],

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
        };
    },

    computed: {
        /**
         * Treat the order as "Mollie order" either via the legacy
         * OrderAttributes (transaction + customFields.mollie_payments) or
         * when a Mollie subscription is attached — the latter is needed
         * since the refactor stopped writing customFields.mollie_payments
         * on the order; that data now lives on the transaction.
         *
         * @returns {boolean}
         */
        isMollieOrder() {
            const attr = new OrderAttributes(this.order);
            return attr.isMollieOrder() || this._extensionSubscription() !== null;
        },

        /**
         *
         * @returns {*}
         */
        hasCreditCardData() {
            return this._creditCardData()?.hasCreditCardData() ?? false;
        },

        /**
         *
         * @returns {string|*}
         */
        creditCardLabel() {
            return this._creditCardData().getLabel();
        },

        /**
         *
         * @returns {string|*}
         */
        creditCardNumber() {
            return '**** **** **** ' + this._creditCardData().getNumber();
        },

        /**
         *
         * @returns {string|*}
         */
        creditCardHolder() {
            return this._creditCardData().getHolder();
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
         * Subscription either via legacy customField (older orders) or via
         * the mollieSubscriptions extension association (loaded by the
         * sw-order-detail orderCriteria override).
         *
         * @returns {boolean}
         */
        isSubscription() {
            const orderAttributes = new OrderAttributes(this.order);
            const fromCustomField = orderAttributes.isSubscription();
            const fromExtension = this._extensionSubscription();

            return fromCustomField || fromExtension !== null;
        },

        /**
         *
         * @returns {string}
         */
        subscriptionId() {
            const orderAttributes = new OrderAttributes(this.order);
            return orderAttributes.getSwSubscriptionId() || this._extensionSubscription()?.id || '';
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

            if (this.$root && this.$root.$on) {
                this.$root.$on(MollieShippingEvents.EventShippedOrder, () => {
                    this.onCloseShippingManager();
                    // let's reload our page so that the
                    // full order is updated like shipping status and more
                    location.reload();
                });
            } else {
                Shopware.Utils.EventBus.on(MollieShippingEvents.EventShippedOrder, () => {
                    this.onCloseShippingManager();
                    // let's reload our page so that the
                    // full order is updated like shipping status and more
                    location.reload();
                });
            }

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
            location.reload();
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
         * Reads mollieSubscriptions from either the entity extension bag
         * or directly off the order — depending on the Shopware version
         * the DAL sometimes hoists the association onto the entity.
         *
         * @returns {object|null}
         * @private
         */
        _extensionSubscription() {
            const order = this.order;
            const subscriptions =
                order?.extensions?.mollieSubscriptions ?? order?.mollieSubscriptions ?? null;

            if (!subscriptions) {
                return null;
            }

            if (typeof subscriptions.first === 'function') {
                return subscriptions.first() ?? null;
            }

            return subscriptions.length > 0 ? subscriptions[0] : null;
        },

        /**
         *
         */
        copyPaymentUrlToClipboard() {
            const fallback = async function (e) {
                await navigator.clipboard.writeText(e);
            };

            // eslint-disable-next-line no-undef
            const clipboard =
                typeof Shopware.Utils.dom.copyToClipboard === 'function'
                    ? Shopware.Utils.dom.copyToClipboard
                    : fallback;
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
                return;
            }

            this.MolliePaymentsOrderService.getPaymentUrl({ orderId: this.order.id }).then((response) => {
                this.molliePaymentUrl = response.url !== null ? response.url : '';
            });

            if (!this.shippingManagerService) {
                this.shippingManagerService = new MollieShipping(this.MolliePaymentsShippingService);
            }

            this.shippingManagerService.isShippingPossible(this.order).then((enabled) => {
                this.isShippingPossible = enabled;
            });

            if (!this.refundedManagerService) {
                this.refundedManagerService = new RefundManager(this.MolliePaymentsConfigService, this.acl);
            }
            this.refundedManagerService
                .isRefundManagerAvailable(this.order.salesChannelId, this.order)
                .then((possible) => {
                    this.isRefundManagerPossible = possible;
                });

            this.MolliePaymentsRefundService.getRefundManagerData({
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

            this.MolliePaymentsShippingService.total({ orderId: this.order.id }).then((response) => {
                this.shippedAmount = Math.round(response.amount * 100) / 100;
                this.shippedQuantity = response.quantity;
            });
        },
    },
});
