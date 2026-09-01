const ApiService = Shopware.Classes.ApiService;

export default class MolliePaymentsRefundService extends ApiService {
    constructor(httpClient: any, loginService: any, apiEndpoint: string = 'mollie') {
        super(httpClient, loginService, apiEndpoint);
    }

    getRefundOverview(data: Record<string, any> = { orderId: null }): Promise<any> {
        return this.__post('/order/refund-overview', data);
    }

    list(data: Record<string, any> = { orderId: null }): Promise<any> {
        return this.__post('/refund/list', data);
    }

    refund(
        data: Record<string, any> = {
            orderId: null,
            amount: null,
            description: '',
            internalDescription: '',
            items: [],
        },
    ): Promise<any> {
        return this.__post('/refund', data);
    }

    refundAll(data: Record<string, any> = { orderId: null, description: '', internalDescription: '' }): Promise<any> {
        return this.__post('/refund', data);
    }

    cancel(data: Record<string, any> = { orderId: null, refundId: null }): Promise<any> {
        return this.__post('/refund/cancel', data);
    }

    private __post(
        endpoint: string = '',
        data: Record<string, any> = {},
        headers: Record<string, any> = {},
    ): Promise<any> {
        return this.httpClient
            .post(`_action/${this.getApiBasePath()}${endpoint}`, JSON.stringify(data), {
                headers: this.getBasicHeaders(headers),
            })
            .then((response: any) => ApiService.handleResponse(response))
            .catch((error: any) => ApiService.handleResponse(error.response));
    }
}
