export default class RegisterRepository {


    /**
     *
     * @returns {*}
     */
    getAccountType() {
        return cy.get('#accountType');
    }

    /**
     *
     * @returns {*}
     */
    getSalutation() {
        return cy.get('#personalSalutation');
    }

    /**
     *
     * @returns {*}
     */
    getFirstname() {
        return cy.get('#personalFirstName');
    }

    /**
     *
     * @returns {*}
     */
    getLastname() {
        return cy.get('#personalLastName');
    }

    /**
     *
     * @returns {*}
     */
    getCompany() {
        return cy.get('#billingAddresscompany');
    }

    /**
     *
     * @returns {*}
     */
    getEmail() {
        return cy.get('#personalMail');
    }

    /**
     *
     * @returns {*}
     */
    getPassword() {
        return cy.get('#personalPassword');
    }

    /**
     *
     * @returns {*}
     */
    getStreet() {
        return cy.get('#billingAddressAddressStreet');
    }

    /**
     *
     * @returns {*}
     */
    getZipcode() {
        return cy.get('#billingAddressAddressZipcode');
    }

    /**
     *
     * @returns {*}
     */
    getCity() {
        return cy.get('#billingAddressAddressCity');
    }

    /**
     *
     * @returns {*}
     */
    getCountry() {
        return cy.get('#billingAddressAddressCountry');
    }

    /**
     *
     * @returns {*}
     */
    getRegisterButton() {
        return cy.get('.register-submit > .btn');
    }

}
