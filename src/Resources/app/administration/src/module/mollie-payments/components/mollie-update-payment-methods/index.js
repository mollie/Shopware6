import template from './mollie-update-payment-methods.html.twig';

// eslint-disable-next-line no-undef
const {Component, Mixin} = Shopware;

Component.register('mollie-update-payment-methods', {
    template,

    inject: [
        'MolliePaymentsPaymentMethodService',
    ],

    mixins: [
        Mixin.getByName('notification'),
    ],

    data() {
        return {
            updatePaymentMethodsIsLoading: false
        };
    },

    methods: {
        onUpdatePaymentMethodsButtonClicked() {
            const me = this;
            const button = this.$refs.updatePaymentMethodsButton;

            button.disabled = true;
            me.updatePaymentMethodsIsLoading = true;

            this.MolliePaymentsPaymentMethodService.updatePaymentMethods()
                .then((response) => {
                    const messageData = {
                        title: me.$tc('sw-payment.updatePaymentMethods.title'),
                        message: me.$tc('sw-payment.updatePaymentMethods.succeeded')
                    };

                    if (response.success === true) {
                        me.createNotificationSuccess(messageData);
                    } else {
                        messageData.message = me.$tc('sw-payment.updatePaymentMethods.failed');
                        me.createNotificationError(messageData);
                    }

                    button.disabled = false;
                    me.updatePaymentMethodsIsLoading = false;
                }).catch(() => {
                    button.disabled = false;
                    me.updatePaymentMethodsIsLoading = false;
                });
        },
    },
});
