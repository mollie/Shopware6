import NavigationRepository from 'Repositories/storefront/navigation/NavigationRepository';

const repo = new NavigationRepository();

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

        cy.get('.header-account-menu > .card > [href="/account/mollie/subscriptions"]').click();
    }


}
