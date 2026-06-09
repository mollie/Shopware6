export default class LoginAction {

    /**
     *
     * @param email
     * @param password
     */
    doLogin(email, password) {
        cy.request('/account/login').then((response) => {
            const tokenMatch = response.body.match(/name="_csrf_token"\s+value="([^"]+)"/);
            const csrfToken = tokenMatch ? tokenMatch[1] : '';

            cy.request({
                method: 'POST',
                url: '/account/login',
                form: true,
                body: {
                    email: email,
                    password: password,
                    _csrf_token: csrfToken,
                },
            });
        });

        cy.visit('/');
    }

}

