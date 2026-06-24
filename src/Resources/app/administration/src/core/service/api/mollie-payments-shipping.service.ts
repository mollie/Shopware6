const ApiService = Shopware.Classes.ApiService;

export default class MolliePaymentsShippingService extends ApiService {
    constructor(httpClient: any, loginService: any, apiEndpoint: string = 'mollie') {
        super(httpClient, loginService, apiEndpoint);
    }

    shipOrder(
        orderId: string,
        trackingCarrier: string,
        trackingCode: string,
        trackingUrl: string,
        items: any[],
    ): Promise<any> {
        const data = {
            orderId: orderId,
            trackingCarrier: trackingCarrier,
            trackingCode: trackingCode,
            trackingUrl: trackingUrl,
            items: items,
        };

        return this.__post('', data);
    }

    shipItem(
        data: Record<string, any> = {
            orderId: null,
            itemId: null,
            quantity: null,
            trackingCarrier: null,
            trackingCode: null,
            trackingUrl: null,
        },
    ): Promise<any> {
        return this.__post('/item', data);
    }

    status(data: Record<string, any> = { orderId: null }): Promise<any> {
        return this.__post('/status', data);
    }

    total(data: Record<string, any> = { orderId: null }): Promise<any> {
        return this.__post('/total', data);
    }

    private __post(
        endpoint: string = '',
        data: Record<string, any> = {},
        headers: Record<string, any> = {},
    ): Promise<any> {
        return this.httpClient
            .post(`_action/${this.getApiBasePath()}/ship${endpoint}`, JSON.stringify(data), {
                headers: this.getBasicHeaders(headers),
            })
            .then((response: any) => ApiService.handleResponse(response));
    }
}
