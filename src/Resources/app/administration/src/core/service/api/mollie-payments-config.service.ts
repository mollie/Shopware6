const ApiService = Shopware.Classes.ApiService;

export default class MolliePaymentsConfigService extends ApiService {
    private readonly currentLocale: any;

    constructor(httpClient: any, loginService: any, currentLocale: any) {
        super(httpClient, loginService, 'mollie');

        this.currentLocale = currentLocale;
    }

    testApiKeys(data: Record<string, any> = { liveApiKey: null, testApiKey: null }): Promise<any> {
        const headers = this.getBasicHeaders();

        return this.httpClient
            .post(`_action/${this.getApiBasePath()}/config/test-api-keys`, JSON.stringify(data), {
                headers: headers,
            })
            .then((response: any) => ApiService.handleResponse(response));
    }

    validateFlowBuilder(): Promise<any> {
        return this.httpClient
            .post(
                `_action/${this.getApiBasePath()}/config/validate/flowbuilder`,
                {
                    locale: this.currentLocale,
                },
                {
                    headers: this.getBasicHeaders(),
                },
            )
            .then((response: any) => ApiService.handleResponse(response));
    }

    getSubscriptionConfig(): Promise<any> {
        return this.httpClient
            .post(
                `_action/${this.getApiBasePath()}/config/subscription`,
                {
                    locale: this.currentLocale,
                },
                {
                    headers: this.getBasicHeaders(),
                },
            )
            .then((response: any) => ApiService.handleResponse(response));
    }
}
