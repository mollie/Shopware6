import template from './sw-order-user-card.html.twig';
import './sw-order-user-card.scss';
import OrderAttributes from '../../../../../../core/models/OrderAttributes';

// eslint-disable-next-line no-undef
const {Component} = Shopware;

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
         *
         * @returns {null|*}
         */
        isSubscription() {
            const orderAttributes = new OrderAttributes(this.currentOrder);
            return orderAttributes.isSubscription();
        },

        /**
         *
         * @returns {string|*}
         */
        subscriptionId() {
            const orderAttributes = new OrderAttributes(this.currentOrder);
            return orderAttributes.getSwSubscriptionId();
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

            this.MolliePaymentsOrderService.getPaymentUrl({orderId: this.currentOrder.id})
                .then(response => {
                    this.molliePaymentUrl = (response.url !== null) ? response.url : '';
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
