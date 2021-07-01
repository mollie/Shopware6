export default class AdminLoginAction {

    /**
     *
     */
    login() {

        cy.visit('/admin');

        cy.get('#sw-field--username').type('admin');
        cy.get('#sw-field--password').type('shopware');

        cy.get('.sw-button').click();
    }

}
