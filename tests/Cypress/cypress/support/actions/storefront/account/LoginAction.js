export default class LoginAction {

    /**
     *
     * @param email
     * @param password
     * @param attempt
     */
    doLogin(email, password, attempt = 1) {
        // createActionResponse() returns new Response() — an empty 200 — when no
        // redirectTo/forwardTo param is present (our case). A failed login forwards to the
        // login page and returns 200 with the full login form HTML. Body length distinguishes
        // the two; no followRedirect:false needed.
        cy.request({
            method: 'POST',
            url: '/account/login',
            form: true,
            failOnStatusCode: false,
            body: {
                email: email,
                password: password,
            },
        }).then(function (response) {
            var loginFailed = typeof response.body === 'string' && response.body.length > 0;

            if (loginFailed) {
                if (attempt >= 3) {
                    throw new Error('Login failed after 3 attempts for user: ' + email);
                }
                cy.log('Login attempt ' + attempt + ' failed, retrying...');
                cy.clearAllCookies();
                cy.clearAllLocalStorage();
                cy.clearAllSessionStorage();
                cy.visit('/');
                this.doLogin(email, password, attempt + 1);
            }
        }.bind(this));

        cy.visit('/');
    }

}

