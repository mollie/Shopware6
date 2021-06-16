export default class LoginRepository {

    /**
     *
     * @returns {*}
     */
    getEmail() {
        return cy.get('#loginMail');
    }

    /**
     *
     * @returns {*}
     */
    getPassword() {
        return cy.get('#loginPassword');
    }

    /**
     *
     * @returns {*}
     */
    getSubmitButton() {
        return cy.get('.login-submit > .btn')
    }

}
