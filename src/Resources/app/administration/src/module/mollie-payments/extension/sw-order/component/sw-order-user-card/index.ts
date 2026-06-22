import template from './sw-order-user-card.html.twig';
import './sw-order-user-card.scss';
import OrderAttributes from '../../../../../../core/models/OrderAttributes';

const { Component } = Shopware;

interface SwOrderUserCardOverride {
    isMolliePaymentUrlLoading: boolean;
    molliePaymentUrl: string | null;
    molliePaymentUrlCopied: boolean;

    [key: string]: any;
}

const overrideConfig: ThisType<SwOrderUserCardOverride> = {
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
        isMollieOrder() {
            return new OrderAttributes(this.currentOrder).isMollieOrder();
        },

        hasCreditCardData() {
            return this._creditCardData().hasCreditCardData();
        },

        creditCardLabel() {
            return this._creditCardData().getLabel();
        },

        creditCardNumber() {
            return '**** **** **** ' + this._creditCardData().getNumber();
        },

        creditCardHolder() {
            return this._creditCardData().getHolder();
        },

        mollieOrderId() {
            return new OrderAttributes(this.currentOrder).getMollieID();
        },

        mollieThirdPartyPaymentId() {
            return new OrderAttributes(this.currentOrder).getPaymentRef();
        },

        /**
         * Subscription either via legacy customField (older orders) or via the
         * mollieSubscriptions extension association (loaded by the
         * sw-order-detail override).
         */
        isSubscription() {
            const orderAttributes = new OrderAttributes(this.currentOrder);

            return orderAttributes.isSubscription() || this._extensionSubscription() !== null;
        },

        subscriptionId() {
            const orderAttributes = new OrderAttributes(this.currentOrder);

            return orderAttributes.getSwSubscriptionId() || this._extensionSubscription()?.id || '';
        },

        hasPaymentLink() {
            return this.molliePaymentUrl !== '';
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.$super('createdComponent');

            this.molliePaymentUrl = '';

            if (!this.mollieOrderId) {
                return;
            }

            this.isMolliePaymentUrlLoading = true;

            this.MolliePaymentsOrderService.getPaymentUrl({ orderId: this.currentOrder.id })
                .then((response: any) => {
                    this.molliePaymentUrl = response.url !== null ? response.url : '';
                })
                .finally(() => {
                    this.isMolliePaymentUrlLoading = false;
                });
        },

        _creditCardData() {
            return new OrderAttributes(this.currentOrder).getCreditCardAttributes();
        },

        /**
         * Reads mollieSubscriptions from either the entity extension bag
         * (Shopware default for EntityExtension associations) or directly from
         * the order — depending on the Shopware version the DAL sometimes hoists
         * the association onto the entity itself.
         */
        _extensionSubscription(): any {
            const order = this.currentOrder;
            const subscriptions = order?.extensions?.mollieSubscriptions ?? order?.mollieSubscriptions ?? null;

            if (!subscriptions) {
                return null;
            }

            if (typeof subscriptions.first === 'function') {
                return subscriptions.first() ?? null;
            }

            return subscriptions.length > 0 ? subscriptions[0] : null;
        },

        copyPaymentUrlToClipboard() {
            Shopware.Utils.dom.copyToClipboard(this.molliePaymentUrl);
            this.molliePaymentUrlCopied = true;
        },

        onMolliePaymentUrlProcessFinished(value: boolean) {
            this.molliePaymentUrlCopied = value;
        },
    },
};

Component.override('sw-order-user-card', overrideConfig);
