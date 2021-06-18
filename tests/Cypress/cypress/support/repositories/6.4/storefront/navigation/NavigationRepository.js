export default class NavigationRepository {

    /**
     *
     * @returns {*}
     */
    getHomeMenuItem() {
        return cy.get('.home-link > .main-navigation-link-text');
    }

}
