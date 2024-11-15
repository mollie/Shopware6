
import MolliePaymentsRefundBundleRepositoryService from './mollie-payments-refund-bundle-repository.service';
// eslint-disable-next-line no-undef
const ApiService = Shopware.Classes.ApiService;

class MolliePaymentsOrderService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'mollie') {
        super(httpClient, loginService, apiEndpoint);
    }

    getPaymentUrl(data = {orderId: null}) {
        const headers = this.getBasicHeaders();

        MolliePaymentsRefundBundleRepositoryService.setOrderId(data.orderId);
        MolliePaymentsRefundBundleRepositoryService.setHeaders(headers);
        MolliePaymentsRefundBundleRepositoryService.setClient(this.httpClient);

        return MolliePaymentsRefundBundleRepositoryService.fetch().then((response) => {
            return ApiService.handleResponse(response.data.payment);
        });
    }
}

export default MolliePaymentsOrderService;
