import template from './mollie-pluginconfig-section-api.html.twig';
import './mollie-pluginconfig-section-api.scss';
import ApiKeyTestService from './services/ApiKeyTestService';

const { Component, Mixin } = Shopware;

interface SectionApiComponent {
    apiKeyTestService: ApiKeyTestService;

    [key: string]: any;
}

const componentConfig: ThisType<SectionApiComponent> = {
    template,

    mixins: [Mixin.getByName('notification')],

    inject: ['MolliePaymentsConfigService'],

    data() {
        return {
            apiKeyTestService: null,
        };
    },

    created() {
        this.apiKeyTestService = new ApiKeyTestService();
    },

    methods: {
        onTestButtonClicked() {
            const liveApiKeyInput = document.querySelector<HTMLInputElement>(
                'input[name="MolliePayments.config.liveApiKey"],*[class*="field-mollie-payments-config-live-api-key"] input',
            );
            const testApiKeyInput = document.querySelector<HTMLInputElement>(
                'input[name="MolliePayments.config.testApiKey"],*[class*="field-mollie-payments-config-test-api-key"] input',
            );

            const liveApiKey = liveApiKeyInput?.value ?? null;
            const testApiKey = testApiKeyInput?.value ?? null;

            this.MolliePaymentsConfigService.testApiKeys({ liveApiKey, testApiKey }).then((response: any) => {
                response.results.forEach((result: any) => {
                    const messageData = {
                        title: this.$tc('mollie-payments.config.api.testApiKeys.title'),
                        message: this.apiKeyTestService.buildResultMessage(result, {
                            apiKey: this.$tc('mollie-payments.config.api.testApiKeys.apiKey'),
                            isValid: this.$tc('mollie-payments.config.api.testApiKeys.isValid'),
                            isInvalid: this.$tc('mollie-payments.config.api.testApiKeys.isInvalid'),
                        }),
                    };

                    const input = result.mode === 'live' ? liveApiKeyInput : testApiKeyInput;
                    input?.parentElement?.parentElement?.classList.remove('has--error');

                    if (this.apiKeyTestService.isValid(result)) {
                        this.createNotificationSuccess(messageData);
                    } else {
                        this.createNotificationError(messageData);
                        input?.parentElement?.parentElement?.classList.add('has--error');
                    }
                });
            });
        },
    },
};

Component.register('mollie-pluginconfig-section-api', componentConfig);
