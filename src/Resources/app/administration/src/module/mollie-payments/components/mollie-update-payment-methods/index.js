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

    methods: {
        onUpdatePaymentMethodsButtonClicked() {
            const me = this;

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
                });
        },
    },
});
