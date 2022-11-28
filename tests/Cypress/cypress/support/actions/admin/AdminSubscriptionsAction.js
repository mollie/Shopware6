import Shopware from "Services/shopware/Shopware";
import OrdersListRepository from "Repositories/admin/orders/OrdersListRepository";
import OrderDetailsRepository from "Repositories/admin/orders/OrderDetailsRepository";
import MainMenuRepository from "Repositories/admin/MainMenuRepository";

const shopware = new Shopware();

const repoMainMenu = new MainMenuRepository();

export default class AdminSubscriptionsAction {

    /**
     *
     */
    openSubscriptions() {
        cy.wait(200);
        repoMainMenu.getOrders().click();

        if (shopware.isVersionGreaterEqual(6.4)) {
            // starting with Shopware 6.4, we have to click
            // on the overview sub menu entry
            cy.wait(1000);
            repoMainMenu.getSubscriptionsOverview().click();
            cy.wait(1000);
        }
    }

    /**
     *
     * @param rowIndex
     */
    openSubscription(rowIndex) {
        cy.wait(2000);
        cy.get('.sw-data-grid__row--' + rowIndex + ' > .sw-data-grid__cell--description > .sw-data-grid__cell-content').click();
        cy.wait(2000);
    }

}
