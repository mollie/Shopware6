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
                !!this.currentOrder
                && !!this.currentOrder.customFields
                && !!this.currentOrder.customFields.mollie_payments
                && !!this.currentOrder.customFields.mollie_payments.third_party_payment_id
            ) {
                return this.currentOrder.customFields.mollie_payments.third_party_payment_id;
            }

            return null;
        },

        /**
         *
         * @returns {null|*}
         */
        isSubscription() {
            const orderAttributes = new OrderAttributes(this.currentOrder);
            return (orderAttributes.getSwSubscriptionId() !== '');
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

        hasCreditCardData() {
            return this._creditCardData().hasCreditCardData();
        },

    },

    created() {
        this.createdComponent();
    },

    methods: {
        /**
         *
         * @returns {CreditcardAttributes|*}
         */
        _creditCardData() {
            const orderAttributes = new OrderAttributes(this.currentOrder);
            return orderAttributes.getCreditCardAttributes();
        },

        createdComponent() {
            this.$super('createdComponent');

            this.molliePaymentUrl = '';

            if (this.mollieOrderId) {
                this.isMolliePaymentUrlLoading = true;

                this.MolliePaymentsOrderService.getPaymentUrl({orderId: this.currentOrder.id})
                    .then(response => {
                        this.molliePaymentUrl = (response.url !== null) ? response.url : '';
                    })
                    .finally(() => {
                        this.isMolliePaymentUrlLoading = false;
                    });
            }
        },

        copyPaymentUrlToClipboard() {
            // eslint-disable-next-line no-undef
            Shopware.Utils.dom.copyToClipboard(this.molliePaymentUrl);
            this.molliePaymentUrlCopied = true;
        },

        onMolliePaymentUrlProcessFinished(value) {
            this.molliePaymentUrlCopied = value;
        },

        /**
         *
         * @returns {{voucher_type: *}|*|null}
         */
        getMollieData() {
            if (this.currentOrder === undefined || this.currentOrder === null) {
                return null;
            }

            if (this.currentOrder.customFields === undefined || this.currentOrder.customFields === null) {
                return null;
            }

            const customFields = this.currentOrder.customFields;

            if (customFields.mollie_payments === undefined || customFields.mollie_payments === null) {
                return null;
            }

            return customFields.mollie_payments;
        },

    },
});
