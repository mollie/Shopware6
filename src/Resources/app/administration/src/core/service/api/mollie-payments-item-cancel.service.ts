const ApiService = Shopware.Classes.ApiService;

export default class MolliePaymentsItemCancelService extends ApiService {
    constructor(httpClient: any, loginService: any, apiEndpoint: string = 'mollie') {
        super(httpClient, loginService, apiEndpoint);
    }

    cancel(
        data: Record<string, any> = {
            shopwareLineId: null,
            quantity: 0,
            resetStock: false,
        },
    ): Promise<any> {
        return this.httpClient
            .post(`_action/${this.getApiBasePath()}/cancel/item`, JSON.stringify(data), {
                headers: this.getBasicHeaders(),
            })
            .then((response: any) => ApiService.handleResponse(response))
            .catch((error: any) => ApiService.handleResponse(error.response));
    }
}
