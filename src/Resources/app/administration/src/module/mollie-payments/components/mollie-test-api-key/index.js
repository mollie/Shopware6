import template from './mollie-test-api-key.html.twig';

// eslint-disable-next-line no-undef
const {Component, Mixin} = Shopware;

Component.register('mollie-test-api-key', {
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
                            title: me.$tc('sw-payment.testApiKeys.title'),
                            message: `${me.$tc('sw-payment.testApiKeys.apiKey')} "${result.key}" (${result.mode}) ${(result.valid === true ? me.$tc('sw-payment.testApiKeys.isValid') : me.$tc('sw-payment.testApiKeys.isInvalid'))}.`,
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
