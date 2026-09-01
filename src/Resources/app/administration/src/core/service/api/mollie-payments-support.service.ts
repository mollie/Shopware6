const ApiService = Shopware.Classes.ApiService;

export default class MolliePaymentsSupportService extends ApiService {
    constructor(httpClient: any, loginService: any, apiEndpoint: string = 'mollie') {
        super(httpClient, loginService, apiEndpoint);
    }

    request(name: string, email: string, recipientLocale: string, subject: string, message: string): Promise<any> {
        const data = {
            name: name,
            email: email,
            recipientLocale: recipientLocale,
            subject: subject,
            message: message,
        };

        return this.__post('/request', data);
    }

    private __post(
        endpoint: string = '',
        data: Record<string, any> = {},
        headers: Record<string, any> = {},
    ): Promise<any> {
        return this.httpClient
            .post(`_action/${this.getApiBasePath()}/support${endpoint}`, JSON.stringify(data), {
                headers: this.getBasicHeaders(headers),
            })
            .then((response: any) => ApiService.handleResponse(response));
    }
}
