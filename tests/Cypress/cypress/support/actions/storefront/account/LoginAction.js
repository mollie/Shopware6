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
            body: {
                email: email,
                password: password,
            },
        });
    }

}
