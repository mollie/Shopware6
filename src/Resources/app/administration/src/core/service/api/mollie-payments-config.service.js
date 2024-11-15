// eslint-disable-next-line no-undef
import MolliePaymentsRefundBundleRepositoryService from './mollie-payments-refund-bundle-repository.service';

const ApiService = Shopware.Classes.ApiService;

export default class MolliePaymentsConfigService extends ApiService {
    repository;


    /**
     *
     * @param httpClient
     * @param loginService
     * @param currentLocale
     */
    constructor(httpClient, loginService, currentLocale) {
        super(httpClient, loginService, 'mollie');

        this.currentLocale = currentLocale;
    }

    /**
     *
     * @param data
     * @returns {*}
     */
    testApiKeys(data = {liveApiKey: null, testApiKey: null}) {
        const headers = this.getBasicHeaders();

        return this.httpClient
            .post(
                `_action/${this.getApiBasePath()}/config/test-api-keys`,
                JSON.stringify(data),
                {
                    headers: headers,
                }
            )
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    /**
     *
     * @returns {*}
     */
    validateFlowBuilder() {
        return this.httpClient
            .post(
                `_action/${this.getApiBasePath()}/config/validate/flowbuilder`,
                {
                    locale: this.currentLocale,
                },
                {
                    headers: this.getBasicHeaders(),
                }
            ).then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    getSubscriptionConfig(){
        return this.httpClient
            .post(
                `_action/${this.getApiBasePath()}/config/subscription`,
                {
                    locale: this.currentLocale,
                },
                {
                    headers: this.getBasicHeaders(),
                }
            ).then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    /**
     *
     * @param salesChannelId
     * @param orderId
     * @returns {*}
     */
    getRefundManagerConfig(salesChannelId, orderId) {
        MolliePaymentsRefundBundleRepositoryService.setOrderId(orderId);
        MolliePaymentsRefundBundleRepositoryService.setClient(this.httpClient);
        MolliePaymentsRefundBundleRepositoryService.setHeaders(this.getBasicHeaders());

        return MolliePaymentsRefundBundleRepositoryService.fetch()
    }

}
