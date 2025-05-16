import NavigationRepository from 'Repositories/storefront/navigation/NavigationRepository';
import Shopware from "Services/shopware/Shopware";
const repo = new NavigationRepository();
const shopware = new Shopware();
export default class TopMenuAction {

    /**
     *
     */
    clickOnHome() {
        repo.getHomeMenuItem().click();
    }

    /**
     *
     */
    clickOnSecondCategory() {
        repo.getSecondMenuItem().click();
        if(shopware.isVersionGreaterEqual('6.7.0.0')){
            repo.getFlyOutMenuItem().click({force:true});
        }
    }

    /**
     *
     */
    clickAccountWidgetOrders() {
        cy.get('#accountWidget').click();
        cy.wait(500);

        cy.get('.header-account-menu > .card > .list-group > [href="/account/order"]').click();
    }

    /**
     *
     */
    clickAccountWidgetSubscriptions() {
        cy.get('#accountWidget').click();
        cy.wait(500);

        cy.get('.header-account-menu > .card [href="/account/mollie/subscriptions"]').click();
    }

}
