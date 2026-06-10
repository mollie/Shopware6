export default class LoginAction {

    /**
     * @param email
     * @param password
     */
    doLogin(email, password) {
        // followRedirect:false is required: SW 6.6+ returns 302 on success.
        // Without it cy.request follows the redirect, loads homepage HTML,
        // and any body-length check would misclassify a successful login as failure.
        cy.request({
            method: 'POST',
            url: '/account/login',
            form: true,
            failOnStatusCode: false,
            followRedirect: false,
            rejectUnauthorized: false,
            body: {
                email: email,
                password: password,
            },
        }).then(function (response) {
            expect(response.status, 'Login failed (expected 302 redirect) - check that the fixture user exists and credentials are correct. User: ' + email).to.eq(302);
        });
    }

}
