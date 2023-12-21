// eslint-disable-next-line no-undef
const ApiService = Shopware.Classes.ApiService;

class MolliePaymentsShippingService extends ApiService {

    /**
     *
     * @param httpClient
     * @param loginService
     * @param apiEndpoint
     */
    constructor(httpClient, loginService, apiEndpoint = 'mollie') {
        super(httpClient, loginService, apiEndpoint);
    }

    /**
     *
     * @param orderId
     * @param trackingCarrier
     * @param trackingCode
     * @param trackingUrl
     * @param items
     * @returns {*}
     */
    shipOrder(orderId, trackingCarrier, trackingCode, trackingUrl, items) {

        const data = {
            orderId: orderId,
            trackingCarrier: trackingCarrier,
            trackingCode: trackingCode,
            trackingUrl: trackingUrl,
            items: items,
        }

        return this.__post('', data);
    }

    /**
     *
     * @param data
     * @returns {*}
     */
    shipItem(data = {
        orderId: null,
        itemId: null,
        quantity: null,
        trackingCarrier: null,
        trackingCode: null,
        trackingUrl: null,
    }) {
        return this.__post('/item', data);
    }

    /**
     *
     * @param data
     * @returns {*}
     */
    status(data = {orderId: null}) {
        return this.__post('/status', data);
    }

    /**
     *
     * @param data
     * @returns {*}
     */
    total(data = {orderId: null}) {
        return this.__post('/total', data);
    }

    /**
     *
     * @param endpoint
     * @param data
     * @param headers
     * @returns {*}
     * @private
     */
    __post(endpoint = '', data = {}, headers = {}) {
        return this.httpClient
            .post(
                `_action/${this.getApiBasePath()}/ship${endpoint}`,
                JSON.stringify(data),
                {
                    headers: this.getBasicHeaders(headers),
                }
            )
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

}

export default MolliePaymentsShippingService;
