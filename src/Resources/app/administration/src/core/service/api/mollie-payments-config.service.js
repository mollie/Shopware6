// eslint-disable-next-line no-undef
const ApiService = Shopware.Classes.ApiService;

export default class MolliePaymentsConfigService extends ApiService {

    constructor(httpClient, loginService, currentLocale) {
        super(httpClient, loginService, 'mollie');

        this.currentLocale = currentLocale;
        this.cache = {};
        // Cache expiration time in milliseconds (e.g., 60 seconds)
        this.cacheExpirationTime = 60000;
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

    getSubscriptionConfig(){
        return this.httpClient
            .post(
                `_action/${this.getApiBasePath()}/config/subscription`,
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
     * @param orderId
     * @returns {*}
     */
    getRefundManagerConfig(salesChannelId, orderId) {
        const cacheKey = `${salesChannelId}_${orderId}`;

        const now = new Date().getTime();
        const cachedEntry = this.cache[cacheKey];

        if (cachedEntry && (now - cachedEntry.timestamp < this.cacheExpirationTime)) {
            return Promise.resolve(cachedEntry.data);
        }

        // If there's no valid cache, make the API request
        return this.httpClient
            .post(
                `_action/${this.getApiBasePath()}/config/refund-manager`,
                {
                    'salesChannelId': salesChannelId,
                    'orderId': orderId,
                },
                {
                    headers: this.getBasicHeaders(),
                }
            ).then((response) => {
                const responseData = ApiService.handleResponse(response);

                this.cache[cacheKey] = {
                    data: responseData,
                    timestamp: now
                };

                return responseData;
            });
    }

}
