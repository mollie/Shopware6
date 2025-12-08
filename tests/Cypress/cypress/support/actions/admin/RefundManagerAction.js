import Shopware from "Services/shopware/Shopware";
import RefundManagerRepository from "Repositories/admin/refund-manager/RefundManagerRepository";
import AdminOrdersAction from "Actions/admin/AdminOrdersAction";

const shopware = new Shopware();

const adminOrders = new AdminOrdersAction();

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

        // here are automatic reloads and things as it seems
        // I really want to test the real UX, so we just wait like a human
        cy.wait(5000);
        // refunds are loaded directly without a page reload,
        // in some cypress browser this does not work properly
        // so we reload the page
        cy.reload();
        adminOrders.openRefundManager();
        // this wait is also necessary somehow
        cy.wait(2000);
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
        cy.wait(5000);
        // refunds are loaded directly without a page reload,
        // in some cypress browser this does not work properly
        // so we reload the page
        cy.reload();
        adminOrders.openRefundManager();
        // this wait is also necessary somehow
        cy.wait(2000);
    }

    /**
     *
     */
    cancelPendingRefund() {
        repoRefundManager.getFirstRefundContextButton().click(forceOption);
        repoRefundManager.getFirstRefundCancelButton().click(forceOption);

        // here are automatic reloads and things as it seems
        // I really want to test the real UX, so we just wait like a human
        cy.wait(5000);
    }

    /**
     *
     */
    selectAllItems() {
        repoRefundManager.getFirstRefundQuantityInput().should('be.visible');
        repoRefundManager.getSelectAllItemsButton().click(forceOption);
    }

}
