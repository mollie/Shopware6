// eslint-disable-next-line no-undef
const ApiService = Shopware.Classes.ApiService;

export default class MolliePaymentsSubscriptionService extends ApiService {

    /**
     *
     * @param httpClient
     * @param loginService
     * @param apiEndpoint
     */
    constructor(httpClient, loginService, apiEndpoint = 'mollie') {
        super(httpClient, loginService, apiEndpoint);
    }


    /**
     *
     * @param data
     * @returns {*}
     */
    cancel(data = {id: null, customerId: null, salesChannelId: null}) {
        return this.__post('/cancel', data);
    }

    /**
     *
     * @param data
     * @returns {*}
     */
    pause(data = {id: null, customerId: null, salesChannelId: null}) {
        return this.__post('/pause', data);
    }

    /**
     *
     * @param data
     * @returns {*}
     */
    resume(data = {id: null, customerId: null, salesChannelId: null}) {
        return this.__post('/resume', data);
    }

    /**
     *
     * @param data
     * @returns {*}
     */
    skip(data = {id: null, customerId: null, salesChannelId: null}) {
        return this.__post('/skip', data);
    }


    /**
     *
     * @param endpoint
     * @returns {*}
     * @private
     */
    __get(endpoint = '') {
        return this.httpClient
            .get(
                `_action/${this.getApiBasePath()}/subscriptions${endpoint}`,
                {
                    headers: this.getBasicHeaders({}),
                }
            )
            .then((response) => {
                return ApiService.handleResponse(response);
            })
            .catch((error) => {
                return ApiService.handleResponse(error.response);
            });
    }

    /**
     *
     * @param endpoint
     * @param data
     * @param headers
     * @returns {*}
     * @private
     */
    __post(endpoint = '', data = {}, headers = {}) {
        return this.httpClient
            .post(
                `_action/${this.getApiBasePath()}/subscriptions${endpoint}`,
                JSON.stringify(data),
                {
                    headers: this.getBasicHeaders(headers),
                }
            )
            .then((response) => {
                return ApiService.handleResponse(response);
            })
            .catch((error) => {
                return ApiService.handleResponse(error.response);
            });
    }

}
