export default class LoginAction {

    /**
     *
     * @param email
     * @param password
     * @param attempt
     */
    doLogin(email, password, attempt = 1) {
        cy.request('/account/login').then((response) => {
            const tokenMatch = response.body.match(/name="_csrf_token"\s+value="([^"]+)"/);
            const csrfToken = tokenMatch ? tokenMatch[1] : '';

            cy.request({
                method: 'POST',
                url: '/account/login',
                form: true,
                failOnStatusCode: false,
                body: {
                    email: email,
                    password: password,
                    _csrf_token: csrfToken,
                },
            }).then((loginResponse) => {
                const loginFailed = (loginResponse.redirects || []).some((r) => r.includes('/account/login'));

                if (loginFailed) {
                    if (attempt >= 3) {
                        throw new Error('Login failed after 3 attempts for user: ' + email);
                    }

                    cy.log('Login attempt ' + attempt + ' failed, retrying (' + (attempt + 1) + '/3)...');
                    cy.clearAllCookies();
                    cy.clearAllLocalStorage();
                    cy.clearAllSessionStorage();
                    cy.visit('/');

                    this.doLogin(email, password, attempt + 1);
                } else {
                    cy.visit('/');
                }
            });
        });
    }

}

