const ApiService = Shopware.Classes.ApiService;

export default class MolliePaymentsPaymentMethodService extends ApiService {
    constructor(httpClient: any, loginService: any, apiEndpoint: string = 'mollie') {
        super(httpClient, loginService, apiEndpoint);
    }

    updatePaymentMethods(): Promise<any> {
        const headers = this.getBasicHeaders();

        return this.httpClient
            .get(`_action/${this.getApiBasePath()}/payment-method/update-methods`, {
                headers: headers,
            })
            .then((response: any) => ApiService.handleResponse(response));
    }
}
