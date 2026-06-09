import RefundManagerRepository from "Repositories/admin/refund-manager/RefundManagerRepository";

const repoRefundManager = new RefundManagerRepository();

// we have to force it
// because due to scrolling it might not
// always be visible
const forceOption = {force: true, timeout: 10000};


export default class RefundManagerAction {


    /**
     *
     * @param publicDesc
     * @param privateDesc
     */
    fullRefund(publicDesc, privateDesc) {
        repoRefundManager.getDescription().clear(forceOption).type(publicDesc, forceOption);

        if (privateDesc !== null && privateDesc.trim() !== '') {
            // empty strings do not work
            repoRefundManager.getInternalDescription().clear(forceOption).type(privateDesc, forceOption);
        }

        repoRefundManager.getVerifyCheckbox().click(forceOption);
        repoRefundManager.getFullRefundButton().click(forceOption);
    }

    /**
     *
     * @param amount
     * @param description
     */
    partialAmountRefund(amount, description) {
        repoRefundManager.getAmountField().clear(forceOption).type(amount, forceOption);
        repoRefundManager.getDescription().clear(forceOption).type(description, forceOption);
        repoRefundManager.getVerifyCheckbox().click(forceOption);
        repoRefundManager.getRefundButton().click(forceOption);
    }

    /**
     *
     */
    cancelPendingRefund() {
        repoRefundManager.getFirstRefundContextButton().click(forceOption);
        repoRefundManager.getFirstRefundCancelButton().click(forceOption);
    }

    /**
     *
     */
    selectAllItems() {
        repoRefundManager.getFirstRefundQuantityInput().should('be.visible');
        repoRefundManager.getSelectAllItemsButton().click(forceOption);
    }

}
