import Shopware from "Services/Shopware";
import OrdersListRepository from "Repositories/admin/orders/OrdersListRepository";

const shopware = new Shopware();
const repoOrdersList = new OrdersListRepository();


export default class AdminOrdersAction {

    /**
     *
     */
    openOrders() {
        cy.get('.sw-order').click();

        if (shopware.isVersionGreaterEqual(6.4)) {
            cy.wait(500);
            cy.get('.sw-order > .sw-admin-menu__sub-navigation-list > .sw-admin-menu__navigation-list-item > .sw-admin-menu__navigation-link').click();
            cy.wait(500);
        }
    }

    /**
     *
     * @param status
     */
    assertLatestOrderStatus(status) {

        this.openOrders();

        cy.contains(repoOrdersList.getLatestOrderStatusLabelSelector(), status);
    }

    /**
     *
     * @param status
     */
    assertLatestPaymentStatus(status) {

        this.openOrders();

        cy.contains(repoOrdersList.getLatestPaymentStatusLabelSelector(), status);
    }

}
