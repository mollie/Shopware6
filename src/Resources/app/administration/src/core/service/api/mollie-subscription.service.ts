const ApiService = Shopware.Classes.ApiService;

export default class MolliePaymentsSubscriptionService extends ApiService {
    constructor(httpClient: any, loginService: any, apiEndpoint: string = 'mollie') {
        super(httpClient, loginService, apiEndpoint);
    }

    cancel(data: Record<string, any> = { id: null, customerId: null, salesChannelId: null }): Promise<any> {
        return this.__post('/cancel', data);
    }

    pause(data: Record<string, any> = { id: null, customerId: null, salesChannelId: null }): Promise<any> {
        return this.__post('/pause', data);
    }

    resume(data: Record<string, any> = { id: null, customerId: null, salesChannelId: null }): Promise<any> {
        return this.__post('/resume', data);
    }

    skip(data: Record<string, any> = { id: null, customerId: null, salesChannelId: null }): Promise<any> {
        return this.__post('/skip', data);
    }

    getUserSubscriptions(data: Record<string, any> = { id: null, salesChannelId: null }): Promise<any> {
        return this.__get('/' + data.id);
    }

    cancelByMollieId(
        data: Record<string, any> = {
            mollieCustomerId: null,
            mollieSubscriptionId: null,
            mandateId: null,
            salesChannelId: null,
        },
    ): Promise<any> {
        return this.__get(
            `/cancel/${data.mollieCustomerId}/${data.mollieSubscriptionId}/${data.mandateId}/${data.salesChannelId}`,
        );
    }

    private __get(endpoint: string = ''): Promise<any> {
        return this.httpClient
            .get(`_action/${this.getApiBasePath()}/subscriptions${endpoint}`, {
                headers: this.getBasicHeaders({}),
            })
            .then((response: any) => ApiService.handleResponse(response))
            .catch((error: any) => ApiService.handleResponse(error.response));
    }

    private __post(
        endpoint: string = '',
        data: Record<string, any> = {},
        headers: Record<string, any> = {},
    ): Promise<any> {
        return this.httpClient
            .post(`_action/${this.getApiBasePath()}/subscriptions${endpoint}`, JSON.stringify(data), {
                headers: this.getBasicHeaders(headers),
            })
            .then((response: any) => ApiService.handleResponse(response))
            .catch((error: any) => ApiService.handleResponse(error.response));
    }
}
