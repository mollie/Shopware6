import template from './sw-plugin-config.html.twig';

const { Component, Mixin } = Shopware;

Component.override('sw-plugin-config', {
    template,

    inject: [
        'MolliePaymentsConfigService',
    ],

    mixins: [
        Mixin.getByName('notification')
    ],

    methods: {
        onTestButtonClicked() {
            let me = this;

            const liveApiKeyInput = document.querySelector('input[name="MolliePayments.config.liveApiKey"]');
            const testApiKeyInput = document.querySelector('input[name="MolliePayments.config.testApiKey"]');

            const liveApiKey = !!liveApiKeyInput ? liveApiKeyInput.value : null;
            const testApiKey = !!testApiKeyInput ? testApiKeyInput.value : null;

            this.MolliePaymentsConfigService.testApiKeys({liveApiKey, testApiKey})
                .then((response) => {
                    if (typeof response.results) {
                        response.results.forEach(function (result) {
                            let messageData = {
                                title: me.$tc('sw-payment.testApiKeys.title'),
                                message: `${me.$tc('sw-payment.testApiKeys.apiKey')} "${result.key}" (${result.mode}) ${(result.valid === true ? me.$tc('sw-payment.testApiKeys.isValid') : me.$tc('sw-payment.testApiKeys.isInvalid'))}.`
                            };

                            let input = result.mode === 'live' ? liveApiKeyInput : testApiKeyInput;

                            if (!!input) {
                                input.parentNode.parentNode.classList.remove('has--error');
                            }

                            if (result.valid === true) {
                                me.createNotificationSuccess(messageData);
                            } else {
                                me.createNotificationError(messageData);

                                if (!!input) {
                                    input.parentNode.parentNode.classList.add('has--error');
                                }
                            }
                        });
                    }
                });
        }
    }
});