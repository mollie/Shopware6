import LoginRepository from "Repositories/storefront/account/LoginRepository";

const repoLogin = new LoginRepository();

export default class LoginAction {

    /**
     * @param email
     * @param password
     */
    doLogin(email, password) {

        cy.visit('/account/login');

        cy.intercept('POST', '/account/login').as('loginSubmit');

        repoLogin.getEmail().clear().type(email);
        repoLogin.getPassword().clear().type(password);
        repoLogin.getSubmitButton().click();

        // wait until the submit POST (and its redirect) has actually completed
        // before asserting the URL, instead of relying on the bare retry window.
        cy.wait('@loginSubmit').then(function (interception) {
            cy.log('login POST status: ' + interception.response.statusCode);
            cy.log('login POST location: ' + interception.response.headers['location']);
            cy.log('login POST set-cookie: ' + JSON.stringify(interception.response.headers['set-cookie']));
        });

        // dump the cookie jar after login so we can see whether a session cookie
        // exists at all and with which attributes (SameSite / Secure / domain).
        cy.getCookies().then(function (cookies) {
            cy.log('cookies after login: ' + JSON.stringify(cookies));
        });

        // hit /account through the cookie jar (no browser cache) to check whether
        // the session cookie is server-side valid: 302 here => server does not
        // know this session as logged in (session-id regeneration mismatch);
        // 200 here => browser/cache issue only.
        cy.request({ url: '/account', followRedirect: false, failOnStatusCode: false }).then(function (resp) {
            cy.log('cy.request GET /account status: ' + resp.status);
            cy.log('cy.request GET /account location: ' + resp.headers['location']);
        });

        // explicit fresh navigation to the post-login page: if the session cookie
        // from the 302 is really set, this lands on /account instead of bouncing
        // back to /account/login.
        cy.visit('/account');
        cy.url().should('not.include', '/account/login');
    }

}
