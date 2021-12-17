// eslint-disable-next-line no-undef
const ApiService = Shopware.Classes.ApiService;

class MolliePaymentsShippingService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'mollie') {
        super(httpClient, loginService, apiEndpoint);
    }

    __post(endpoint = '', data = {}, headers = {}) {
        return this.httpClient
            .post(
                `_action/${this.getApiBasePath()}/ship${endpoint}`,
                JSON.stringify(data),
                {
                    headers: this.getBasicHeaders(headers),
                }
            )
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    ship(data = {itemId: null, quantity: null}) {
        return this.__post('', data);
    }

    status(data = {orderId: null}) {
        return this.__post('/status', data);
    }

    total(data = {orderId: null}) {
        return this.__post('/total', data);
    }
}

export default MolliePaymentsShippingService;
