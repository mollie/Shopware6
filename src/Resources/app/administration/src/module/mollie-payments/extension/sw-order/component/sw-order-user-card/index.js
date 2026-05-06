import template from './sw-order-user-card.html.twig';
import './sw-order-user-card.scss';
import OrderAttributes from '../../../../../../core/models/OrderAttributes';

// eslint-disable-next-line no-undef
const { Component } = Shopware;

Component.override('sw-order-user-card', {
    template,

    inject: ['MolliePaymentsOrderService'],

    data() {
        return {
            isMolliePaymentUrlLoading: false,
            molliePaymentUrl: null,
            molliePaymentUrlCopied: false,
        };
    },

    computed: {
        /**
         *
         * @returns {boolean}
         */
        isMollieOrder() {
            const attr = new OrderAttributes(this.currentOrder);
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
            const orderAttributes = new OrderAttributes(this.currentOrder);
            return orderAttributes.getMollieID();
        },

        /**
         *
         * @returns {string}
         */
        mollieThirdPartyPaymentId() {
            const orderAttributes = new OrderAttributes(this.currentOrder);
            return orderAttributes.getPaymentRef();
        },

        /**
         * Subscription either via legacy customField (older orders) or via
         * the mollieSubscriptions extension association (loaded by the
         * sw-order-detail override).
         *
         * @returns {boolean}
         */
        isSubscription() {
            const orderAttributes = new OrderAttributes(this.currentOrder);
            return orderAttributes.isSubscription() || this._extensionSubscription() !== null;
        },

        /**
         *
         * @returns {string}
         */
        subscriptionId() {
            const orderAttributes = new OrderAttributes(this.currentOrder);
            return orderAttributes.getSwSubscriptionId() || this._extensionSubscription()?.id || '';
        },

        /**
         *
         * @returns {boolean}
         */
        hasPaymentLink() {
            return this.molliePaymentUrl !== '';
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
            this.$super('createdComponent');

            this.molliePaymentUrl = '';

            if (!this.mollieOrderId) {
                return;
            }

            this.isMolliePaymentUrlLoading = true;

            this.MolliePaymentsOrderService.getPaymentUrl({ orderId: this.currentOrder.id })
                .then((response) => {
                    this.molliePaymentUrl = response.url !== null ? response.url : '';
                })
                .finally(() => {
                    this.isMolliePaymentUrlLoading = false;
                });
        },

        /**
         *
         * @returns {CreditcardAttributes|*}
         */
        _creditCardData() {
            const orderAttributes = new OrderAttributes(this.currentOrder);
            return orderAttributes.getCreditCardAttributes();
        },

        /**
         * Reads mollieSubscriptions from either the entity extension bag
         * (Shopware default for EntityExtension associations) or directly
         * from the order — depending on the Shopware version the DAL
         * sometimes hoists the association onto the entity itself.
         *
         * @returns {object|null}
         */
        _extensionSubscription() {
            const order = this.currentOrder;
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
            // eslint-disable-next-line no-undef
            Shopware.Utils.dom.copyToClipboard(this.molliePaymentUrl);
            this.molliePaymentUrlCopied = true;
        },

        /**
         *
         * @param value
         */
        onMolliePaymentUrlProcessFinished(value) {
            this.molliePaymentUrlCopied = value;
        },
    },
});
