import Shopware from "Services/shopware/Shopware";
import OrdersListRepository from "Repositories/admin/orders/OrdersListRepository";
import OrderDetailsRepository from "Repositories/admin/orders/OrderDetailsRepository";
import MollieRefundManagerRepository from "Repositories/admin/orders/MollieRefundManagerRepository";
import MainMenuRepository from "Repositories/admin/MainMenuRepository";

const shopware = new Shopware();

const repoMainMenu = new MainMenuRepository();
const repoOrdersList = new OrdersListRepository();
const repoOrdersDetails = new OrderDetailsRepository();
const repoRefundManager = new MollieRefundManagerRepository();


export default class AdminOrdersAction {

    /**
     *
     */
    openOrders() {
        cy.wait(200);
        repoMainMenu.getOrders().click();

        if (shopware.isVersionGreaterEqual(6.4)) {
            // starting with Shopware 6.4, we have to click
            // on the overview sub menu entry
            cy.wait(1000);
            repoMainMenu.getOrdersOverview().click();
            cy.wait(1000);
        }
    }

    /**
     *
     */
    openLastOrder() {
        repoOrdersList.getLatestOrderNumber().click();
    }

    /**
     *
     * @param amount
     */
    refundOrder(amount) {

        repoOrdersDetails.getMollieActionsButton().click();
        cy.wait(2000);
        repoOrdersDetails.getMollieActionButtonRefundOrder().click();

        repoRefundManager.getAmountField().type(amount);
        repoRefundManager.getSubmitButton().click();

        // here are automatic reloads and things as it seems
        // I really want to test the real UX, so we just wait like a human
        cy.wait(4000);
    }

    /**
     *
     */
    cancelOrderRefund() {

        repoOrdersDetails.getMollieActionsButton().click();
        cy.wait(2000);
        repoOrdersDetails.getMollieActionButtonRefundOrder().click();

        // there must be a pending refund
        repoRefundManager.getFirstRefundStatusLabel().contains('Pending');

        // click on more and CANCEL it
        repoRefundManager.getFirstRefundMoreButton().click();
        repoRefundManager.getFirstRefundCancelButton().click();

        // here are automatic reloads and things as it seems
        // I really want to test the real UX, so we just wait like a human
        cy.wait(4000);
    }

    /**
     *
     * @param status
     */
    assertLatestOrderStatus(status) {

        this.openOrders();

        cy.wait(500);
        cy.contains(repoOrdersList.getLatestOrderStatusLabelSelector(), status);
    }

    /**
     *
     * @param status
     */
    assertLatestPaymentStatus(status) {

        this.openOrders();

        cy.wait(500);
        cy.contains(repoOrdersList.getLatestPaymentStatusLabelSelector(), status);
    }

}
