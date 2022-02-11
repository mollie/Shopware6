import template from './mollie-pluginconfig-section-api.html.twig';
import './mollie-pluginconfig-section-api.scss';


// eslint-disable-next-line no-undef
const {Component, Mixin} = Shopware;

Component.register('mollie-pluginconfig-section-api', {
    template,

    inject: [
        'MolliePaymentsConfigService',
    ],

    mixins: [
        Mixin.getByName('notification'),
    ],

    methods: {
        onTestButtonClicked() {
            const me = this;

            const liveApiKeyInput = document.querySelector('input[name="MolliePayments.config.liveApiKey"]');
            const testApiKeyInput = document.querySelector('input[name="MolliePayments.config.testApiKey"]');


            const liveApiKey = (liveApiKeyInput) ? liveApiKeyInput.value : null;
            const testApiKey = (testApiKeyInput) ? testApiKeyInput.value : null;

            this.MolliePaymentsConfigService.testApiKeys({liveApiKey, testApiKey})
                .then((response) => {

                    response.results.forEach(function (result) {
                        const messageData = {
                            title: me.$tc('mollie-payments.config.api.testApiKeys.title'),
                            message: `${me.$tc('mollie-payments.config.api.testApiKeys.apiKey')} "${result.key}" (${result.mode}) ${(result.valid === true ? me.$tc('mollie-payments.config.api.testApiKeys.isValid') : me.$tc('mollie-payments.config.api.testApiKeys.isInvalid'))}.`,
                        };

                        const input = result.mode === 'live' ? liveApiKeyInput : testApiKeyInput;

                        if (input) {
                            input.parentNode.parentNode.classList.remove('has--error');
                        }

                        if (result.valid === true) {
                            me.createNotificationSuccess(messageData);
                        } else {
                            me.createNotificationError(messageData);

                            if (input) {
                                input.parentNode.parentNode.classList.add('has--error');
                            }
                        }
                    });
                });
        },
    },
});
