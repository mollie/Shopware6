// eslint-disable-next-line no-undef
const ApiService = Shopware.Classes.ApiService;

class MollieOrderDetailsService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'mollie') {
        super(httpClient, loginService, apiEndpoint);
    }

    getDetails(orderId) {
        return this.httpClient
            .get(`_action/${this.getApiBasePath()}/order/${orderId}/details`, {
                headers: this.getBasicHeaders(),
            })
            .then(function (response) {
                return ApiService.handleResponse(response);
            });
    }
}

export default MollieOrderDetailsService;
