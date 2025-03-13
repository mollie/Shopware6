// eslint-disable-next-line no-undef
const ApiService = Shopware.Classes.ApiService;

export default class MolliePaymentsItemCancelService extends ApiService {
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
    status(data = { mollieOrderId: null }) {
        return this.__post('/status', data);
    }

    cancel(
        data = {
            mollieOrderId: null,
            mollieLineId: null,
            shopwareLineId: null,
            canceledQuantity: 0,
            resetStock: false,
        },
    ) {
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
            .post(`_action/${this.getApiBasePath()}/cancel-item${endpoint}`, JSON.stringify(data), {
                headers: this.getBasicHeaders(headers),
            })
            .then((response) => {
                return ApiService.handleResponse(response);
            })
            .catch((error) => {
                return ApiService.handleResponse(error.response);
            });
    }
}
