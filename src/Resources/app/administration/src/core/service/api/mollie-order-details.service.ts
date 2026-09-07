const ApiService = Shopware.Classes.ApiService;

export default class MollieOrderDetailsService extends ApiService {
    constructor(httpClient: any, loginService: any, apiEndpoint: string = 'mollie') {
        super(httpClient, loginService, apiEndpoint);
    }

    getDetails(orderId: string): Promise<any> {
        return this.httpClient
            .get(`_action/${this.getApiBasePath()}/order/${orderId}/details`, {
                headers: this.getBasicHeaders(),
            })
            .then((response: any) => ApiService.handleResponse(response));
    }

    /**
     * Calls the plugin's own webhook for an order transaction, which is what Mollie calls when it
     * has something to report. Mollie only calls it when it can reach the shop and when there is
     * something to report - an expired payment that never redirected back leaves the order behind
     * with a stale status, and this lets the merchant fetch the current one.
     */
    triggerWebhook(transactionId: string): Promise<any> {
        return this.httpClient
            .post(
                `${this.getApiBasePath()}/webhook/${transactionId}`,
                {},
                {
                    headers: this.getBasicHeaders(),
                },
            )
            .then((response: any) => ApiService.handleResponse(response));
    }
}
