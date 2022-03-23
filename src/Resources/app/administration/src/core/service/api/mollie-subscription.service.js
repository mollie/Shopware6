// eslint-disable-next-line no-undef
const ApiService = Shopware.Classes.ApiService;

class MolliePaymentsSubscriptionService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'mollie') {
        super(httpClient, loginService, apiEndpoint);
    }

    __post(endpoint = '', data = {}, headers = {}) {
        return this.httpClient
            .post(
                `_action/${this.getApiBasePath()}/subscription${endpoint}`,
                JSON.stringify(data),
                {
                    headers: this.getBasicHeaders(headers),
                }
            )
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    cancel(data = {id: null, customerId: null, salesChannelId: null}) {
        return this.__post('/cancel', data);
    }
}

export default MolliePaymentsSubscriptionService;
