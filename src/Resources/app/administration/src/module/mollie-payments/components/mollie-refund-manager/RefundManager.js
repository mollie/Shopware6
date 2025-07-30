export default class RefundManager {
    /**
     *
     * @param configService
     * @param acl
     */
    constructor(configService, acl) {
        this._configService = configService;
        this._acl = acl;
    }

    /**
     * Gets if the refund manager is available
     * @returns Promise<boolean>
     */
    async isRefundManagerAvailable(salesChannelId, order) {
        const currentPaymentTransactionStatus = order?.transactions?.[0]?.stateMachineState?.technicalName ?? 'unknown';
        //Authorized orders cannot be refunded.
        if (currentPaymentTransactionStatus === 'authorized') {
            return false;
        }

        const aclAllowed = this._acl.can('mollie_refund_manager:read');

        if (!aclAllowed) {
            return false;
        }

        let refundManagerPossible = false;

        await this._configService.getRefundManagerConfig(salesChannelId, order.id).then((response) => {
            refundManagerPossible = response.enabled;
        });

        return refundManagerPossible;
    }
}
