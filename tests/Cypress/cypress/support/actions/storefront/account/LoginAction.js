export default class LoginAction {

    /**
     *
     * @param email
     * @param password
     */
    doLogin(email, password) {

        cy.request({
            method: 'POST',
            url: '/account/login',
            form: true,
            followRedirect: false,
            failOnStatusCode: false,
            body: {
                username: email,
                password: password,
                redirectTo: 'frontend.account.home.page',
                redirectParameters: '[]',
            },
        });

        cy.visit('/');
    }

}

