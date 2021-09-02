import Shopware from "Services/Shopware";
import OrdersListRepository from "Repositories/admin/orders/OrdersListRepository";
import MainMenuRepository from "Repositories/admin/MainMenuRepository";

const shopware = new Shopware();

const repoMainMenu = new MainMenuRepository();
const repoOrdersList = new OrdersListRepository();


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
