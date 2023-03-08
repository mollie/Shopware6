import Shopware from "Services/shopware/Shopware";
import RefundManagerRepository from "Repositories/admin/refund-manager/RefundManagerRepository";

const shopware = new Shopware();


const repoRefundManager = new RefundManagerRepository();

// we have to force it
// because due to scrolling it might not
// always be visible
const forceOption = {force: true};


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

        // here are automatic reloads and things as it seems
        // I really want to test the real UX, so we just wait like a human
        cy.wait(4000);
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

        // here are automatic reloads and things as it seems
        // I really want to test the real UX, so we just wait like a human
        cy.wait(4000);
    }

    /**
     *
     */
    cancelPendingRefund() {
        repoRefundManager.getFirstRefundContextButton().click(forceOption);
        repoRefundManager.getFirstRefundCancelButton().click(forceOption);

        // here are automatic reloads and things as it seems
        // I really want to test the real UX, so we just wait like a human
        cy.wait(4000);
    }

    /**
     *
     */
    selectAllItems() {
        repoRefundManager.getSelectAllItemsButton().click(forceOption);
    }

}
