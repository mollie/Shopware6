export default class AdminLoginAction {

    /**
     *
     */
    login() {

        // increase our viewport for admin
        // otherwise we don't see a lot (page height)
        cy.viewport(1920, 1500);
        cy.visit('/admin');
        cy.wait(4000);
        cy.get('#sw-field--username').type('admin');
        cy.get('#sw-field--password,.mt-password-field__input').type('shopware');

        cy.get('.sw-button,.sw-login__submit button').click();
        cy.wait(4000);
    }

}
