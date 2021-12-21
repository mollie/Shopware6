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
            updatePaymentMethodsIsLoading: false,
        };
    },

    methods: {
        onUpdatePaymentMethodsButtonClicked() {
            const me = this;

            this.startUpdatePaymentMethod();

            this.MolliePaymentsPaymentMethodService.updatePaymentMethods()
                .then((response) => {
                    const messageData = {
                        title: me.$tc('mollie-payments.config.payments.updatePaymentMethods.title'),
                        message: me.$tc('mollie-payments.config.payments.updatePaymentMethods.succeeded'),
                    };

                    if (response.success === true) {
                        me.createNotificationSuccess(messageData);
                    } else {
                        messageData.message = me.$tc('mollie-payments.config.payments.updatePaymentMethods.failed');
                        me.createNotificationError(messageData);
                    }

                    this.updatePaymentMethodsIsDone();
                }).catch(() => {
                    this.updatePaymentMethodsIsDone();
                });
        },

        startUpdatePaymentMethod() {
            this.updatePaymentMethodsIsLoading = true;

            const button = this.$refs.updatePaymentMethodsButton;

            if (!button) {
                return;
            }

            button.disabled = true;
        },

        updatePaymentMethodsIsDone() {
            this.updatePaymentMethodsIsLoading = false;

            const button = this.$refs.updatePaymentMethodsButton;

            if (!button) {
                return;
            }

            button.disabled = false;
        },
    },
});
