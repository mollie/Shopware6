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
    async isRefundManagerAvailable(salesChannelId, orderId) {
        const aclAllowed = this._acl.can('mollie_refund_manager:read');

        if (!aclAllowed) {
            return false;
        }

        let refundManagerPossible = false;

        await this._configService.getRefundManagerConfig(salesChannelId, orderId).then((response) => {
            refundManagerPossible = response.enabled;
        });

        return refundManagerPossible;
    }
}
