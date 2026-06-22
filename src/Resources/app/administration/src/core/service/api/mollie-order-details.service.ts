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
}
