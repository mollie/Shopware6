// eslint-disable-next-line no-undef
const ApiService = Shopware.Classes.ApiService;

export default class MolliePaymentsRefundBundleRepositoryService extends ApiService {
    static response = null;
    static orderId = null;
    static headers = null
    static client = null;

    static setOrderId(orderId) {
        if (orderId !== null) {
            MolliePaymentsRefundBundleRepositoryService.orderId = orderId;
        }
    }

    static setHeaders(headers) {
        if (headers !== null) {
            MolliePaymentsRefundBundleRepositoryService.headers = headers;
        }
    }

    static setClient(client) {
        if (client !== null) {
            MolliePaymentsRefundBundleRepositoryService.client = client;
        }
    }

    static fetch() {
        if (!MolliePaymentsRefundBundleRepositoryService.client) {
            throw new Error('Client not set. Please set the client using setClient() method.');
        }

        if (!MolliePaymentsRefundBundleRepositoryService.orderId) {
            throw new Error('orderId not set. Please set the orderId using setOrderId() method.');
        }

        if (MolliePaymentsRefundBundleRepositoryService.response !== null) {
            return MolliePaymentsRefundBundleRepositoryService.response;
        }

        MolliePaymentsRefundBundleRepositoryService.response = MolliePaymentsRefundBundleRepositoryService.client.post(
            '_action/mollie/refund-manager/bundled',
            {
                orderId: MolliePaymentsRefundBundleRepositoryService.orderId,
            },
            {
                headers: MolliePaymentsRefundBundleRepositoryService.headers,
            }
        );

        return MolliePaymentsRefundBundleRepositoryService.response;
    }
}
