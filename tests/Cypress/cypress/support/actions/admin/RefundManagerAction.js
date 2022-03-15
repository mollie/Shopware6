import Shopware from "Services/shopware/Shopware";
import RefundManagerRepository from "Repositories/admin/refund-manager/RefundManagerRepository";

const shopware = new Shopware();


const repoRefundManager = new RefundManagerRepository();

export default class RefundManagerAction {


    /**
     *
     * @param description
     */
    fullRefund(description)
    {
        repoRefundManager.getDescription().clear().type(description);
        repoRefundManager.getVerifyCheckbox().click();
        repoRefundManager.getFullRefundButton().click();

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

        repoRefundManager.getAmountField().clear().type(amount);
        repoRefundManager.getDescription().clear().type(description);
        repoRefundManager.getVerifyCheckbox().click();
        repoRefundManager.getRefundButton().click();

        // here are automatic reloads and things as it seems
        // I really want to test the real UX, so we just wait like a human
        cy.wait(4000);
    }

    /**
     *
     */
    cancelPendingRefund() {

        repoRefundManager.getFirstRefundContextButton().click();
        repoRefundManager.getFirstRefundCancelButton().click();

        // here are automatic reloads and things as it seems
        // I really want to test the real UX, so we just wait like a human
        cy.wait(4000);
    }

}
