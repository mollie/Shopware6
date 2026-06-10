export default class LoginAction {

    /**
     *
     * @param email
     * @param password
     * @param attempt
     */
    doLogin(email, password, attempt = 1) {
        // followRedirect:false is critical: SW 6.6+ returns 302 on success. Without this flag
        // cy.request would follow the redirect, load the homepage HTML, and the body-length
        // check below would incorrectly classify a successful login as a failure.
        //
        // Success:  302 redirect (SW 6.6+) or empty 200 (SW 6.5)
        // Failure:  200 with HTML body (login form returned on wrong credentials)
        cy.request({
            method: 'POST',
            url: '/account/login',
            form: true,
            failOnStatusCode: false,
            followRedirect: false,
            body: {
                email: email,
                password: password,
            },
        }).then(function (response) {
            var loginFailed = response.status === 200
                && typeof response.body === 'string'
                && response.body.length > 0;

            if (loginFailed) {
                if (attempt >= 3) {
                    throw new Error('Login failed after 3 attempts for user: ' + email);
                }
                cy.log('Login attempt ' + attempt + ' failed (HTTP ' + response.status + '), retrying...');
                cy.clearAllCookies();
                cy.clearAllLocalStorage();
                cy.clearAllSessionStorage();
                cy.visit('/');
                this.doLogin(email, password, attempt + 1);
            } else {
                cy.visit('/');
            }
        }.bind(this));
    }

}

