export default class AdminLoginAction {

    /**
     *
     */
    login() {

        cy.viewport(1920, 2600);

        cy.visit('/admin');

        cy.get('#sw-field--username').type('admin');
        cy.get('#sw-field--password').type('shopware');

        cy.get('.sw-button').click();
    }

}
