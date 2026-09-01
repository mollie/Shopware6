import template from './mollie-pluginconfig-section-payments.html.twig';
import './mollie-pluginconfig-section-payments.scss';
import UpdateResultService, { type UpdateResponse } from './services/UpdateResultService';

const { Component, Mixin } = Shopware;

interface SectionPaymentsComponent {
    updateResultService: UpdateResultService;
    updatePaymentMethodsIsLoading: boolean;

    [key: string]: any;
}

const componentConfig: ThisType<SectionPaymentsComponent> = {
    template,

    mixins: [Mixin.getByName('notification')],

    inject: ['MolliePaymentsPaymentMethodService'],

    data() {
        return {
            updateResultService: null,
            updatePaymentMethodsIsLoading: false,
        };
    },

    created() {
        this.updateResultService = new UpdateResultService();
    },

    methods: {
        onUpdatePaymentMethodsButtonClicked() {
            this.updatePaymentMethodsIsLoading = true;

            this.MolliePaymentsPaymentMethodService.updatePaymentMethods()
                .then((response: UpdateResponse) => {
                    const messageData = {
                        title: this.$tc('mollie-payments.config.payments.updatePaymentMethods.title'),
                        message: this.$tc('mollie-payments.config.payments.updatePaymentMethods.succeeded'),
                    };

                    if (this.updateResultService.isSuccess(response)) {
                        this.createNotificationSuccess(messageData);
                    } else {
                        messageData.message = this.updateResultService.buildErrorMessage(
                            response,
                            this.$tc('mollie-payments.config.payments.updatePaymentMethods.failed'),
                        );
                        this.createNotificationError(messageData);
                    }
                })
                .finally(() => {
                    this.updatePaymentMethodsIsLoading = false;
                });
        },
    },
};

Component.register('mollie-pluginconfig-section-payments', componentConfig);
