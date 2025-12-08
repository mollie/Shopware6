import NavigationRepository from 'Repositories/storefront/navigation/NavigationRepository';
import Shopware from "Services/shopware/Shopware";

const repo = new NavigationRepository();
const shopware = new Shopware();
export default class TopMenuAction {
    

    /**
     *
     */
    clickAccountWidgetSubscriptions() {
        cy.get('#accountWidget').click();
        cy.wait(500);

        cy.get('.header-account-menu > .card [href="/account/mollie/subscriptions"]').click();
    }

}
