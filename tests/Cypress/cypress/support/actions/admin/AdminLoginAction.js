export default class AdminLoginAction {

    /**
     *
     */
    login() {

        // increase our viewport for admin
        // otherwise we don't see a lot (page height)
        cy.viewport(1920, 1500);

        cy.visit('/admin');

        cy.get('#sw-field--username', {timeout: 10000}).type('admin');
        cy.get('#sw-field--password').type('shopware');

        cy.get('.sw-button,.sw-login__submit button').click();

        // make sure we are logged in
        cy.get('.sw-version__info', {timeout: 10000}).should('be.visible');
    }

}
