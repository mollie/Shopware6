// eslint-disable-next-line no-undef
const ApiService = Shopware.Classes.ApiService;

class MolliePaymentsPaymentMethodService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'mollie') {
        super(httpClient, loginService, apiEndpoint);
    }

    updatePaymentMethods() {
        const headers = this.getBasicHeaders();

        return this.httpClient
            .get(
                `_action/${this.getApiBasePath()}/payment-method/update-methods`,
                {
                    headers: headers,
                }
            )
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }
}

export default MolliePaymentsPaymentMethodService;