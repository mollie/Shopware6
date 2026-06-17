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

    cancel(
        data = {
            shopwareLineId: null,
            quantity: 0,
            resetStock: false,
        },
    ) {
        return this.httpClient
            .post(`_action/${this.getApiBasePath()}/cancel/item`, JSON.stringify(data), {
                headers: this.getBasicHeaders(),
            })
            .then((response) => {
                return ApiService.handleResponse(response);
            })
            .catch((error) => {
                return ApiService.handleResponse(error.response);
            });
    }
}
