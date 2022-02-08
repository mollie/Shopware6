import template from './mollie-pluginconfig-section-payments.html.twig';
import './mollie-pluginconfig-section-payments.scss';


// eslint-disable-next-line no-undef
const {Component, Mixin} = Shopware;

Component.register('mollie-pluginconfig-section-payments', {
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

        /**
         *
         */
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
                        messageData.message = me.$tc('mollie-payments.config.payments.updatePaymentMethods.failed') + '\n\nException:\n' + response.message;
                        me.createNotificationError(messageData);
                    }

                    this.updatePaymentMethodsIsDone();
                }).catch(() => {
                    this.updatePaymentMethodsIsDone();
                });
        },

        startUpdatePaymentMethod() {
            this.updatePaymentMethodsIsLoading = true;
        },

        updatePaymentMethodsIsDone() {
            this.updatePaymentMethodsIsLoading = false;
        },
    },
});
