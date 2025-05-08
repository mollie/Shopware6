export default class NavigationRepository {

    /**
     *
     * @returns {*}
     */
    getHomeMenuItem() {
        return cy.get('.home-link > .main-navigation-link-text');
    }

    /**
     *
     * @returns {*}
     */
    getSecondMenuItem() {
        return cy.get('#mainNavigation > div.container > nav > a:nth-child(2),.main-navigation-menu-list li.nav-item:nth-child(2) a.nav-link').first();
    }

}
