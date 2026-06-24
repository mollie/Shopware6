const ApiService = Shopware.Classes.ApiService;

export default class MolliePaymentsOrderService extends ApiService {
    constructor(httpClient: any, loginService: any, apiEndpoint: string = 'mollie') {
        super(httpClient, loginService, apiEndpoint);
    }

    getPaymentUrl(data: Record<string, any> = { orderId: null }): Promise<any> {
        const headers = this.getBasicHeaders();

        return this.httpClient
            .post(`_action/${this.getApiBasePath()}/order/payment-url`, JSON.stringify(data), {
                headers: headers,
            })
            .then((response: any) => ApiService.handleResponse(response));
    }
}
