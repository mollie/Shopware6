const ApiService = Shopware.Classes.ApiService;

class MolliePaymentsRefundService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'mollie') {
        super(httpClient, loginService, apiEndpoint);
    }

    __post(endpoint = '', data = {}, headers = {}) {
        return this.httpClient
            .post(
                `_action/${this.getApiBasePath()}/refund${endpoint}`,
                JSON.stringify(data),
                {
                    headers: this.getBasicHeaders(headers)
                }
            )
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    refund(data = {orderId: null, amount: null}) {
        return this.__post('', data);
    }

    total(data = {orderId: null}) {
        return this.__post('/total', data);
    }
}

export default MolliePaymentsRefundService;
