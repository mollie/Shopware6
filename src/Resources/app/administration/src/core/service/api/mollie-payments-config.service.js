// eslint-disable-next-line no-undef
const ApiService = Shopware.Classes.ApiService;

export default class MolliePaymentsConfigService extends ApiService {


    /**
     *
     * @param httpClient
     * @param loginService
     * @param currentLocale
     */
    constructor(httpClient, loginService, currentLocale) {
        super(httpClient, loginService, 'mollie');

        this.currentLocale = currentLocale;
    }

    /**
     *
     * @param data
     * @returns {*}
     */
    testApiKeys(data = {liveApiKey: null, testApiKey: null}) {
        const headers = this.getBasicHeaders();

        return this.httpClient
            .post(
                `_action/${this.getApiBasePath()}/config/test-api-keys`,
                JSON.stringify(data),
                {
                    headers: headers,
                }
            )
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    /**
     *
     * @returns {*}
     */
    validateFlowBuilder() {
        return this.httpClient
            .post(
                `_action/${this.getApiBasePath()}/config/validate/flowbuilder`,
                {
                    locale: this.currentLocale,
                },
                {
                    headers: this.getBasicHeaders(),
                }
            ).then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    /**
     *
     * @param salesChannelId
     * @returns {*}
     */
    getRefundManagerConfig(salesChannelId) {
        return this.httpClient
            .post(
                `_action/${this.getApiBasePath()}/config/refund-manager`,
                {
                    'salesChannelId': salesChannelId,
                },
                {
                    headers: this.getBasicHeaders(),
                }
            ).then((response) => {
                return ApiService.handleResponse(response);
            });
    }

}
