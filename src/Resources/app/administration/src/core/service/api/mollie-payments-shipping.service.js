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

    shipOrder(data = {
        orderId: null,
        trackingCarrier: null,
        trackingCode: null,
        trackingUrl: null,
    }) {
        return this.__post('', data);
    }

    shipItem(data = {
        orderId: null,
        itemId: null,
        quantity: null,
        trackingCarrier: null,
        trackingCode: null,
        trackingUrl: null,
    }) {
        return this.__post('/item', data);
    }

    status(data = {orderId: null}) {
        return this.__post('/status', data);
    }

    total(data = {orderId: null}) {
        return this.__post('/total', data);
    }
}

export default MolliePaymentsShippingService;
