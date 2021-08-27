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
    clickOnClothing() {
        repo.getClothingMenuItem().click();
    }

    /**
     *
     */
    clickAccountWidgetOrders() {
        cy.get('#accountWidget').click();
        cy.wait(500);

        cy.get('.header-account-menu > .card > .list-group > [href="/account/order"]').click();

    }

}
