import LoginRepository from "Repositories/storefront/account/LoginRepository";

const repoLogin = new LoginRepository();

export default class LoginAction {

    /**
     * Logs the customer in through the real storefront login form (user journey).
     * The form login is wrapped in cy.session() so it runs ONCE and the
     * authenticated cookie is cached and replayed for every following spec. That
     * keeps the form under test while avoiding the SW 6.6/6.7 login rate limiter
     * that repeated per-spec UI logins of the same account would otherwise trip.
     *
     * @param email
     * @param password
     */
    doLogin(email, password) {

            cy.visit('/account/login');

            repoLogin.getEmail().clear().type(email);
            repoLogin.getPassword().clear().type(password);
            repoLogin.getSubmitButton().click();

            cy.url().should('not.include', '/account/login');

        cy.visit('/');
    }

}
