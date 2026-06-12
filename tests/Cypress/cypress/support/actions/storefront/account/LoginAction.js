import LoginRepository from "Repositories/storefront/account/LoginRepository";

const repoLogin = new LoginRepository();

export default class LoginAction {

    /**
     * @param email
     * @param password
     */
    doLogin(email, password) {

        cy.visit('/account/login');

        repoLogin.getEmail().clear().type(email);
        repoLogin.getPassword().clear().type(password);
        repoLogin.getSubmitButton().click();
        cy.wait(200);
        cy.url().should('not.include', '/account/login');
    }

}
