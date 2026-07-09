import LoginRepository from "Repositories/storefront/account/LoginRepository";

const repoLogin = new LoginRepository();

export default class LoginAction {

    /**
     * @param email
     * @param password
     */
    doLogin(email, password) {

        this.submitLogin(email, password);

        cy.url().then((url) => {
            if (url.includes('/account/login')) {
                this.submitLogin(email, password);
            }
        });

        cy.url().should('not.include', '/account/login');
    }

    /**
     * @param email
     * @param password
     */
    submitLogin(email, password) {

        cy.visit('/account/login');
        cy.wait(600);
        repoLogin.getEmail().clear().type(email);
        repoLogin.getPassword().clear().type(password);
        repoLogin.getSubmitButton().click();
        cy.wait(200);
    }

}
