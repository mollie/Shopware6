import template from './sw-order-user-card.html.twig';

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
        mollieOrderId() {
            if (
                !!this.currentOrder
                && !!this.currentOrder.customFields
                && !!this.currentOrder.customFields.mollie_payments
                && !!this.currentOrder.customFields.mollie_payments.order_id
            ) {
                return this.currentOrder.customFields.mollie_payments.order_id;
            }

            return null;
        },

    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.$super('createdComponent');

            if(this.mollieOrderId) {
                this.isMolliePaymentUrlLoading = true;

                this.MolliePaymentsOrderService.getPaymentUrl({orderId: this.currentOrder.id})
                    .then(response => {
                        this.molliePaymentUrl = response.url;
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
    },
});
