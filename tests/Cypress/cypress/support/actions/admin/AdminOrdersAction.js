import Shopware from "Services/shopware/Shopware";
import OrdersListRepository from "Repositories/admin/orders/OrdersListRepository";
import OrderDetailsRepository from "Repositories/admin/orders/OrderDetailsRepository";
import MainMenuRepository from "Repositories/admin/MainMenuRepository";

const shopware = new Shopware();

const repoMainMenu = new MainMenuRepository();
const repoOrdersList = new OrdersListRepository();
const repoOrdersDetails = new OrderDetailsRepository();

export default class AdminOrdersAction {

    /**
     *
     */
    openOrders() {
        cy.wait(200);
        repoMainMenu.getOrders().click();
        cy.wait(1000);
        repoMainMenu.getOrdersOverview().click();
        cy.wait(1000);
    }

    /**
     *
     */
    openLastOrder() {
        repoOrdersList.getLatestOrderNumber().click();
    }


    /**
     *
     */
    openRefundManager() {
        repoOrdersDetails.getMollieActionsButton().click();
        cy.wait(2000);
        repoOrdersDetails.getMollieRefundManagerButton().click();
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
