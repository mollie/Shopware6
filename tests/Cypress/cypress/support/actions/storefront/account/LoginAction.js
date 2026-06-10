export default class LoginAction {

    /**
     *
     * @param email
     * @param password
     * @param attempt
     */
    doLogin(email, password, attempt = 1) {
        // SW 6.7 login form has no _csrf_token. A successful login returns 302 (redirect
        // to home); a failed login returns 200 (login form re-rendered). followRedirect:false
        // lets us distinguish the two without an extra cy.visit('/account') verification step.
        cy.request({
            method: 'POST',
            url: '/account/login',
            form: true,
            followRedirect: false,
            failOnStatusCode: false,
            body: {
                email: email,
                password: password,
            },
        }).then(function (response) {
            if (response.status !== 302) {
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

