// eslint-disable-next-line no-undef
const ApiService = Shopware.Classes.ApiService;

export default class MolliePaymentsRefundService extends ApiService {

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
    list(data = {orderId: null}) {
        return this.__post('/list', data);
    }

    /**
     *
     * @param data
     * @returns {*}
     */
    refund(data = {orderId: null, amount: null, description: '', items: []}) {
        return this.__post('', data);
    }

    /**
     *
     * @param data
     * @returns {*}
     */
    refundAll(data = {orderId: null, amount: null}) {
        return this.__post('', data);
    }

    /**
     *
     * @param data
     * @returns {*}
     */
    cancel(data = {orderId: null, refundId: null}) {
        return this.__post('/cancel', data);
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
                `_action/${this.getApiBasePath()}/refund${endpoint}`,
                JSON.stringify(data),
                {
                    headers: this.getBasicHeaders(headers),
                }
            )
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

}
